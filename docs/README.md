# Library Management App — Dokumentasi

Backend (REST API) untuk aplikasi manajemen perpustakaan. Dibangun dengan **Laravel 12**, **PostgreSQL**, dan **Laravel Sanctum**.

Melayani seluruh alur perpustakaan: katalog & pencarian buku, keanggotaan member, peminjaman & pengembalian dengan denda otomatis, reservasi (hold) buku yang sedang habis, sampai laporan.

## Isi Dokumentasi

- [Dokumentasi API](API.md) — daftar lengkap semua endpoint v1, role & akses, contoh request/response, aturan bisnis, dan akun demo.
- [ERD](erd.md) — struktur database (tabel inti + tabel auth + relasi).
- [Backlog](BACKLOG.md) — daftar pekerjaan & status pengembangan backend.

## File Pendukung

- [Catatan API (Excel)](API_CATATAN.xlsx) — ringkasan fitur, cara pakai, daftar endpoint, dan aturan bisnis dalam format spreadsheet.
- [Collection Postman](library-api.postman.json) — import langsung ke Postman untuk mengetes API.

## Mulai Cepat

1. `composer install` lalu atur `.env` (koneksi PostgreSQL).
2. `php artisan migrate --seed`.
3. `php artisan serve` → Base URL `http://localhost:8000/api/v1`.
4. Login pakai akun demo: `admin@library.com` / `password` (admin) atau `demo.member@example.com` / `password` (member).
