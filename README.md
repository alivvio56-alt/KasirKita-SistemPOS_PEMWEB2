# Database, Model & Proses Bisnis

Bagian ini menjelaskan fondasi data KasirKita dan aturan alur pesanan yang berjalan di belakang API.

## Hasil yang dikerjakan

- **Struktur database**: 7 tabel utama (users, categories, products, customers, orders, order_items, stock_movements) ditambah tabel pendukung (cache, jobs, personal_access_tokens untuk Sanctum), dibuat lewat migration.
- **Model & relasi Eloquent**: hasMany/belongsTo antar tabel, serta belongsToMany antara Order dan Product lewat pivot order_items. Produk memakai SoftDeletes supaya riwayat transaksi tetap utuh.
- **Enum**: Role, OrderType, OrderStatus, PaymentStatus, PaymentMethod, StockMovementType. OrderStatus memuat aturan transisi status (state machine).
- **Service proses bisnis**: OrderService (catat, ubah, ganti status, proses, bayar, batal) dan StockService (mutasi stok dan kartu stok).
- **Laporan**: DashboardController dengan query agregat untuk antrian, omzet harian, produk terlaris, dan metode pembayaran.
- **Data demo**: factory dan seeder yang membuat akun demo serta transaksi 7 hari lewat OrderService yang asli.

## Keputusan desain

| Keputusan | Alasan |
|---|---|
| order_items menyimpan product_name dan price sendiri (snapshot) | Riwayat pesanan tidak berubah walau harga atau nama produk diubah |
| Produk di-soft delete | Pesanan lama tetap bisa menampilkan produknya |
| Stok dipotong saat pesanan diproses, bukan saat dicatat | Pesanan yang batal sebelum diproses tidak mengganggu stok |
| Preorder tidak dicek stoknya di awal | Stok baru dicek dan dipotong saat produksi dimulai |
| Proses stok memakai DB::transaction dan lockForUpdate | Dua kasir yang memproses produk sama bersamaan tidak membuat stok minus |
| Setiap mutasi stok mencatat stok sebelum dan sesudah | Kartu stok bisa diaudit |

## Alur status pesanan

| Dari | Boleh ke |
|---|---|
| pending | processing, cancelled |
| processing | ready, cancelled |
| ready | completed (wajib lunas), cancelled |
| completed, cancelled | final |

Pembatalan mengembalikan stok otomatis dan menandai pembayaran sebagai refunded.

## Cara mencoba

1. Buat database kosong, lalu atur DB_* di file .env.
2. Jalankan `php artisan migrate:fresh --seed`.
3. Jalankan `php artisan tinker`, lalu coba `App\Models\Order::with('items')->first()`.

Hasil seeder: 30 pesanan demo beserta itemnya, dan akun demo admin@kasirkita.test dan kasir@kasirkita.test (password: password123).
