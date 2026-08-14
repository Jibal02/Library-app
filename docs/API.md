# API Documentation — Library Management App (Backend)

> Dokumentasi lengkap semua endpoint API v1 (Laravel + Sanctum + PostgreSQL).
> Base URL: `http://localhost/api/v1` (atau URL tunnel Cloudflare yang aktif).

## Autentikasi

Semua endpoint (kecuali yang bertanda **Public**) butuh header:

```
Authorization: Bearer <access_token>
```

Dapet token dari `POST /login` (response `access_token`). Format respons error:

| Situasi | HTTP | Body |
|---|---|---|
| Belum login / token invalid | 401 | `{"message": "Unauthenticated"}` |
| Tidak punya role yang dibutuhkan | 403 | `{"message": "Forbidden"}` |
| Validasi gagal | 422 | `{"message": "...", "errors": {...}}` |
| Data tidak ditemukan | 404 | `{"message": "Data tidak ditemukan."}` |
| Terlalu banyak percobaan login/register (5/menit/email) | 429 | `{"message": "...", "retry_after": n}` |
| Error bisnis | 422 | `{"message": "..."}` |

## Role & akses

| Role | Hak akses |
|---|---|
| `member` | Katalog publik, detail buku, profile, reservasi (self) |
| `staff` | Semua admin/staff + `member` |
| `admin` | Sama dengan `staff` |

---

## 1. Auth

### POST `/register` — Public
Daftar member baru (otomatis dibuatkan kartu member `MBR-xxxx`).
```json
{
  "name": "Budi",
  "email": "budi@mail.com",
  "password": "rahasia123",
  "phone": "08123456789"
}
```
`201` → `{message, user: {id, name, email, phone, role, member: {member_code, status}}}`

### POST `/login` — Public
```json
{ "email": "budi@mail.com", "password": "rahasia123" }
```
`200` → `{message, access_token, user: {id, name, email, role}}`
- `401` password/email salah.
- `403` kalau kartu member `suspended`.

### GET `/user` — Login (semua role)
`200` → `{id, name, email, phone, role}`

---

## 2. Katalog (Public)

### GET `/books` — Public
Query opsional: `?search=` (alias `?q=`) cari title/author/isbn, `?author=`, `?category_id=`, `?page=`.
`200` → paginated `{data: [...], current_page, last_page, total, ...}` — tiap buku punya `available_copies`.

### GET `/books/{id}` — Login (semua role)
`200` → `{id, isbn, title, author, publisher, publication_year, total_copies, available_copies, category: {...}}`
`404` → `{"message": "Buku tidak ditemukan"}`

### GET `/categories` — Public
`200` → `[{id, name, slug}]`

### GET `/authors` — Public
`200` → `["Penulis A", "Penulis B"]`

---

## 3. Profil (Login, semua role)

### GET `/profile`
`200` → flat object. Role member tambahan `member_code`, `status`, `loans` (semua riwayat + `book`), `active_loans`, `total_loans`.

### GET `/profile/loans`
`200` → `{loans: [...aktif, with book], active_loans}`

### GET `/profile/history`
`200` → `{loans: [...semua, with book + fine_amount], total_loans}`

> **Soal denda di endpoint profil:** tiap loan (di `/profile`, `/profile/loans`, `/profile/history`) sekarang punya field `estimated_fine`.
> - Loan `returned` → `fine_amount` = denda final; `estimated_fine` sama nilainya.
> - Loan belum dikembalikan (`active`/`overdue`) → `fine_amount` = `0` (final dihitung saat return), **`estimated_fine`** = estimasi denda berjalan (2000/hari; `0` kalau belum telat).
> FE sebaiknya tampilkan: `returned` → `fine_amount`, selain itu → `estimated_fine`.

---

## 4. Reservasi / Hold buku (Login, semua role)

> Alur: hanya bisa hold kalau **stok habis** (`available_copies = 0`). Saat buku dikembalikan, hold `pending` **pertama (FIFO)** jadi `ready` (siap diambil staff). Staff yang terbitkan loan-nya lewat `POST /loans/issue`, otomatis hold jadi `fulfilled`. Member bisa batalin hold-nya sendiri.
> Status: `pending` → `ready` → `fulfilled` / `cancelled`. Maksimal **5 hold aktif** per member.

### POST `/reservations` — Login (member self, atau staff/admin atas nama member)
Body:
```json
{
  "book_id": 12,
  "member_id": 5          // wajib diisi oleh admin/staff; member self tidak perlu
}
```
- `201` → `{message, reservation: {id, member, book, reserved_at, status}}`
- `422` kalau: buku masih tersedia, member suspended, hold duplikat aktif, hold > 5, atau admin/staff lupa `member_id`.
- `403` kalau member coba reserve atas nama member lain.

### GET `/reservations` — Login
- **Admin/Staff**: semua reservasi. Filter: `?status=`, `?book_id=`, `?member_id=` (id kartu member, `members.id`). Paginated (12/halaman), urut `reserved_at` desc.
- **Member**: cuma reservasi miliknya sendiri.

### DELETE `/reservations/{id}` — Login
- Member: batalin punya sendiri. Admin/Staff: batalin siapa aja.
- `200` → `{message, reservation}` dengan `status: cancelled`.
- `422` kalau sudah `fulfilled`/`cancelled`. `403` kalau bukan punya sendiri.

---

## 5. Sirkulasi (Admin/Staff)

### POST `/loans/issue` — Admin/Staff
Terbitkan peminjaman (`due_date` = +14 hari), stok berkurang (transaction + lock).
```json
{ "member_id": 3, "book_id": 12 }
```
`member_id` = id kartu member (`members.id`).
- `201` → `{message, loan: {..., member, book}}`
- `422` kalau: member suspended, stok habis, member masih pinjam buku sama.
- Auto `fulfilled` hold `pending`/`ready` milik member tsb untuk buku tsb.

### POST `/loans/{id}/return` — Admin/Staff
- `200` → `{message, loan}` (hitung denda otomatis `2000/hari`).
- `422` kalau sudah dikembalikan (double-return).
- Auto ubah hold `pending` pertama (FIFO) buku ini jadi `ready`.

### GET `/loans` — Admin/Staff
Filter opsional: `?book_id=`, `?status=` (`active|returned|overdue`), `?member_id=` (`members.id`). Paginated 12, urut `borrowed_at` desc. Tiap item punya `estimated_fine` (sama seperti endpoint profil: `returned` → pakai `fine_amount`, belum balik → `estimated_fine`).

### GET `/transactions?date=YYYY-MM-DD` — Admin/Staff
`200` → `{date, issued: [...], returned: [...]}`. `422` kalau `date` wajib & format salah.

> **Catatan Transaksi Harian:** endpoint ini hanya menampilkan transaksi issue/return yang terjadi **tanggal `date` itu** (berdasarkan `borrowed_at` / `returned_at`). Data pinjaman lama (mis. seed demo) tidak akan muncul — kalau halaman "Transaksi Harian" kosong tapi `GET /loans` ada datanya, itu normal.

---

## 6. Member (Admin/Staff)

> `member_id` di endpoint member = **id user** (`users.id`) yang role-nya `member`.

### GET `/members` — Admin/Staff
Daftar user role member + kartu (`with member`). Search `?q=`. Paginated 12.

### PATCH `/members/{id}` — Admin/Staff
Edit name/email/phone (sinkron ke kartu member). Email unique (kecuali diri sendiri). `member_code` & `status` tidak bisa diedit di sini.

### PATCH `/members/{id}/status` — Admin/Staff
```json
{ "status": "active" | "suspended" }
```
`422` kalau id bukan user role member. Member suspended tidak bisa login (`403`) & tidak bisa pinjam/hold.

### GET `/members/{id}/history` — Admin/Staff
`200` → `{member, loans, active_loans, total_loans}`

### DELETE `/members/{id}` — Admin/Staff
`422` kalau masih ada pinjaman aktif **atau** hold aktif.

---

## 7. Buku (Admin/Staff)

### POST `/books` — Admin/Staff
```json
{
  "category_id": 1,
  "isbn": "9786020311001",
  "title": "Laravel in Action",
  "author": "John Doe",
  "publisher": "Publisher X",
  "publication_year": 2024,
  "total_copies": 3
}
```
`201` → `{message, book}`. `available_copies` = `total_copies`. ISBN unique.

### PUT `/books/{id}` — Admin/Staff
Body sama seperti POST. `available_copies` ikut naik/turun sebesar selisih `total_copies` (min 0). ISBN unique (kecuali diri sendiri).

### GET `/books/scan/{isbn}` — Admin/Staff
Cari by ISBN exact match (scan barcode). `404` → `{"message": "Buku dengan ISBN tersebut tidak ditemukan."}`

### DELETE `/books/{id}` — Admin/Staff
`422` kalau masih ada pinjaman **atau** hold aktif.

---

## 8. Laporan (Admin/Staff)

### GET `/reports/overdue` — Admin/Staff
`200` → `{count, overdue_loans: [...]}` — loan belum dikembalikan & lewat due_date, dengan `estimated_fine`.

---

## Aturan bisnis penting

- **Denda**: Rp 2.000/hari keterlambatan, durasi pinjam 14 hari (`app/Services/FineCalculator.php`).
- **Status loan**: `active` → `returned` | `overdue` (disinkronkan tiap hari oleh command `loans:mark-overdue`).
- **Reservasi**: cuma saat stok habis, max 5 hold aktif/member, antrean FIFO.
- **Stock**: decrement saat issue, increment saat return, dibungkus DB transaction + `lockForUpdate` (anti oversell saat 2 request bareng).

## Data demo untuk testing denda

> Dibuat oleh `php artisan db:seed --class=DemoMemberSeeder` (bisa diulang, idempotent).

**Akun member demo** (login buat tes alur member):

| Field | Nilai |
|---|---|
| email | `demo.member@example.com` |
| password | `password` |
| user_id | cek output seeder |
| member_id (kartu) | cek output seeder |

**Loan milik akun demo** (tanggal relatif hari ini `H`):

| Loan | borrowed_at | due_date | returned_at | status | fine_amount |
|---|---|---|---|---|---|
| Telat balik | H-20 | H-6 | H-1 | `returned` | **10.000** (5 hari telat) |
| Overdue (belum balik) | H-19 | H-5 | null | `overdue` | 0 → saat return kehitung **10.000** |
| Aktif normal | H-3 | H+11 | null | `active` | 0 |

**Skenario tes yang bisa dicoba:**
1. Login `demo.member@example.com` / `password` → `GET /profile` atau `/profile/loans` → loan `overdue` muncul dengan `fine_amount` 0 dan **`estimated_fine` 10.000**; loan `returned` (di `/profile/history`) punya `fine_amount` 10.000.
2. Admin/staff → `GET /reports/overdue` → loan `overdue` muncul dengan `estimated_fine` 10.000.
3. Admin/staff → `POST /loans/{id}/return` untuk loan overdue → response `fine_amount` = 10.000.

> Catatan: akun demo muncul di daftar `GET /members` (nama "Demo Member"). Setelah testing selesai, return dulu loan aktif/overdue-nya baru bisa dihapus via `DELETE /members/{id}`.
