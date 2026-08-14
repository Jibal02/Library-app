# Library Management App — Backend API

Backend (REST API) untuk aplikasi manajemen perpustakaan. Dibangun dengan **Laravel 12**, **PostgreSQL**, dan **Laravel Sanctum** untuk autentikasi token.

Melayani seluruh alur perpustakaan: katalog & pencarian buku, keanggotaan member, peminjaman & pengembalian dengan denda otomatis, reservasi (hold) buku yang sedang habis, sampai laporan.

## Fitur

1. Katalog & pencarian buku (title/author/isbn) — bisa diakses tanpa login.
2. Registrasi & login member — kartu member (`MBR-xxxx`) dibuat otomatis.
3. Peminjaman & pengembalian — denda keterlambatan otomatis **Rp 2.000/hari**, durasi pinjam **14 hari**.
4. Reservasi (hold) buku yang stoknya habis — antrean FIFO, maksimal 5 hold/member.
5. Kelola member (aktif / suspend) & kelola buku (CRUD + scan barcode ISBN).
6. Laporan pinjaman terlambat (overdue) + estimasi denda berjalan.
7. Laporan ringkasan denda & keterlambatan per member.
8. Keamanan: autentikasi token (Sanctum), role `admin` / `staff` / `member`, rate limiting login/register (5x/menit/email).

## Tech Stack

- Laravel 12 (PHP 8.2+)
- PostgreSQL
- Laravel Sanctum (autentikasi token)
- PHPUnit (pengujian)

## Setup Lokal

```bash
composer install
cp .env.example .env       # atur koneksi DB PostgreSQL & APP_KEY
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

Base URL API: `http://localhost/api/v1` (sesuaikan dengan port server / URL tunnel Cloudflare bila dipakai).

> Alternatif: `php artisan db:seed --class=DemoMemberSeeder` untuk membuat akun demo member beserta contoh loan (idempotent, bisa diulang).

## Akun Demo

| Role | Email | Password |
|---|---|---|
| Admin / Staff | `admin@library.com` | `password` (cek output seeder) |
| Member (dengan loan contoh) | `demo.member@example.com` | `password` |

## Role & Hak Akses

| Role | Akses |
|---|---|
| `member` | Katalog publik, detail buku, profil, reservasi (milik sendiri) |
| `staff` | Semua akses admin/staff + member |
| `admin` | Sama dengan staff |

## Ringkasan Endpoint (v1)

| Area | Endpoint utama |
|---|---|
| Auth & profil | `POST /register`, `POST /login`, `GET /user`, `GET /profile`, `GET /profile/loans`, `GET /profile/history` |
| Katalog | `GET /books`, `GET /books/{id}`, `GET /books/scan/{isbn}`, `GET /categories`, `GET /authors` |
| Reservasi | `POST /reservations`, `GET /reservations`, `DELETE /reservations/{id}` |
| Sirkulasi | `POST /loans/issue`, `POST /loans/{id}/return`, `GET /loans`, `GET /transactions` |
| Member | `GET /members`, `PATCH /members/{id}`, `PATCH /members/{id}/status`, `GET /members/{id}/history`, `DELETE /members/{id}` |
| Buku | `POST /books`, `PUT /books/{id}`, `DELETE /books/{id}` |
| Laporan | `GET /reports/overdue`, `GET /reports/member-penalty` |

## Dokumentasi

- [Dokumentasi API lengkap](docs/API.md)
- [Catatan API (Excel)](docs/API_CATATAN.xlsx)
- [Collection Postman](docs/library-api.postman.json)
- [ERD](docs/erd.md)
- [Backlog](docs/BACKLOG.md)

## Menjalankan Test

```bash
php artisan test
```
