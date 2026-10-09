<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inti proses bisnis POS:
 *
 *  Pelanggan memesan -> Kasir mencatat (pending) -> Sistem cek stok
 *  -> Pesanan diproses (stok otomatis berkurang) -> Siap diambil
 *  -> Dibayar & diselesaikan -> Tersimpan di riwayat transaksi.
 *  Pembatalan setelah stok dipotong akan mengembalikan stok secara otomatis.
 */
class OrderService
{
    public function __construct(private StockService $stock) {}

    /**
     * Mencatat pesanan baru (status: pending).
     *
     * @param  array{customer_id?:int|null,type:string,pickup_at?:string|null,discount?:numeric,notes?:string|null,items:array<int,array{product_id:int,quantity:int,notes?:string|null}>}  $data
     */
    public function create(array $data, User $cashier): Order
    {
        return DB::transaction(function () use ($data, $cashier) {
            $type = OrderType::from($data['type']);
            $lines = $this->buildLines($data['items'], checkStock: $type !== OrderType::Preorder);

            $order = new Order([
                'customer_id' => $data['customer_id'] ?? null,
                'type' => $type,
                'pickup_at' => $type === OrderType::Preorder ? ($data['pickup_at'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
            ]);
            $order->user_id = $cashier->id;
            $order->code = $this->generateCode();
            $this->fillTotals($order, $lines, (float) ($data['discount'] ?? 0));
            $order->save();

            $order->items()->createMany($lines->all());

            return $order->load(['items', 'customer', 'cashier']);
        });
    }

    /** Mengubah pesanan — hanya selama masih pending (belum diproses). */
    public function update(Order $order, array $data): Order
    {
        $this->ensureStatus($order, [OrderStatus::Pending], 'Pesanan hanya dapat diubah selama berstatus Menunggu.');
        if ($order->isPaid()) {
            throw new BusinessException('Pesanan yang sudah dibayar tidak dapat diubah.', 409);
        }

        return DB::transaction(function () use ($order, $data) {
            $type = isset($data['type']) ? OrderType::from($data['type']) : $order->type;

            $order->fill([
                'customer_id' => array_key_exists('customer_id', $data) ? $data['customer_id'] : $order->customer_id,
                'type' => $type,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
            ]);
            $order->pickup_at = $type === OrderType::Preorder
                ? ($data['pickup_at'] ?? $order->pickup_at)
                : null;

            if ($type === OrderType::Preorder && (! $order->customer_id || ! $order->pickup_at)) {
                throw new BusinessException('Preorder wajib memiliki pelanggan dan jadwal pengambilan.', 422, [
                    'pickup_at' => ['Preorder wajib memiliki pelanggan dan jadwal pengambilan.'],
                ]);
            }

            if (isset($data['items'])) {
                $lines = $this->buildLines($data['items'], checkStock: $type !== OrderType::Preorder);
                $order->items()->delete();
                $order->items()->createMany($lines->all());
            } else {
                $lines = $order->items()->get(['product_id', 'product_name', 'price', 'quantity', 'subtotal', 'notes'])
                    ->map(fn ($i) => $i->toArray());
            }

            $this->fillTotals($order, collect($lines), (float) ($data['discount'] ?? $order->discount));

            $order->save();

            return $order->load(['items', 'customer', 'cashier']);
        });
    }

    /** Menjalankan perpindahan status sesuai state machine. */
    public function changeStatus(Order $order, OrderStatus $target, User $user, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $target, $user, $reason) {
            /** @var Order $order */
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->status->canTransitionTo($target)) {
                throw new BusinessException(
                    "Status tidak dapat diubah dari '{$order->status->label()}' ke '{$target->label()}'.",
                    409,
                    ['status' => ['Transisi yang diizinkan: '.(collect($order->status->allowedTransitions())->map->value->implode(', ') ?: 'tidak ada (status final)')]],
                );
            }

            match ($target) {
                OrderStatus::Processing => $this->process($order, $user),
                OrderStatus::Ready => null,
                OrderStatus::Completed => $this->complete($order),
                OrderStatus::Cancelled => $this->cancel($order, $user, $reason),
                default => null,
            };

            $order->status = $target;
            $order->save();

            return $order->load(['items', 'customer', 'cashier']);
        });
    }

    /** Mencatat pembayaran pesanan. */
    public function pay(Order $order, PaymentMethod $method, float $amount): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw new BusinessException('Pesanan yang dibatalkan tidak dapat dibayar.', 409);
        }
        if ($order->isPaid()) {
            throw new BusinessException('Pesanan ini sudah lunas.', 409);
        }

        $total = (float) $order->total;
        if ($amount < $total) {
            throw new BusinessException('Jumlah bayar kurang dari total tagihan.', 422, [
                'paid_amount' => ['Jumlah bayar minimal Rp '.number_format($total, 0, ',', '.').'.'],
            ]);
        }
        if ($method !== PaymentMethod::Cash && $amount != $total) {
            throw new BusinessException('Pembayaran non-tunai harus sama dengan total tagihan.', 422, [
                'paid_amount' => ['Untuk QRIS/transfer, jumlah bayar harus pas.'],
            ]);
        }

        $order->forceFill([
            'payment_method' => $method,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => $amount,
            'change_amount' => $amount - $total,
            'paid_at' => now(),
        ])->save();

        return $order->load(['items', 'customer', 'cashier']);
    }

    // ---------------------------------------------------------------------

    /** Cek ketersediaan stok (dengan lock) lalu potong stok otomatis. */
    private function process(Order $order, User $user): void
    {
        $items = $order->items()->get();
        $products = Product::whereIn('id', $items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

        $shortages = [];
        foreach ($items->groupBy('product_id') as $productId => $group) {
            $needed = $group->sum('quantity');
            $product = $products->get($productId);
            if (! $product || ! $product->is_active) {
                $shortages["product_{$productId}"] = ["Produk {$group->first()->product_name} sudah tidak tersedia."];
            } elseif ($product->stock < $needed) {
                $shortages["product_{$productId}"] = ["Stok {$product->name} kurang: dibutuhkan {$needed}, tersedia {$product->stock}."];
            }
        }

        if ($shortages) {
            throw new BusinessException('Stok tidak mencukupi untuk memproses pesanan ini.', 422, $shortages);
        }

        foreach ($items as $item) {
            $this->stock->move(
                $products->get($item->product_id),
                StockMovementType::Out,
                -$item->quantity,
                $user,
                $order,
                "Penjualan {$order->code}",
            );
        }

        $order->stock_deducted = true;
        $order->processed_at = now();
    }

    private function complete(Order $order): void
    {
        if (! $order->isPaid()) {
            throw new BusinessException('Pesanan belum dibayar. Catat pembayaran sebelum menyelesaikan pesanan.', 409, [
                'payment_status' => ['Pesanan harus lunas sebelum diselesaikan.'],
            ]);
        }
        $order->completed_at = now();
    }

    private function cancel(Order $order, User $user, ?string $reason): void
    {
        if ($order->stock_deducted) {
            $order->load('items');
            $products = Product::withTrashed()->whereIn('id', $order->items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($order->items as $item) {
                $this->stock->move(
                    $products->get($item->product_id),
                    StockMovementType::Return,
                    $item->quantity,
                    $user,
                    $order,
                    "Pembatalan {$order->code}",
                );
            }
            $order->stock_deducted = false;
        }

        if ($order->isPaid()) {
            $order->payment_status = PaymentStatus::Refunded;
        }

        $order->cancel_reason = $reason;
        $order->cancelled_at = now();
    }

    /**
     * Susun baris item + snapshot harga. Untuk pesanan non-preorder stok dicek
     * di awal agar kasir langsung tahu jika barang habis.
     */
    private function buildLines(array $items, bool $checkStock): Collection
    {
        $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');
        $errors = [];

        $lines = collect($items)->map(function ($item, $i) use ($products, &$errors) {
            $product = $products->get($item['product_id']);
            if (! $product || ! $product->is_active) {
                $errors["items.{$i}.product_id"] = ['Produk tidak tersedia untuk dijual.'];

                return null;
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $product->price,
                'quantity' => (int) $item['quantity'],
                'subtotal' => round((float) $product->price * (int) $item['quantity'], 2),
                'notes' => $item['notes'] ?? null,
            ];
        });

        if ($checkStock && ! $errors) {
            foreach ($lines->groupBy('product_id') as $productId => $group) {
                $product = $products->get($productId);
                $qty = $group->sum('quantity');
                if ($product->stock < $qty) {
                    $errors["product_{$productId}"] = ["Stok {$product->name} tidak mencukupi: diminta {$qty}, tersedia {$product->stock}."];
                }
            }
        }

        if ($errors) {
            throw new BusinessException('Sebagian item pesanan tidak dapat dipenuhi.', 422, $errors);
        }

        return $lines->values();
    }

    private function fillTotals(Order $order, Collection $lines, float $discount): void
    {
        $subtotal = round((float) $lines->sum('subtotal'), 2);
        if ($discount > $subtotal) {
            throw new BusinessException('Diskon tidak boleh melebihi subtotal.', 422, ['discount' => ['Diskon melebihi subtotal.']]);
        }
        $order->subtotal = $subtotal;
        $order->discount = $discount;
        $order->total = $subtotal - $discount;
    }

    private function ensureStatus(Order $order, array $allowed, string $message): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw new BusinessException($message, 409);
        }
    }

    private function generateCode(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';
        $last = Order::where('code', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('code')->value('code');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
