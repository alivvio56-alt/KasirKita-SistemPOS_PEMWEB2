# KasirKita
> Sistem POS Kasir Kedai Kopi & Roti — pesanan, stok, dan laporan dalam satu aplikasi
>
> https://c2.athafa.cloud
> 
> admin@kasirkita.test / kasir@kasirkita.test
> 
> password123

---

## 📌 Informasi Kelompok
- **Nomor Kelompok:** 2
- **Shift Praktikum:** C

---

## 👥 Anggota Kelompok

| No | Nama Lengkap | NIM | Shift Awal | Shift Akhir | Jobdesk / Kontribusi | Link Video Penjelasan |
|---|---|---|---|---|---|---|
| 1 | David Ananta Nugraha | H1H024025 | D | C | Frontend | https://youtu.be/Hv7Rz5ojF9Q?si=iF94uB0O0rJcqGpL |
| 2 | Yogi Ferdiansyah Amta Miluloh | H1H024027 | C | C | Database, Model, Bussiness Process | https://youtu.be/mrpZbAzJqTQ?si=Wh8TwBO7yy5GNmEu |
| 3 | Ardhis Alivio Rajendra | H1H024031 | A | C | REST API, Auth & Otorisasi | https://youtu.be/Pqt7bgp8OfU?si=RKZeLA60FRrhtl6d |

---

## 📖 Deskripsi Aplikasi
**KasirKita** adalah aplikasi **POS (Point of Sale)** untuk kedai kopi & toko roti skala UMKM. Backend berupa **RESTful API** (Bearer token Sanctum), sedangkan frontend memakai **Blade + HTML/CSS/JavaScript** yang mengonsumsi API tersebut.

**Target pengguna:** pemilik (Admin) dan kasir kedai kopi & roti, terutama yang berada di sekitar kampus.

**Problem yang diselesaikan** — kedai kecil umumnya mencatat pesanan di kertas/chat WhatsApp, sehingga:
1. **Stok tidak sinkron** — roti/kopi habis tetapi masih dijanjikan ke pelanggan, atau kasir lupa mengurangi stok.
2. **Status pesanan tidak jelas** — dapur tidak tahu pesanan mana yang sedang dibuat atau sudah siap diambil.
3. **Preorder kue terlupa** — pesanan kue ulang tahun/bolu loyang untuk H+1 sering tercecer.
4. **Tidak ada riwayat** — pemilik tidak tahu omzet harian, produk terlaris, maupun pelanggan langganan.
5. **Rawan penyalahgunaan** — kasir bisa membatalkan transaksi yang sudah dibayar tanpa jejak.

KasirKita menjawabnya dengan alur pesanan berstatus, pengecekan & pemotongan stok otomatis, kartu stok yang tercatat, pembagian hak akses Admin/Kasir, serta laporan penjualan.

---

## ⚙️ Penjelasan Teknis

### 1. Teknologi (Tech Stack)
- **Backend:** Laravel 13 (PHP ≥ 8.4, PHP 8.3 juga jalan)
- **Frontend:** Blade + HTML/CSS/JavaScript (CSS/JS sudah jadi di `public/assets`, **Node.js tidak diperlukan**)
- **Database:** MySQL / MariaDB
- **Library / Package:** Laravel Sanctum (autentikasi token), Policy & Gate (otorisasi), Form Request (validasi), API Resource (output JSON), koleksi Postman/Bruno

### 2. Fitur Utama & Modul
- **Autentikasi & Otorisasi:** register, login, logout dengan token Sanctum; role **Admin** & **Kasir** lewat Policy (`app/Policies`) dan Gate `admin`; middleware `EnsureUserIsActive` (akun nonaktif langsung kehilangan semua token); login dibatasi 10x/menit/IP.
- **Pesanan:** catat pesanan (makan di tempat / bawa pulang / preorder), diskon, catatan, ubah selama masih *pending*, alur status, pembayaran tunai/QRIS/transfer + kembalian, pembatalan dengan alasan, cetak struk.
- **Inventori:** stok dicek saat pesanan dicatat & dicek ulang (dengan *row lock*) saat diproses, stok otomatis berkurang dan kembali jika dibatalkan, restock & stock opname, kartu stok lengkap, peringatan stok menipis.
- **Pelanggan:** data pelanggan, riwayat transaksi, total belanja.
- **Laporan (Admin):** pendapatan harian, produk terlaris, metode pembayaran, jumlah pembatalan.
- **Pengguna (Admin):** kelola role & aktif/nonaktif akun.
- **Frontend:** login/daftar, dashboard antrian, layar kasir, daftar pesanan + detail, produk, kategori, pelanggan, kartu stok, pengguna, laporan — responsif desktop & mobile, dengan notifikasi sukses/error.

### 3. Skema Data Singkat
- `users` (1 : N) `orders`
- `users` (1 : N) `stock_movements`
- `categories` (1 : N) `products`
- `customers` (1 : N) `orders`
- `orders` (1 : N) `order_items`
- `orders` (M : N) `products` (pivot `order_items`)
- `products` (1 : N) `stock_movements`
- `orders` (1 : N) `stock_movements`

> Produk memakai **SoftDeletes** agar riwayat transaksi tetap utuh. ERD lengkap ada di bagian *Dokumentasi Tambahan*.

---

## 🚀 Panduan Instalasi Lokal

Kebutuhan: PHP ≥ 8.4 (8.3 juga jalan), Composer ≥ 2.7, MySQL/MariaDB, ekstensi PHP `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`/`intl` (disarankan).

```bash
# Clone repository
git clone <URL_REPOSITORY>
cd <NAMA_FOLDER>

# Install dependensi PHP (Node.js tidak diperlukan)
composer install

# Konfigurasi Environment
cp .env.example .env
php artisan key:generate

# Isi DB_DATABASE, DB_USERNAME, DB_PASSWORD di file .env, lalu migrasi & seed
php artisan migrate --seed

# Jalankan development server (buka http://127.0.0.1:8000)
php artisan serve

# (Opsional) jalankan feature test
php artisan test
```

Akun demo (password **`password123`**): `admin@kasirkita.test` (Admin) · `kasir@kasirkita.test` (Kasir) · `kasir2@kasirkita.test` (Kasir).

---
