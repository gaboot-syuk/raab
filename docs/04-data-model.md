# 04 — Data Model & ERD

> **Fase 0 — Dokumen Spesifikasi.** Status: *menunggu review*. ⚠️ = asumsi, ✅ = sudah dikonfirmasi.
> Total: **± 57 tabel** (termasuk 5 tabel Spatie Permission, `activity_log`, `notifications`, `jobs`, `media`).
> Terjemahan konten memakai **kolom JSON**, bukan tabel terpisah → lihat `10-lokalisasi-bilingual.md`.

## 1. Konvensi

| Aspek | Aturan |
|---|---|
| Nama tabel | `snake_case`, jamak (contoh: `article_categories`) |
| Primary key | `id` bigint unsigned auto increment |
| Foreign key | `{tabel_tunggal}_id` + constraint `nullOnDelete` atau `cascadeOnDelete` sesuai konteks |
| Timestamps | `created_at`, `updated_at` di semua tabel |
| Soft delete | Pada tabel konten & data penting: `articles`, `events`, `books`, `inventory_items`, `members`, `announcements` |
| Audit | `created_by`, `updated_by`, `deleted_by` (FK ke `users`, nullable) pada tabel data resmi |
| Slug | Kolom `slug` unik pada entitas yang punya halaman publik |
| Status | Pakai **string enum** (bukan angka) agar mudah dibaca di DB; dikelola lewat PHP Enum |
| JSON | Kolom `json` untuk data fleksibel (field tambahan event, sosmed, privasi) — bukan untuk data yang sering difilter |
| Media | Relasi ke tabel `media` lewat FK (`cover_media_id`) **atau** polymorhpic (`model_type`/`model_id`) |
| Uang | `decimal(15,2)` — bukan float |
| Zona waktu | Simpan UTC, tampilkan `Asia/Jakarta` |

## 2. Peta Domain

| # | Domain | Jumlah tabel | Pemilik utama |
|---|---|---|---|
| 1 | Identitas & Akses | 11 | Superadmin, Sekretaris |
| 2 | Organisasi & Kepengurusan | 5 | Sekretaris |
| 3 | Konten, Tampilan Situs & Lokalisasi | 11 | Konten Manager |
| 4 | Event, Kegiatan & Pembinaan | 9 | Sekretaris |
| 5 | Aset & Perpustakaan | 10 | Sekretaris |
| 6 | Keuangan, Iuran & Hibah | 9 | Bendahara |
| 7 | Prestasi | 2 | Sekretaris, Konten Manager |
| 8 | Layanan & Interaksi | 4 | Sekretaris |

---

## 3. ERD

### 3.1 Identitas & Akses

```mermaid
erDiagram
    USERS ||--o| MEMBERS : "profil keanggotaan"
    USERS ||--o{ MEMBER_APPLICATIONS : "mengajukan"
    USERS ||--o{ EMAIL_LOGS : "menerima"
    MEMBERS ||--o{ MEMBER_STATUS_HISTORIES : "riwayat status"
    MEMBERS ||--o| MEMBER_CARDS : "kartu kader"
    USERS }o--o{ ROLES : "model_has_roles"
    ROLES }o--o{ PERMISSIONS : "role_has_permissions"

    USERS {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        string phone
        string whatsapp
        bigint avatar_media_id FK
        enum status
        timestamp last_login_at
    }
    MEMBERS {
        bigint id PK
        bigint user_id FK
        string nomor_anggota UK
        string nim UK
        string nama_lengkap
        enum status_keanggotaan
        date tanggal_mapaba
        string fakultas
        string prodi
        year angkatan_kuliah
        bigint photo_media_id FK
        json visibilitas
    }
    MEMBER_APPLICATIONS {
        bigint id PK
        bigint user_id FK
        enum jenis
        enum status
        json data_pendaftaran
        bigint member_id FK
        text alasan
        bigint verified_by FK
        timestamp verified_at
        boolean auto_verified
    }
    MEMBER_STATUS_HISTORIES {
        bigint id PK
        bigint member_id FK
        string status_lama
        string status_baru
        text alasan
        bigint diubah_oleh FK
    }
    MEMBER_CARDS {
        bigint id PK
        bigint member_id FK
        string nomor_kartu UK
        uuid qr_token UK
        date berlaku_hingga
        enum status
        bigint issued_by FK
    }
    ROLES {
        bigint id PK
        string name UK
        string guard_name
    }
    PERMISSIONS {
        bigint id PK
        string name UK
        string guard_name
    }
```

### 3.2 Organisasi & Kepengurusan

```mermaid
erDiagram
    PERIODS ||--o{ POSITION_ASSIGNMENTS : "berlaku pada"
    POSITIONS ||--o{ POSITION_ASSIGNMENTS : "jabatan"
    MEMBERS ||--o{ POSITION_ASSIGNMENTS : "dijabat oleh"
    ORGANISATION_UNITS ||--o{ POSITION_ASSIGNMENTS : "di unit"
    ORGANISATION_UNITS ||--o{ ORGANISATION_UNITS : "parent"
    MEMBERS ||--o| ALUMNI_PROFILES : "data alumni"

    PERIODS {
        bigint id PK
        string nama UK
        date tanggal_mulai
        date tanggal_selesai
        boolean is_active
    }
    POSITIONS {
        bigint id PK
        string nama
        string slug UK
        int level
        int urutan
        boolean is_unit_position
    }
    ORGANISATION_UNITS {
        bigint id PK
        bigint parent_id FK
        enum tipe
        string nama
        string slug UK
        string singkatan
        bigint logo_media_id FK
        text deskripsi
    }
    POSITION_ASSIGNMENTS {
        bigint id PK
        bigint period_id FK
        bigint position_id FK
        bigint member_id FK
        string nama_manual
        bigint organisation_unit_id FK
        int urutan
    }
    ALUMNI_PROFILES {
        bigint id PK
        bigint member_id FK
        year tahun_mandat_selesai
        string jabatan_terakhir
        string pekerjaan
        string instansi
        string domisili_kota
        string domisili_provinsi
        boolean bersedia_mentor
        json topik_mentor
        json kontak_publik
    }
```

> `organisation_units.tipe`: `komisariat`, `rayon`, `mabinra`, `biro`, `lso` ✅ — berisi **8 Biro** dan **5 LSO** (Mutasi, Harokatuna, LDR, LPM Albiruni, MJT); setiap LSO punya pengurus & halaman sendiri.
> ✅ Istilah resmi rayon: **Biro** (bukan "Divisi") dan **Kepala Biro/Kabiro** (bukan "Kadiv").

### 3.3 Konten & Tampilan Situs

```mermaid
erDiagram
    ARTICLE_CATEGORIES ||--o{ ARTICLES : "kategori"
    USERS ||--o{ ARTICLES : "penulis akun"
    MEMBERS ||--o{ ARTICLES : "penulis kader"
    ARTICLES ||--o{ ARTICLE_TAG : ""
    TAGS ||--o{ ARTICLE_TAG : ""
    ARTICLES ||--o{ ARTICLE_REVISIONS : "riwayat"
    MEDIA ||--o{ ARTICLES : "cover"

    ARTICLES {
        bigint id PK
        enum tipe
        bigint article_category_id FK
        string judul
        string slug UK
        text excerpt
        longtext body
        bigint cover_media_id FK
        bigint author_user_id FK
        bigint author_member_id FK
        string penulis_tamu
        enum status
        boolean is_featured
        timestamp published_at
        timestamp scheduled_at
        bigint views_count
        json meta
        string seo_title
        string seo_description
        text review_notes
        bigint reviewed_by FK
    }
    ARTICLE_CATEGORIES {
        bigint id PK
        string nama
        string slug UK
        enum tipe
        int urutan
    }
    PAGES {
        bigint id PK
        string slug UK
        string judul
        enum tipe
        longtext konten
        bigint media_id FK
        enum status
        timestamp published_at
    }
    SLIDERS {
        bigint id PK
        string judul
        string subjudul
        bigint media_id FK
        string cta_label
        string cta_url
        int urutan
        boolean is_active
    }
    SITE_SETTINGS {
        bigint id PK
        string group
        string key UK
        text value
        string type
    }
    SOCIAL_LINKS {
        bigint id PK
        string platform
        string label
        string url
        string icon
        int urutan
        boolean is_active
    }
    MEDIA {
        bigint id PK
        string disk
        string path
        string nama_asli
        string mime
        bigint size
        int width
        int height
        string alt_text
        bigint uploaded_by FK
    }
    MESSAGES {
        bigint id PK
        string nama
        string email
        string telepon
        string subjek
        text isi
        enum status
        text balasan
        bigint dibalas_oleh FK
    }
```

> `articles.meta` (JSON) dipakai untuk berita acara: `nomor_dokumen`, `agenda`, `lokasi`, `waktu`, `pemimpin_rapat`, `notulis`, `daftar_hadir[]`, `keputusan[]`, `penandatangan[]`, `lampiran[]`.

### 3.4 Event, Kegiatan & Pembinaan

```mermaid
erDiagram
    EVENTS ||--o{ EVENT_REGISTRATIONS : "pendaftar"
    PERIODS ||--o{ ACTIVITIES : "periode"
    ORGANISATION_UNITS ||--o{ ACTIVITIES : "unit"
    ACTIVITIES ||--o{ ATTENDANCES : "presensi"
    MEMBERS ||--o{ ATTENDANCES : ""
    MEMBERS ||--o{ CONTRIBUTION_POINTS : "poin"
    MEMBERS ||--o{ MEMBERS_ACTIVITIES : "target peserta"

    EVENTS {
        bigint id PK
        enum tipe
        string nama
        string slug UK
        text deskripsi
        bigint poster_media_id FK
        string lokasi
        date tanggal_mulai
        date tanggal_selesai
        datetime pendaftaran_dibuka_pada
        datetime pendaftaran_ditutup_pada
        int kuota
        enum status
        string info_biaya
        json field_tambahan
        json syarat
        boolean auto_approve
        bigint created_by FK
    }
    EVENT_REGISTRATIONS {
        bigint id PK
        bigint event_id FK
        string kode_pendaftaran UK
        string nama_lengkap
        enum jenis_kelamin
        string nim
        string email
        string no_hp
        string fakultas
        string prodi
        year angkatan
        json data_tambahan
        enum status
        boolean hadir
        datetime hadir_at
        bigint verified_by FK
        text alasan_tolak
        bigint member_id FK
    }
    ACTIVITIES {
        bigint id PK
        string nama
        enum tipe
        text deskripsi
        string lokasi
        datetime mulai_at
        datetime selesai_at
        bigint period_id FK
        bigint organisation_unit_id FK
        boolean is_wajib
        int poin_hadir
        int poin_terlambat
        enum status
        enum attendance_mode
        uuid qr_token
    }
    ATTENDANCES {
        bigint id PK
        bigint activity_id FK
        bigint member_id FK
        enum status
        datetime check_in_at
        enum metode
        bigint dicatat_oleh FK
        text keterangan
    }
    CONTRIBUTION_POINTS {
        bigint id PK
        bigint member_id FK
        bigint period_id FK
        string sumber
        string referensi_type
        bigint referensi_id
        int poin
        text keterangan
    }
    ANNOUNCEMENTS {
        bigint id PK
        string judul
        string slug UK
        longtext isi
        enum tipe
        json target_audience
        boolean is_pinned
        datetime publish_at
        datetime expire_at
    }
    DOCUMENTS {
        bigint id PK
        string judul
        string kategori
        bigint media_id FK
        json akses
        bigint period_id FK
        bigint uploaded_by FK
    }
```

### 3.5 Aset & Perpustakaan

```mermaid
erDiagram
    INVENTORY_CATEGORIES ||--o{ INVENTORY_ITEMS : ""
    INVENTORY_ITEMS ||--o{ INVENTORY_MOVEMENTS : "mutasi"
    BOOK_CATEGORIES ||--o{ BOOKS : ""
    BOOKS ||--o{ BOOK_COPIES : "eksemplar"
    BOOKS ||--o{ BOOK_RESERVATIONS : "antrian"
    MEMBERS ||--o{ BOOK_RESERVATIONS : ""
    MEMBERS ||--o{ LOANS : "peminjam"
    LOANS ||--o{ LOAN_ITEMS : "rincian"
    LOANS ||--o{ LOAN_EXTENSIONS : "perpanjangan"

    INVENTORY_ITEMS {
        bigint id PK
        bigint inventory_category_id FK
        string kode UK
        string nama
        string satuan
        int jumlah_total
        int jumlah_tersedia
        enum kondisi_default
        string lokasi_penyimpanan
        int tahun_perolehan
        decimal nilai_perolehan
        bigint foto_media_id FK
        boolean is_public
        boolean is_loanable
        int min_stok
    }
    INVENTORY_MOVEMENTS {
        bigint id PK
        bigint inventory_item_id FK
        enum tipe
        int jumlah
        date tanggal
        text keterangan
        string referensi_type
        bigint referensi_id
        bigint dicatat_oleh FK
    }
    BOOKS {
        bigint id PK
        bigint book_category_id FK
        string judul
        string slug UK
        string penulis
        string penerbit
        year tahun_terbit
        string isbn
        string ddc
        text sinopsis
        bigint cover_media_id FK
        string lokasi_rak
        int jumlah_eksemplar
        int jumlah_tersedia
        boolean is_public
    }
    BOOK_COPIES {
        bigint id PK
        bigint book_id FK
        string kode_eksemplar UK
        enum kondisi
        enum status
        string lokasi_rak
    }
    BOOK_RESERVATIONS {
        bigint id PK
        bigint book_id FK
        bigint member_id FK
        int antrian_ke
        enum status
        datetime siap_pada
        datetime kedaluwarsa_pada
        bigint fulfilled_loan_id FK
    }
    LOANS {
        bigint id PK
        string nomor_pinjam UK
        enum borrower_type
        bigint member_id FK
        string nama_peminjam_eksternal
        string instansi_eksternal
        string kontak_eksternal
        bigint penanggung_jawab_member_id FK
        text tujuan
        datetime mulai_at
        datetime jatuh_tempo_at
        datetime dikembalikan_at
        enum status
        text alasan_tolak
        bigint disetujui_oleh FK
        bigint dicatat_oleh FK
    }
    LOAN_ITEMS {
        bigint id PK
        bigint loan_id FK
        string loanable_type
        bigint loanable_id
        int qty
        enum kondisi_keluar
        enum kondisi_masuk
        text catatan
    }
    LOAN_EXTENSIONS {
        bigint id PK
        bigint loan_id FK
        datetime jatuh_tempo_lama
        datetime jatuh_tempo_baru
        text alasan
        enum status
        bigint diproses_oleh FK
    }
```

> `loan_items.loanable_type` = `App\Models\InventoryItem` **atau** `App\Models\BookCopy` (polimorfik) — sehingga satu mekanisme peminjaman melayani aset & buku.

### 3.6 Keuangan

```mermaid
erDiagram
    FINANCE_ACCOUNTS ||--o{ TRANSACTIONS : ""
    FINANCE_CATEGORIES ||--o{ TRANSACTIONS : "kategori"
    PERIODS ||--o{ TRANSACTIONS : ""
    TRANSACTIONS ||--o| DUES_PAYMENTS : "sumber"
    DUES ||--o{ DUES_PAYMENTS : ""
    MEMBERS ||--o{ DUES_PAYMENTS : ""
    PERIODS ||--o{ BUDGETS : ""
    FINANCE_CATEGORIES ||--o{ BUDGETS : ""
    MEMBERS ||--o{ DONATIONS : "pemberi hibah"
    DONATIONS ||--o| TRANSACTIONS : "menjadi kas masuk"

    FINANCE_ACCOUNTS {
        bigint id PK
        string nama
        string slug UK
        decimal saldo_awal
        decimal saldo_berjalan
        boolean is_active
    }
    FINANCE_CATEGORIES {
        bigint id PK
        string nama
        string slug UK
        enum tipe
        bigint parent_id FK
        boolean is_iuran
    }
    TRANSACTIONS {
        bigint id PK
        bigint finance_account_id FK
        bigint finance_category_id FK
        bigint period_id FK
        string nomor_voucher UK
        date tanggal
        enum tipe
        decimal jumlah
        text keterangan
        string sumber
        bigint bukti_media_id FK
        string related_type
        bigint related_id
        enum status
        bigint dicatat_oleh FK
        bigint diverifikasi_oleh FK
        text alasan_void
    }
    DUES {
        bigint id PK
        string nama
        int bulan
        year tahun
        decimal nominal
        json target
        date jatuh_tempo
        boolean is_active
    }
    DUES_PAYMENTS {
        bigint id PK
        bigint due_id FK
        bigint member_id FK
        decimal jumlah
        date tanggal_bayar
        enum metode
        bigint bukti_media_id FK
        enum status
        bigint diverifikasi_oleh FK
    }
    BUDGETS {
        bigint id PK
        bigint period_id FK
        bigint finance_category_id FK
        bigint organisation_unit_id FK
        string nama_program
        decimal pagu_anggaran
        text keterangan
    }
    REPORT_EXPORTS {
        bigint id PK
        string jenis
        json parameter
        bigint media_id FK
        bigint requested_by FK
        enum status
    }
    DONATIONS {
        bigint id PK
        string nomor_hibah UK
        bigint member_id FK
        string nama_pemberi_manual
        enum jenis
        string tujuan
        text deskripsi
        decimal nominal_estimasi
        decimal nominal_diterima
        bigint bukti_media_id FK
        enum status
        boolean tampil_anonim
        date tanggal_janji
        date tanggal_terima
        bigint transaction_id FK
        bigint inventory_movement_id FK
        bigint activity_id FK
        bigint verified_by FK
        text catatan
    }
```

### 3.7 Prestasi

```mermaid
erDiagram
    ACHIEVEMENT_CATEGORIES ||--o{ ACHIEVEMENTS : ""
    MEMBERS ||--o{ ACHIEVEMENTS : ""

    ACHIEVEMENTS {
        bigint id PK
        bigint member_id FK
        bigint achievement_category_id FK
        string nama_kegiatan
        enum tingkat
        string peringkat
        string penyelenggara
        year tahun
        date tanggal
        text deskripsi
        bigint sertifikat_media_id FK
        enum status
        boolean is_featured
        string sumber
        bigint verified_by FK
        text alasan_tolak
    }
```

### 3.8 Layanan & Interaksi

```mermaid
erDiagram
    ASPIRATIONS ||--o{ ASPIRATION_REPLIES : "tanggapan"

    ASPIRATIONS {
        bigint id PK
        string nomor_tiket UK
        bigint user_id FK
        string nama_pelapor
        string email_pelapor
        string telepon_pelapor
        enum jenis_pelapor
        string kategori
        string judul
        longtext isi
        json lampiran
        enum status
        enum prioritas
        boolean tampil_publik
        boolean is_anonim_publik
        datetime published_at
    }
    ASPIRATION_REPLIES {
        bigint id PK
        bigint aspiration_id FK
        enum tipe
        longtext isi
        bigint user_id FK
    }
```

### 3.9 Galeri, Agenda Unit & Iuran Berkategori 🆕

```mermaid
erDiagram
    ORGANISATION_UNITS ||--o{ UNIT_AGENDAS : "agenda publik"
    ORGANISATION_UNITS ||--o{ GALLERIES : "galeri"
    GALLERIES ||--o{ GALLERY_ITEMS : "foto"
    MEDIA ||--o{ GALLERY_ITEMS : ""
    DUES_TIERS ||--o{ DUES : "kategori iuran"

    UNIT_AGENDAS {
        bigint id PK
        bigint organisation_unit_id FK
        string judul
        string slug UK
        text deskripsi
        string lokasi
        datetime mulai_at
        datetime selesai_at
        bigint poster_media_id FK
        enum status
        boolean is_public
        bigint created_by FK
    }
    GALLERIES {
        bigint id PK
        bigint organisation_unit_id FK
        bigint event_id FK
        bigint activity_id FK
        string judul
        string slug UK
        text deskripsi
        bigint cover_media_id FK
        date tanggal
        enum status
        boolean is_public
        int urutan
    }
    GALLERY_ITEMS {
        bigint id PK
        bigint gallery_id FK
        bigint media_id FK
        string caption
        int urutan
    }
    DUES_TIERS {
        bigint id PK
        string nama
        string slug UK
        enum target_status
        decimal nominal_default
        text keterangan
        boolean is_active
        int urutan
    }
```

**Aturan**
- `unit_agendas` = agenda **publik** milik unit (LSO/divisi), **dikelola Konten Manager** ✅ — berbeda dari `activities` (kegiatan internal + presensi, milik Sekretaris). Tidak ada presensi di sini.
- `galleries` bisa menempel ke **unit**, **event**, atau **kegiatan**; `gallery_items` = daftar foto + keterangan.
- Seluruh teks galeri & agenda **dapat diterjemahkan** (lihat `10-lokalisasi-bilingual.md`).
- `dues_tiers` = **kategori iuran** ✅, dikelola Bendahara. Tiga kategori awal: **iuran anggota aktif**, **iuran pengurus**, **iuran alumni**. Bendahara bisa menambah kategori lain.
- `dues` bertambah kolom **`dues_tier_id`** → menentukan siapa yang otomatis mendapat tagihan (berdasarkan `target_status`).

---

## 4. Enum & Status

| Tabel.kolom | Nilai |
|---|---|
| `users.status` | `aktif`, `nonaktif`, `ditangguhkan` |
| `members.status_keanggotaan` | `belum_anggota`, `calon`, `kader_aktif`, `alumni`, `mengundurkan_diri`, `diberhentikan` |
| `member_applications.jenis` | `kader_aktif`, `alumni` |
| `member_applications.status` | `draft`, `menunggu_email`, `menunggu_verifikasi`, `perlu_perbaikan`, `disetujui`, `ditolak`, `kedaluwarsa` |
| `member_cards.status` | `aktif`, `kedaluwarsa`, `dicabut` |
| `organisation_units.tipe` | `komisariat`, `rayon`, `mabinra`, `biro`, `lso` ✅ |
| `articles.tipe` | `berita`, `opini`, `kajian`, `esai`, `sastra`, `berita_acara`, `pers_release` |
| `articles.status` | `draft`, `menunggu_review`, `perlu_revisi`, `dijadwalkan`, `terbit`, `arsip`, `ditolak` |
| `pages.tipe` | `sejarah`, `visi_misi`, `sambutan`, `statis` |
| `pages.status` | `draft`, `terbit` |
| `events.tipe` | `mapaba`, `pkd`, `lainnya` |
| `events.status` | `draft`, `dibuka`, `ditutup`, `berlangsung`, `selesai`, `dibatalkan` |
| `event_registrations.status` | `menunggu`, `terverifikasi`, `cadangan`, `ditolak`, `dibatalkan` |
| `activities.tipe` | `rapat`, `kajian`, `pelatihan`, `sosial`, `lainnya` |
| `activities.status` | `rencana`, `berlangsung`, `selesai`, `dibatalkan` |
| `activities.attendance_mode` | `manual`, `qr` |
| `attendances.status` | `hadir`, `terlambat`, `izin`, `sakit`, `alpa` |
| `inventory_items.kondisi_default` | `baik`, `rusak_ringan`, `rusak_berat` |
| `inventory_movements.tipe` | `masuk`, `keluar`, `penyesuaian_tambah`, `penyesuaian_kurang`, `rusak`, `hilang`, `perbaikan_selesai`, `pengembalian` |
| `book_copies.kondisi` | `baik`, `rusak_ringan`, `rusak_berat`, `hilang` |
| `book_copies.status` | `tersedia`, `dipinjam`, `dipesan`, `rusak`, `perbaikan`, `hilang` |
| `book_reservations.status` | `menunggu`, `siap_diambil`, `selesai`, `kedaluwarsa`, `dibatalkan` |
| `loans.borrower_type` | `member`, `eksternal` |
| `loans.status` | `diajukan`, `disetujui`, `ditolak`, `dipinjam`, `dikembalikan`, `terlambat`, `bermasalah` |
| `loan_extensions.status` | `diajukan`, `disetujui`, `ditolak` |
| `finance_categories.tipe` | `masuk`, `keluar` |
| `transactions.tipe` | `masuk`, `keluar` |
| `transactions.sumber` | `iuran`, `hibah`, `donasi`, `dana_kegiatan`, `inventaris`, `operasional`, `lainnya` |
| `donations.jenis` 🆕 | `dana`, `barang`, `jasa` ⚠️ |
| `donations.status` 🆕 | `diajukan`, `dijanjikan`, `diterima`, `diverifikasi`, `ditolak`, `dibatalkan` |
| `dues_tiers.target_status` 🆕 | `kader_aktif`, `pengurus`, `alumni`, `semua` |
| `unit_agendas.status` 🆕 | `rencana`, `berlangsung`, `selesai`, `dibatalkan` |
| `galleries.status` 🆕 | `draft`, `terbit`, `arsip` |
| `users.locale`, `event_registrations.locale` 🆕 | `id`, `en` |
| `users.preferensi_tema` 🆕 | `terang`, `gelap`, `sistem` |
| `transactions.status` | `draft`, `terkonfirmasi`, `void` |
| `dues.target` | JSON: `{ scope: 'semua'|'angkatan'|'unit', nilai: [...] }` |
| `dues_payments.metode` | `tunai`, `transfer` |
| `dues_payments.status` | `menunggu`, `terverifikasi`, `ditolak` |
| `achievements.tingkat` | `rayon`, `komisariat`, `kota`, `provinsi`, `nasional`, `internasional` ⚠️ |
| `achievements.status` | `diajukan`, `terverifikasi`, `ditolak` |
| `achievements.sumber` | `pengajuan_kader`, `input_pengurus` |
| `aspirations.jenis_pelapor` | `publik`, `kader`, `alumni`, `pengurus` |
| `aspirations.status` | `baru`, `dibaca`, `diproses`, `ditanggapi`, `selesai`, `ditutup`, `spam` |
| `aspirations.prioritas` | `rendah`, `normal`, `tinggi` |
| `aspiration_replies.tipe` | `internal`, `publik` |
| `documents.akses` | JSON: `["publik","kader","alumni","pengurus"]` |
| `announcements.tipe` | `internal`, `publik` |

## 5. Index & Constraint Penting

```text
-- Unik
users.email                                    UNIQUE
members.nim                                    UNIQUE
members.nomor_anggota                          UNIQUE
member_cards.qr_token                          UNIQUE
articles.slug                                  UNIQUE
events.slug                                    UNIQUE
books.slug  /  books.isbn                      UNIQUE (isbn nullable)
inventory_items.kode                           UNIQUE
book_copies.kode_eksemplar                     UNIQUE
event_registrations.kode_pendaftaran            UNIQUE
event_registrations (event_id, email)          UNIQUE  -- cegah daftar ganda
aspirations.nomor_tiket                        UNIQUE
transactions.nomor_voucher                     UNIQUE
dues_payments (due_id, member_id)              UNIQUE  -- cegah tagihan ganda
attendances (activity_id, member_id)           UNIQUE  -- cegah absen ganda

-- Index pencarian & filter
articles (tipe, status, published_at)          INDEX
articles (article_category_id, status)         INDEX
events (tipe, status, pendaftaran_dibuka_pada) INDEX
loans (status, jatuh_tempo_at)                 INDEX
transactions (finance_account_id, tanggal)     INDEX
attendances (activity_id, status)              INDEX
members (status_keanggotaan, angkatan_kuliah)  INDEX

-- Fulltext (MySQL 8 / MariaDB 10.4+)
articles (judul, excerpt, body)                FULLTEXT
books (judul, penulis, sinopsis)               FULLTEXT
```

**Format kode & nomor ✅**

| Kode | Format | Catatan |
|---|---|---|
| **Nomor anggota** | `RAAB-{tahun periode mulai}-{6 digit acak}` — contoh `RAAB-2026-482913` | ✅ **Nomor acak, bukan berurutan** (menyembunyikan jumlah anggota). Dibuat saat status menjadi `kader_aktif`, dicek unik ke database, penomoran **dimulai dari periode saat ini** |
| Nomor kartu kader | `KRT-{nomor anggota}` | — |
| Kode pendaftaran event | `{TIPE}-{tahun}-{4 digit}` → `MAPABA-2026-0147` | — |
| Nomor hibah | `HIB-{tahun}-{4 digit}` | ⚠️ perlu persetujuan |
| Nomor berita acara | `{urut}/BA/RAAB/{bulan romawi}/{tahun}` | ⚠️ perlu persetujuan |
| Nomor voucher transaksi | `{urut}/{KAS}/{bulan romawi}/{tahun}` | — |
| Nomor tiket aspirasi | `ASP-{tahun}-{4 digit}` | — |

**Aturan integritas**
- Menghapus `members` → tidak menghapus presensi/prestasi; gunakan **soft delete** dan pertahankan relasi.
- Menghapus `articles` → `article_tag` ikut terhapus (`cascadeOnDelete`).
- Menghapus `events` yang sudah punya pendaftar → **dilarang** (hanya boleh dinonaktifkan/arsip).
- Menghapus `transactions` → **dilarang** (hanya `void`).
- `media` yang masih direferensikan tidak boleh dihapus (cek relasi sebelum hapus).

## 6. Penyimpanan Media

| Konteks | Disk | Catatan |
|---|---|---|
| Gambar umum | `public` (`storage/app/public`) | Symlink `public/storage`; fallback `public/uploads` bila hosting melarang symlink ⚠️ |
| Dokumen PDF (berita acara, laporan) | `local` | Diunduh lewat route ber-proteksi, bukan URL publik |
| Sertifikat prestasi, bukti transfer, bukti transaksi | `local` | **Sensitif** — hanya pemilik/verifikator yang bisa mengunduh |
| Foto kader | `public` | Di-resize otomatis (thumbnail 96px, medium 480px) |
| Cover artikel & poster event | `public` | Konversi ke WebP + ukuran maks. 1600px |

## 7. Data Awal (Seeder) yang Diusulkan

| Seeder | Isi |
|---|---|
| `RolePermissionSeeder` | 4 role + ±125 permission sesuai `02-role-permission.md` |
| `SuperadminSeeder` | 1 akun superadmin (kredensial dari `.env`, wajib ganti password saat login pertama) |
| `SettingSeeder` | **PMII Rayon Ali Ahmad Baktsir**, **PMII Komisariat Raden Mas Said — Cabang Sukoharjo**, **UIN Raden Mas Said Surakarta**; alamat/jam/maps/lokasi **placeholder**, SEO default, parameter perpustakaan (7 hari, 1× perpanjang, 2 buku, 2×24 jam ambil, **tanpa denda**) |
| `PeriodSeeder` | Periode **Masa Juang** berlaku 1 tahun, dari **2017** s/d periode aktif sekarang → 10 periode placeholder ✅ |
| `PositionSeeder` | Struktur tetap: **Mabinra** → **Ketua Rayon** → **Wakil Ketua** → **Sekretaris 1** → **Sekretaris 2** → **Bendahara 1** → **Bendahara 2** → **8 Kepala Biro (Kabiro)** + jabatan unit untuk LSO ✅ |
| `UnitSeeder` | **8 Biro** (Advokasi & Gerakan/Advoger, Kaderisasi, Keilmuan, Keagamaan, **Gender**, Kebudayaan, Media, Kewirausahaan) + **5 LSO** (Mutasi, Harokatuna, LDR, LPM Albiruni, MJT) — deskripsi placeholder ✅ |
| `CategorySeeder` | Tipe artikel (berita, opini, kajian, esai, sastra, berita_acara, pers_release), kategori inventaris (elektronik, perlengkapan, ATK, kendaraan), kategori buku (DDC ringkas), kategori keuangan (iuran, hibah, donasi, operasional, kegiatan, inventaris) |
| `FinanceAccountSeeder` | Kas Utama + Kas Kegiatan (saldo awal 0) |
| `DuesTierSeeder` 🆕 | 3 kategori iuran: **iuran anggota aktif**, **iuran pengurus**, **iuran alumni** — nominal placeholder, nominal & target diatur Bendahara ✅ |
| `PageSeeder` | Halaman Sejarah, Visi & Misi, Sambutan — **versi ID + EN** (contoh dwibahasa) |
| `DemoSeeder` | Data contoh: 30 anggota, 20 alumni, 12 artikel, 5 prestasi, **50 judul buku / 150 eksemplar** ⚠️ placeholder, 15 aset, 3 event, 2 periode kepengurusan, 5 LSO lengkap dengan galeri & agenda (nonaktif di produksi) |

### Struktur Jabatan Placeholder ✅

| Level bagan | Jabatan |
|---|---|
| 0 — Pembina | **Mabinra** (Majelis Pembina Rayon) |
| 1 | **Ketua Rayon** |
| 2 | **Wakil Ketua** |
| 3 | **Sekretaris 1**, **Sekretaris 2** |
| 3 | **Bendahara 1**, **Bendahara 2** |
| 4 — Koordinator | **Kepala Biro (Kabiro)**: Advokasi & Gerakan (Advoger) · Kaderisasi · Keilmuan · Keagamaan · **Gender** · Kebudayaan · Media · Kewirausahaan |
| 5 | Anggota Divisi ⚠️ (opsional) |
| Unit LSO | Ketua · Wakil · Sekretaris · Bendahara · Anggota (per LSO) |

> ✅ Istilah resmi: **Biro** (bukan "Divisi") dan **Kepala Biro/Kabiro** (bukan "Kadiv"). Unit **Biro Gender** sudah termasuk.
> ⚠️ "Wakil" saya baca sebagai **Wakil Ketua**; "Sekertaris" ditulis **Sekretaris** (ejaan baku). Mohon koreksi bila ada jabatan yang kurang.

## 8. Catatan Migrasi & Data Lama

✅ Konfirmasi kamu: data lama **ada**, tetapi untuk MVP dipakai **placeholder** dulu; impor menyusul.

| Data | Bentuk | Rencana |
|---|---|---|
| Daftar anggota/kader | ⚠️ bentuk berkasnya belum disebutkan | Alat impor CSV + pemetaan kolom, dijalankan setelah Fase 2 |
| Daftar alumni | ⚠️ idem | Sama, setelah Fase 4 |
| Katalog buku | ⚠️ idem | Impor CSV (judul, penulis, ISBN, jumlah eksemplar) setelah Fase 6 |
| Inventaris aset | ⚠️ idem | Impor CSV setelah Fase 6 |
| Prestasi kader | ⚠️ idem | Impor setelah Fase 8 |
| Riwayat keuangan lampau | ⚠️ idem | Usulan: **tidak** dimigrasi; mulai dari saldo awal saja |

> Untuk MVP, `DemoSeeder` mengisi data contoh agar tampilan tidak kosong. Sub-fase "Impor Data" ditambahkan ke roadmap (`05-roadmap-fase.md`) dan skema CSV akan disepakati sebelum dijalankan.
