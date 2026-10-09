<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(OrderService $orders, StockService $stock): void
    {
        // --- Pengguna -------------------------------------------------------
        $admin = User::factory()->admin()->create([
            'name' => 'Pemilik Kedai',
            'email' => 'admin@kasirkita.test',
            'password' => 'password123',
        ]);
        $kasir = User::factory()->create([
            'name' => 'Rina Kasir',
            'email' => 'kasir@kasirkita.test',
            'password' => 'password123',
        ]);
        User::factory()->create([
            'name' => 'Budi Kasir',
            'email' => 'kasir2@kasirkita.test',
            'password' => 'password123',
        ]);

        // --- Katalog --------------------------------------------------------
        $catalog = [
            'Kopi' => [
                ['KOP-001', 'Es Kopi Susu Gula Aren', 18000, 'gelas'],
                ['KOP-002', 'Americano', 15000, 'gelas'],
                ['KOP-003', 'Cappuccino', 22000, 'gelas'],
                ['KOP-004', 'Kopi Tubruk', 10000, 'gelas'],
            ],
            'Non-Kopi' => [
                ['NKP-001', 'Matcha Latte', 22000, 'gelas'],
                ['NKP-002', 'Coklat Panas', 18000, 'gelas'],
                ['NKP-003', 'Es Teh Lemon', 10000, 'gelas'],
            ],
            'Roti & Pastry' => [
                ['ROT-001', 'Croissant Butter', 15000, 'pcs'],
                ['ROT-002', 'Roti Sobek Coklat', 20000, 'pcs'],
                ['ROT-003', 'Donat Gula', 6000, 'pcs'],
                ['ROT-004', 'Pain au Chocolat', 18000, 'pcs'],
            ],
            'Kue Pesanan' => [
                ['KUE-001', 'Bolu Pandan (loyang)', 85000, 'loyang'],
                ['KUE-002', 'Brownies Panggang (loyang)', 95000, 'loyang'],
                ['KUE-003', 'Kue Ulang Tahun 20cm', 250000, 'pcs'],
            ],
            'Snack' => [
                ['SNK-001', 'Kentang Goreng', 15000, 'porsi'],
                ['SNK-002', 'Pisang Goreng Keju', 14000, 'porsi'],
            ],
        ];

        $products = collect();
        foreach ($catalog as $categoryName => $items) {
            $category = Category::create([
                'name' => $categoryName,
                'description' => "Menu {$categoryName}",
            ]);
            foreach ($items as [$sku, $name, $price, $unit]) {
                $isCake = $categoryName === 'Kue Pesanan';
                $product = Product::create([
                    'category_id' => $category->id,
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $price,
                    'stock' => 0,
                    'min_stock' => $isCake ? 1 : 10,
                    'unit' => $unit,
                    'description' => $isCake ? 'Dibuat sesuai pesanan (preorder H-1).' : null,
                ]);
                $stock->move($product, StockMovementType::In, $isCake ? 3 : rand(40, 80), $admin, null, 'Stok awal');
                $products->push($product->fresh());
            }
        }
        // satu produk sengaja menipis untuk demo peringatan stok
        $stock->adjust($products->firstWhere('sku', 'ROT-004'), StockMovementType::Adjustment, 4, $admin, 'Stock opname');

        // --- Pelanggan ------------------------------------------------------
        $customers = Customer::factory()->count(12)->create();

        // --- Riwayat transaksi 7 hari terakhir (melalui alur bisnis asli) ---
        $sellable = $products
            ->where('category_id', '!=', Category::where('name', 'Kue Pesanan')->value('id'))
            ->where('sku', '!=', 'ROT-004')
            ->values();
        $base = now()->startOfDay();
        for ($day = 6; $day >= 0; $day--) {
            foreach (range(1, rand(3, 6)) as $n) {
                $hour = $day === 0 ? rand(7, max(7, (int) now()->format('G'))) : rand(8, 19);
                Carbon::setTestNow($base->copy()->subDays($day)->setTime($hour, rand(0, 59)));

                $picked = $sellable->random(rand(1, 3));
                $order = $orders->create([
                    'customer_id' => rand(0, 1) ? $customers->random()->id : null,
                    'type' => rand(0, 1) ? 'dine_in' : 'take_away',
                    'items' => $picked->map(fn ($p) => ['product_id' => $p->id, 'quantity' => rand(1, 2)])->all(),
                ], rand(0, 1) ? $kasir : $admin);

                $order = $orders->changeStatus($order, OrderStatus::Processing, $kasir);
                $order = $orders->changeStatus($order, OrderStatus::Ready, $kasir);
                $method = collect(PaymentMethod::cases())->random();
                $pay = $method === PaymentMethod::Cash ? ceil($order->total / 10000) * 10000 : (float) $order->total;
                $order = $orders->pay($order, $method, $pay);
                $orders->changeStatus($order, OrderStatus::Completed, $kasir);
            }
        }
        Carbon::setTestNow();

        // --- Pesanan aktif untuk demo antrian -------------------------------
        $orders->create([
            'type' => 'dine_in',
            'items' => [['product_id' => $products->firstWhere('sku', 'KOP-001')->id, 'quantity' => 2]],
            'notes' => 'Meja 3, less sugar',
        ], $kasir);

        $proc = $orders->create([
            'customer_id' => $customers[0]->id,
            'type' => 'take_away',
            'items' => [
                ['product_id' => $products->firstWhere('sku', 'ROT-001')->id, 'quantity' => 3],
                ['product_id' => $products->firstWhere('sku', 'NKP-001')->id, 'quantity' => 1],
            ],
        ], $kasir);
        $orders->changeStatus($proc, OrderStatus::Processing, $kasir);

        $orders->create([
            'customer_id' => $customers[1]->id,
            'type' => 'preorder',
            'pickup_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'items' => [['product_id' => $products->firstWhere('sku', 'KUE-003')->id, 'quantity' => 1]],
            'notes' => 'Tulisan: Happy Birthday Sari',
        ], $kasir);

        $this->command?->info('Akun demo -> admin@kasirkita.test / kasir@kasirkita.test (password: password123)');
    }
}
