# KasirKita — Sistem POS Kasir (Laravel 13)

Project dasar: Laravel 13 + Laravel Sanctum + MySQL. Fitur ditambahkan bertahap oleh tim lewat branch masing-masing.

## Menjalankan di lokal

Kebutuhan: PHP ≥ 8.3 (disarankan 8.4), Composer ≥ 2.7, MySQL/MariaDB.

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
# buat database kosong "kasirkita" di MySQL dulu
php artisan migrate
php artisan serve             # http://127.0.0.1:8000
```

Cek API: buka http://127.0.0.1:8000/api/ping → `{"success":true,"message":"KasirKita API aktif"}`

## Pembagian tugas & branch

| Branch | Penanggung jawab | Isi |
| --- | --- | --- |
| `database` | Orang 1 | Migration, model & relasi, enum, service proses bisnis (pesanan & stok), factory, seeder |
| `api` | Orang 2 | `routes/api.php`, controller API, Form Request, API Resource, Policy, autentikasi Sanctum, format error |
| `frontend` | Orang 3 | View Blade, `public/assets` (CSS/JS), `routes/web.php`, feature test, panduan deploy |

Urutan merge ke `main`: `database` → `api` → `frontend`.

## Alur kerja Git

```bash
git clone https://github.com/<user>/pos-kasir.git
cd pos-kasir
git checkout -b database          # atau api / frontend
# ... kerjakan bagian sendiri ...
git add .
git commit -m "Tambah migration produk dan pesanan"
git push -u origin database
```

Lalu buat **Pull Request** ke `main` di GitHub. Sebelum mulai kerja, ambil perubahan terbaru: `git pull origin main`.

Jangan commit file `.env` dan folder `vendor/` (sudah diatur di `.gitignore`).
