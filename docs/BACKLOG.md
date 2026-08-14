# BACKLOG - Backend Library Management App

> Daftar tugas backend (Laravel + PostgreSQL). Centang item saat selesai.
> Setiap fitur baru/ubah struktur, update juga `docs/erd.md`.

## Legend

- [ ] Belum dikerjakan
- [x] Selesai

## Maintenance / Cleanup

- [x] **Bersihkan migrasi duplikat `create_users_table`** — hapus `2026_07_30_024014_create_users_table.php` (duplikat create users) & `2026_07_30_023003_add_phone_to_users_table.php` (phone sudah ada di migration default). Sekarang cuma 1 source: `0001_01_01_000000_create_users_table.php` (sudah berisi `phone`). Tabel `migrations` di Postgres di-sync (hapus record `024014`, tandai `0001` sebagai ran). `php artisan migrate` = "Nothing to migrate".
- [x] **Fix `app/Models/Member.php`** — class masih `Category` (copy-paste), diganti jadi `Member`.
- [x] **Fix `create_member_table` down()** — drop `member` → `members` (sesuai nama tabel aslinya).
- [x] **Fix `app/Models/Loan.php`** — nama file huruf kecil (`loan.php` → `Loan.php`), syntax error `namespace App\Models;{}`, nama kolom `borrow_at/return_at` → `borrowed_at/returned_at`, relasi `finepayment()` → `finePayment()`.

## Auth (Sanctum)

- [x] Register (POST `/api/register`)
- [x] Login (POST `/api/login`) — balikin token + user (id, name, email, role)
- [x] Ambil user login (GET `/api/user`, middleware `auth:sanctum`)

## Infra & Struktur

- [x] **#1 Migrasi `add_role_to_users_table`** — kolom `role` (enum admin/staff/member, default member) sudah dibuat. Catatan: kolom ini sebelumnya ditambahkan MANUAL di DB (bukan lewat migration), jadi data lama di-normalisasi ke huruf kecil & default `member`.
- [x] **#2 Middleware role** — `app/Http/Middleware/CheckRole.php`, alias `role` di `bootstrap/app.php`. Pemakaian: `role:admin,staff`. User tidak login / role tidak cocok → `403 Forbidden`.
- [x] **#3 Prefix route `/api/v1`** — semua route auth pindah ke `/api/v1/...` (register, login, user). Frontend harus update base URL.
- [x] Model `Loan`
- [x] Model `FinePayment`
- [x] Seeder data dummy — pakai model factory (Category/Book/Member/LoanFactory) + `DatabaseSeeder`. Hasil: 8 kategori, 25 buku, 10 member, 20 loan (status active/returned/overdue + denda terhitung). Data user `agus`/`agas` tetap aman (pakai `db:seed`, bukan `migrate:fresh`).

## API Contract (§3.4)

- [x] **GET `/api/v1/books`** — Public (tanpa token). Search katalog (`?q=` di title/author/isbn, `ilike`) + filter (`?category_id=`) + pagination + availability (`available_copies`).
- [x] **`GET /api/v1/categories`** — Public. Daftar semua kategori (`id`, `name`, `slug`) urut nama. Response raw array (tanpa wrapper `success`), konsisten dengan endpoint lain.
- [x] **`GET /api/v1/authors`** — Public. Daftar penulis unik dari tabel books (raw array string, urut alfabet).
- [x] **Filter books `?search=` & `?author=`** — `?search=` alias dari `?q=` (cari title/author/isbn, ilike); `?author=` partial match kolom author (ilike). `?q=` & `?category_id=` tetap jalan (backward compatible).
- [x] **GET `/api/v1/books/{id}`** — Detail buku (book + category), wajib login (`auth:sanctum`, semua role termasuk member), `404 {"message":"Buku tidak ditemukan"}` kalau id gak ada.
- [x] **POST `/api/v1/books`** — Admin/Staff. Tambah buku baru; `available_copies = total_copies` saat dibuat; validasi isbn unik.
- [x] **POST `/api/v1/loans/issue`** — Admin/Staff. Terbitkan peminjaman (due_date = +14 hari), `available_copies` berkurang (DB transaction). Cek: member aktif, stok > 0, member gak pinjam buku yang sama.
- [x] **POST `/api/v1/loans/{id}/return`** — Admin/Staff. Tandai kembali, hitung denda via FineCalculator, `available_copies` bertambah (DB transaction). Return dobel → 422.
- [x] **GET `/api/v1/members/{id}/history`** — Admin/Staff. Data member + riwayat peminjaman + loan aktif + total loan.
- [x] **GET `/api/v1/members`** — Admin/Staff. Daftar user dengan role `member` (search `?q=` name/email/phone) + pagination (12/halaman). Catatan: berbeda dari tabel `members` (peminjam, dipakai `loans.member_id`) — yang dimaksud "member" di LMS = user `users` dengan role `member`.
- [x] **GET `/api/v1/reports/overdue`** — Admin/Staff. List loan belum dikembalikan & lewat due_date + estimasi denda.
- Catatan: keputusan akses — admin boleh akses semua endpoint yang di spec "Staff" (semua pakai `role:admin,staff`).

## Service / Logika Bisnis (Week 2)

- [x] **Fine calculator service** — `app/Services/FineCalculator.php`. Denda `2000/hari`, durasi pinjam `14 hari` (constant). `calculate()` saat return, `estimate()` untuk laporan. Catatan: `diffInDays()` di Carbon 3 balikin nilai bertanda (bisa negatif) → dibungkus `abs()`.
- [x] **Stock counter sync** — decrement saat issue, increment saat return (dibungkus DB transaction, kalau satu gagal semua batal).
- [x] **Guard double-return** — `LoanController::return`: kalau loan sudah `returned_at` terisi **atau** `status` = `returned` → `422 {"message":"Buku ini sudah dikembalikan sebelumnya."}` (anti double-return).
- [x] **Update status loan otomatis** — command `loans:mark-overdue` (`app/Console/Commands/MarkOverdueLoans.php`): loan yang `returned_at` null + status `active` + `due_date < hari ini` → `status = overdue` (bulk update). Dijadwalkan tiap hari (`->daily()` di `bootstrap/app.php` via `withSchedule`). Denda tetap dihitung saat return / di laporan; command ini hanya menyinkronkan kolom `status` biar akurat buat frontend.

## Week 3 - Edge Cases & Polish

- [x] **Logika hold/reservation stok buku** — tabel `book_reservations` + model `BookReservation` + `ReservationController` (`POST/GET/DELETE /api/v1/reservations`). Aturan: hold cuma boleh kalau `available_copies = 0`, max **5 hold aktif/member**, antrean FIFO (`pending` → `ready` → `fulfilled`/`cancelled`). Saat return, hold `pending` pertama jadi `ready`; saat issue, hold `pending`/`ready` milik member jadi `fulfilled`. Admin/staff bisa reserve atas nama member (`member_id` di body = `users.id`); member cuma bisa buat dirinya sendiri & batalin punya sendiri (selain itu 403). Update: `docs/erd.md`.
- [x] **Penanganan edge case: member suspended, buku habis, loan kembar** — cek `member.status`, `available_copies > 0`, dan loan aktif kembar tetap dijaga di `LoanController::issue`. Tambahan hardening: decrement stok dibungkus **`lockForUpdate()`** dalam DB transaction (anti oversell kalau 2 request bareng). Delete buku/member diblokir 422 kalau masih ada hold aktif (`pending`/`ready`).
- [x] **Validasi & error handling API (response JSON konsisten)** — `bootstrap/app.php` tambah render `ModelNotFoundException` → `404 {"message":"Data tidak ditemukan."}` untuk `api/*` (sebelumnya pesan default Laravel bahasa Inggris). Error 401/403/422/429 sudah JSON; format: business error `{message}`, validasi `{message, errors}`.
- [x] **Dokumentasi API (README / Postman collection)** — `docs/API.md` (semua endpoint, body, contoh response, aturan bisnis) + `docs/library-api.postman.json` (collection siap import, auto-set token pas login).
- [x] **Seeder demo denda** (`DemoMemberSeeder`) — akun `demo.member@example.com` / `password` + 3 loan deterministic (returned fine 10.000, overdue estimated 10.000, aktif normal) biar tim FE bisa tes fitur denda end-to-end. Jalanin: `php artisan db:seed --class=DemoMemberSeeder` (idempotent). Detail di `docs/API.md` → "Data demo untuk testing denda".
- [x] **`estimated_fine` di semua endpoint loan member/staff** — `ProfileController` (`/profile`, `/profile/loans`, `/profile/history`) + `LoanController::index` (`GET /loans`) sekarang tiap loan mengirim `estimated_fine` via `FineCalculator::estimate()` (untuk loan belum dikembalikan; loan `returned` nilainya = `fine_amount`). Fix keluhan FE "profile member tampil 0": loan `active`/`overdue` yang `fine_amount`-nya 0 by design kini punya estimasi denda berjalan. Test: `tests/Feature/ProfileEstimateTest.php` (3 kasus).

## Akses Tim FE via Cloudflare Tunnel & Rate Limiting

- [x] **Cloudflare Quick Tunnel buat akses FE jarak jauh** — `cloudflared tunnel --url http://127.0.0.1:8000` → URL publik `https://xxx.trycloudflare.com` (ganti tiap restart). Instal: `winget install Cloudflare.cloudflared` (exe di `C:\Program Files (x86)\cloudflared\`, mungkin butuh buka terminal baru biar PATH ke-detect). Catatan penting: **cuma API yang di-expose, DB tetap lokal** (jangan pernah expose DB). Tunnel & `php artisan serve` harus jalan bareng; kalau salah satu mati, URL mati. Semua traffic masuk ke Laravel sebagai `127.0.0.1` (karena lewat tunnel).
- [x] **Rate limiting login/register** — `throttle:auth` (5 percobaan/menit). Named limiter di `app/Providers/AppServiceProvider.php` (`RateLimiter::for('auth', ...)`), key-nya **email** (bukan IP — karena tunnel share 1 IP, kalau key IP semua user saling kunci). Dipakai di `routes/api.php`: `POST /register` & `POST /login`.
- [x] **Response 429 jadi JSON** — `bootstrap/app.php`: render `ThrottleRequestsException` → `{"message": "...", "retry_after": n}` untuk route `api/*` (sebelumnya HTML). Pakai kelas `Illuminate\Http\Exceptions\ThrottleRequestsException`, bukan `Symfony\Component\HttpKernel\Exception\ThrottleRequestsException`.
- [x] **Fix `APP_URL` di `.env`** — `http://192.168.1.31/api` → `http://localhost` (suffix `/api` di APP_URL itu salah, bisa bikin URL generasi Laravel ngaco).
- [x] **Verifikasi auth end-to-end via tunnel** — `POST /login` → dapet `access_token` → `GET /api/v1/user` dengan `Authorization: Bearer <token>` → 200. Backend dinyatakan sehat.
- [ ] **Bug "login kepental ke login" (di sisi FE, bukan backend)** — penyebab paling mungkin: FE baca key `res.data.token` padahal backend kirim `access_token`; FE gak kirim header `Authorization: Bearer` di semua method (termasuk GET); token ke-split di `|`; atau FE campur base URL lokal. Checklist udah dikasih ke tim FE.

## Refactor: Sambungkan users ↔ members (kartu member)

- [x] **Migration `add_user_id_to_members_table`** — tambah kolom `user_id` (nullable, unique) di `members` + FK `users.id` (cascade). Sebelumnya `users` & `members` dua tabel terpisah tanpa kaitan.
- [x] **Relasi model** — `User::member()` (HasOne) & `Member::user()` (BelongsTo).
- [x] **Register auto-buat kartu member** — `AuthController::register` sekarang dalam DB transaction: create user (role member) → auto-create `members` record (`MBR-xxxx` unik, status `active`, data dari user). Response register include `member_code`. **Fiks bug: user yang baru daftar sekarang punya kartu → bisa dipakai di `loans/issue`.**
- [x] **Backfill member lama** — user role `member` yang sudah ada (agas, supra, test) dibuatkan kartu member-nya.
- [x] **`GET /api/v1/members`** — tetap daftar `users` role=member, sekarang `->with('member')` → response tiap member berisi kartu (member_code + status).
- [x] **`PATCH /api/v1/members/{id}/status`** — Admin/Staff. Body `{"status":"active|suspended"}`. Validasi: id harus user dengan role `member` (bukan → 422), update `members.status` (kalau kartu belum ada, dibuatkan otomatis).
- [x] **`PATCH /api/v1/members/{id}`** — Admin/Staff. Edit data member (name/email/phone), email unique (kecuali diri sendiri), sinkron ke kartu member. `member_code` & `status` tidak lewat endpoint ini.
- [x] **`PUT /api/v1/books/{id}`** — Admin/Staff. Edit buku. Validasi sama seperti store (isbn unique kecuali diri sendiri). Sinkron stok: `available_copies` ikut naik/turun sebesar selisih `total_copies` (min 0).
- [x] **`GET /api/v1/books/scan/{isbn}`** — Admin/Staff. Cari buku by ISBN (exact match) untuk scan barcode. Response = detail buku + category + available_copies (format sama `GET /books/{id}`), 404 `{"message":"Buku dengan ISBN tersebut tidak ditemukan."}` kalau tidak ketemu. Dipakai FE buat alur pinjam/balikin.
- [x] **`DELETE /api/v1/books/{id}`** — Admin/Staff. Hapus buku. Blokir 422 kalau masih ada loan belum dikembalikan (`returned_at` null). Kalau aman, buku + loan-nya yang sudah dikembalikan ikut terhapus (cascade).
- [x] **`DELETE /api/v1/members/{id}`** — Admin/Staff. Hapus user role `member` (bukan member → 422). Blokir 422 kalau kartunya masih punya pinjaman aktif. Kalau aman, user + kartu member + loan-nya ikut terhapus (cascade).
- [x] **Hapus duplikat route `GET /api/v1/books/{id}`** — sebelumnya terdaftar 2x di `routes/api.php` (baris 31 & 33), sekarang 1x.
- [x] **Login cek suspend** — `AuthController::login`: kalau user role `member` dan kartunya `suspended` → `403 {"message":"Akun kamu kena suspend. Hubungi admin."}`. Berlaku cuma untuk role `member`; admin/staff gak terpengaruh.
- [x] **`GET /api/v1/profile`** — user yang login (semua role). Response **flat**: `{id, name, email, phone, role}`; kalau role member tambah `member_code`, `status`, `loans` (SEMUA riwayat pinjaman, `with('book')`, urut `borrowed_at` desc), `active_loans` (count yang belum dikembalikan), `total_loans`. Admin/staff dapat `loans: []`, `active_loans: 0`. Dipakai FE buat halaman Profile & Peminjaman Saya.
- [x] **`GET /api/v1/profile/loans`** — user yang login (semua role; admin/staff tanpa kartu → list kosong). Loan aktif (belum dikembalikan) milik member itu, `with('book')`, urut `borrowed_at` desc. Response `{loans, active_loans}`.
- [x] **`GET /api/v1/profile/history`** — user yang login. Semua loan (aktif/returned/overdue) milik member itu, `with('book')` + `fine_amount`, urut `borrowed_at` desc. Response `{loans, total_loans}`. Admin buat lihat riwayat member lain pakai `GET /members/{id}/history`.
- [x] **`GET /api/v1/transactions?date=YYYY-MM-DD`** — Admin/Staff. Transaksi sirkulasi harian: response `{date, issued, returned}` — `issued` = loan yang `borrowed_at` di tanggal itu **dan status `active`**, `returned` = loan yang `returned_at` di tanggal itu **dan status `returned`**; masing-masing `with(['member','book'])`. Filter status dijamin tiap loan muncul cuma di satu list (tidak ada ID duplikat; issue selalu status `active`, return selalu `returned`). Validasi `date` wajib & format tanggal (salah → 422).
- [x] **`GET /api/v1/loans?book_id=&status=&member_id=`** — Admin/Staff. Daftar loan difilter (semua opsional), `with(['member','book'])`, urut `borrowed_at` desc, paginate 12. Status valid: `active|returned|overdue`. Dipakai FE buat CirculationPage (mis. cek buku id=25 lagi status active dipinjam siapa).
- [ ] **Tunggu refactor (backlog potensial)** — data lama tabel `members` yang dummy (seeder) masih ada & bisa kebaca di `loans.member_id`. Kalau mau bersihin, hapus record members yang `user_id`-nya null (tapi hati-hati: `loans.member_id` referensi ke situ). Juga opsi nanti: ganti `loans.member_id` langsung ke `users.id` (gabung total).




