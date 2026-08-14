# ERD - Library Management App

> Diagram di bawah mengikuti **Database Schema (§3.3)** dari spesifikasi — 5 tabel inti, kolom persis seperti spec.
> Buka di VSCode lalu tekan `Ctrl+Shift+V` untuk lihat diagram (Mermaid sudah support bawaan VSCode).
> Semua perubahan tabel WAJIB diupdate di file ini dulu, baru migration.

## Tabel inti (sesuai §3.3)

```mermaid
erDiagram
    CATEGORIES {
        bigint id PK
        string name
        string slug UK
    }

    BOOKS {
        bigint id PK
        bigint category_id FK
        string isbn UK
        string title
        string author
        string publisher
        integer publication_year
        integer total_copies
        integer available_copies
    }

    MEMBERS {
        bigint id PK
        string member_code UK
        string name
        string email UK
        string phone
        string status "enum(active, suspended) default active"
    }

    LOANS {
        bigint id PK
        bigint member_id FK
        bigint book_id FK
        date borrowed_at
        date due_date
        date returned_at "nullable"
        decimal fine_amount "10,2 default 0"
        string status "enum(active, returned, overdue) default active"
    }

    FINE_PAYMENTS {
        bigint id PK
        bigint loan_id FK
        decimal amount_paid "10,2"
        date paid_at
        text notes "nullable"
    }

    BOOK_RESERVATIONS {
        bigint id PK
        bigint member_id FK
        bigint book_id FK
        date reserved_at
        string status "enum(pending, ready, fulfilled, cancelled) default pending"
    }

    CATEGORIES ||--o{ BOOKS : "has"
    BOOKS ||--o{ LOANS : "loaned in"
    MEMBERS ||--o{ LOANS : "borrows"
    LOANS ||--o| FINE_PAYMENTS : "has"
    MEMBERS ||--o{ BOOK_RESERVATIONS : "holds"
    BOOKS ||--o{ BOOK_RESERVATIONS : "reserved in"
```

## Tabel auth — TAMBAHAN di luar §3.3 (untuk fitur login/role)

Tabel `users` tidak ada di spec schema, tapi **dibutuhkan untuk auth** sesuai API contract (Access Level: Admin/Staff/Member) & Week 1 checkpoint (`/login`, `/register`).

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at "nullable"
        string phone "nullable"
        string role "enum(admin, staff, member) default member"
        string password
        string remember_token
    }
```

## Relasi

| Sumber | Relasi | Tujuan | Keterangan |
|--------|--------|--------|------------|
| categories | 1 — N | books | satu kategori punya banyak buku (cascade delete) |
| books | 1 — N | loans | satu buku bisa dipinjam berkali-kali (cascade delete) |
| members | 1 — N | loans | satu member bisa punya banyak peminjaman (cascade delete) |
| loans | 1 — 0..1 | fine_payments | denda optional, bayar 1x per loan (cascade delete) |
| members | 1 — N | book_reservations | satu member bisa punya banyak hold buku (cascade delete) |
| books | 1 — N | book_reservations | satu buku bisa di-hold banyak member (cascade delete) |

## Catatan

- **`created_at` / `updated_at`** otomatis ditambah Laravel di semua tabel (tidak di-list di spec §3.3, tapi ada di DB).
- **`users.role`**: kolom sudah ada (di-normalisasi ke huruf kecil: `admin`, `staff`, `member`, default `member`). Dibuat lewat migration `add_role_to_users_table`.
- **`members` vs `users`** terpisah: `members` = peminjam, `users` = staff/admin yang login ke sistem.
- **`book_reservations`** (Week 3): tabel hold/reservasi buku. Alur: `pending` (nunggu stok) → `ready` (buku balik, siap diambil staff) → `fulfilled` (loan terbit) / `cancelled` (dibatalkan). Hanya bisa dibuat kalau `books.available_copies = 0`, max 5 hold aktif per member, hold pertama (FIFO) yang jadi `ready` saat return.
- Pengurangan/penambahan stock ada di `books.available_copies`, diupdate saat issue/return.
