<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Ubah stok produk dan catat riwayatnya. Wajib dipanggil di dalam transaksi
     * dengan produk yang sudah di-lock (lockForUpdate).
     */
    public function move(
        Product $product,
        StockMovementType $type,
        int $quantity,
        ?User $user = null,
        ?Order $order = null,
        ?string $note = null,
    ): StockMovement {
        $before = $product->stock;
        $after = $before + $quantity;

        if ($after < 0) {
            throw new BusinessException(
                "Stok {$product->name} tidak mencukupi (tersedia {$before}).",
                422,
                ['stock' => ["Stok {$product->name} tidak mencukupi."]],
            );
        }

        $product->stock = $after;
        $product->save();

        return $product->stockMovements()->create([
            'order_id' => $order?->id,
            'user_id' => $user?->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'note' => $note,
        ]);
    }

    /**
     * Restock / penyesuaian manual oleh admin.
     * - type "in"         : quantity > 0 ditambahkan ke stok
     * - type "adjustment" : stok di-set menjadi angka hasil stock opname
     */
    public function adjust(Product $product, StockMovementType $type, int $quantity, User $user, ?string $note): StockMovement
    {
        return DB::transaction(function () use ($product, $type, $quantity, $user, $note) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $delta = $type === StockMovementType::Adjustment
                ? $quantity - $locked->stock
                : $quantity;

            return $this->move($locked, $type, $delta, $user, null, $note);
        });
    }
}
