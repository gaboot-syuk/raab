# 06 — Keputusan Teknis & Batasan Hosting

> **Fase 0 — Dokumen Spesifikasi.** Status: *menunggu review*. ✅ = sudah dikonfirmasi, ⚠️ = asumsi.

## 1. Stack ✅

| Lapisan | Pilihan |
|---|---|
| Framework | **Laravel 13** ✅ — versi stabil terbaru, menggantikan rencana awal Laravel 12 (arsitektur & dokumen tetap berlaku) |
| **Halaman publik** 🆕 | **Blade + Tailwind** (server-rendered) + **Alpine.js** untuk interaksi halus |
| **Panel admin & dashboard** 🆕 | **Inertia.js v2 + Vue 3** (Composition API, `<script setup>`) + TypeScript |
| Styling | Tailwind CSS v4 — **token desain yang sama** dipakai Blade & Vue |
| Build | **Vite 8** + `laravel-vite-plugin` v3 (membangun aset Blade **dan** bundel Inertia), plugin font otomatis (Bunny) |
| **Lingkungan pengembangan** 🆕 | **Docker Compose** — app (PHP 8.4 CLI), MariaDB 11, Mailpit, queue worker, scheduler |
| Database | MySQL 8 / MariaDB 10.4+ (Docker: **MariaDB 11**) |
| PHP | **≥ 8.3** — syarat Laravel 13 (lokal terverifikasi pada PHP 8.4.24) |
| Email | Brevo atau Resend via API/SMTP ✅ |
| Hosting | MVP: **free tier** (Render + TiDB + Cloudflare R2) ✅ → `09-deploy-mvp-free-tier.md`; rilis: shared hosting / cPanel murah |

## 2. Batasan Hosting Murah → Keputusan Arsitektur

| Batasan nyata | Keputusan kita |
|---|---|
| Tidak ada Node.js di server | Asset di-`npm run build` **di lokal**, lalu folder `public/build` di-upload. Server hanya menerima file statis. |
| **Tidak ada SSR Inertia** | Halaman publik dirender **server-side dengan Blade** → HTML lengkap dikirim ke crawler, SEO bagus tanpa SSR 🆕 |
| Tidak ada worker queue permanen | `QUEUE_CONNECTION=database` + cron tiap 5 menit: `queue:work --stop-when-empty --max-time=280`. Email terkirim ≤ 5 menit setelah dipicu. |
| Scheduler tidak berjalan sendiri | Cron tiap menit: `php artisan schedule:run`. Dipakai untuk: kirim email antrean, artikel terjadwal, expired reservasi buku, pengingat jatuh tempo, backup. |
| Tidak ada Redis | `CACHE_STORE=file`, `SESSION_DRIVER=file` (atau `database` bila file cache terlalu banyak), `QUEUE_CONNECTION=database`. |
| Symlink `storage:link` kadang diblokir | Kode memakai helper path gambar yang menyesuaikan: bila symlink gagal → fallback ke `public/uploads`. Saya siapkan perintah artisan `storage:check` untuk mendeteksi. |
| Memory limit rendah (128–256 MB) | Laravel normal saja; hindari `Excel::toCollection` untuk data raksasa — export memakai **chunk query** + streamed writer. |
| `max_execution_time` terbatas | Semua proses berat (export, sinkronisasi, backup) dijalankan lewat **queued job**, bukan request. |
| Tidak ada `git` / SSH | Deploy manual (FTP/ZIP) didokumentasikan langkah demi langkah, tanpa perintah yang mustahil dijalankan. ⚠️ Bila ada SSH, deploy jadi jauh lebih mudah. |
| Document root = `public_html` | Dua opsi: (a) ubah document root ke `/public` (paling aman, bila cPanel mengizinkan), (b) aplikasi di luar `public_html` + `.htaccess` pengarah. |
| HTTPS sering dari AutoSSL/Cloudflare | Aktifkan `force_https` di produksi + `trustProxies` bila di belakang Cloudflare. |
| Tidak bisa menjalankan test di server | Test (Pest) dijalankan **lokal** sebelum deploy. |
| **Free tier:** kontainer "tidur" saat idle, tanpa cron, disk sementara | Penjadwal lewat URL + cron eksternal, berkas disimpan ke penyimpanan objek, `QUEUE_CONNECTION=sync` — dirinci di `09-deploy-mvp-free-tier.md` |

## 3. Paket Composer & NPM

### Composer
| Paket | Kegunaan |
|---|---|
| `inertiajs/inertia-laravel` v3 | Jembatan Inertia untuk panel admin & dashboard |
| `laravel/fortify` ✅ | Backend autentikasi resmi: login, registrasi, verifikasi email, reset kata sandi, **2FA opsional**, passkey — UI dibuat sendiri dengan Blade |
| `spatie/laravel-permission` | Role & permission |
| `spatie/laravel-activitylog` | Jejak audit |
| `spatie/laravel-sitemap` | `sitemap.xml` |
| `spatie/laravel-medialibrary` | Manajemen media + konversi gambar |
| `intervention/image` | Resize/optimasi gambar (dipakai medialibrary) |
| `maatwebsite/excel` | Export Excel (peserta, anggota, iuran, inventaris) |
| `barryvdh/laravel-dompdf` | PDF: kartu kader, berita acara, laporan |
| `mcamara/laravel-localization` 🆕 | Prefiks bahasa di URL, deteksi bahasa, helper `hreflang` |
| `spatie/laravel-translatable` 🆕 | Kolom JSON multi-bahasa untuk konten dari database |
| `laravel-vue-i18n` 🆕 | Terjemahan antarmuka area **admin** (Inertia + Vue). Halaman publik memakai `__()` langsung di Blade |
| `deeplcom/deepl-php` / `google/cloud-translate` 🆕 | Terjemahan otomatis konten (opsional; adaptor `deepl` / `google` / `none`) |
| `laravel/socialite` ⚠️ | Login Google (opsional — perlu keputusan) |
| `tightenco/ziggy` | Route helper di Vue |
| `spatie/laravel-backup` ⚠️ | Backup otomatis (bisa diganti skrip `mysqldump` bila berat) |
| `guzzlehttp/guzzle` | Kirim email via API Brevo/Resend |
| `laravel/horizon` ❌ | Tidak dipakai (butuh Redis) |
| `laravel/scout` ❌ | Tidak dipakai (tanpa Meilisearch) — pencarian pakai `LIKE` + FULLTEXT index |

### NPM
| Paket | Kegunaan |
|---|---|
| `@inertiajs/vue3`, `vue`, `typescript`, `vite` | Inti area admin & dashboard |
| `alpinejs` + `@alpinejs/*` 🆕 | Interaksi halaman publik: dropdown, drawer, toggle tema, tab, filter, modal |
| `@tailwindcss/typography` 🆕 | Tipografi isi artikel & karya sastra |
| `@fontsource/archivo-black`, `@fontsource/plus-jakarta-sans`, `@fontsource/lora` 🆕 | Font self-host — termasuk **serif untuk Sastra** (V4) |
| `tailwindcss` v4 + `@tailwindcss/vite` | Styling |
| `@headlessui/vue`, `@heroicons/vue` | Komponen aksesibel |
| `@tiptap/vue-3` + ekstensi | Editor rich text artikel |
| `@vueuse/core` | Utility (debounce, clipboard, dll) |
| `chart.js` + `vue-chartjs` | Grafik keuangan & statistik |
| `leaflet` | Peta sebaran alumni (gratis, tanpa API key) ⚠️ alternatif: Google Maps embed |
| `dayjs` | Format tanggal Indonesia |
| `vue-sonner` | Notifikasi toast |
| `vuedraggable` | Urutkan ulang slider/menu |
| `qrcode` / `vue-qrcode` | Menampilkan QR kartu kader & presensi |
| `html5-qrcode` ⚠️ | Scan QR presensi dari HP (opsional; alternatif manual) |

## 4. Struktur Folder

```text
RAAB/
├── app/
│   ├── Enums/                 # StatusKeanggotaan, StatusArtikel, StatusPeminjaman, ...
│   ├── Actions/               # Logika satu-tugas: VerifikasiAnggota, PinjamBuku, CatatTransaksi
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Public/        # Mengembalikan view **Blade** (beranda, artikel, LSO, event, perpustakaan)
│   │   │   ├── Admin/         # Mengembalikan halaman Inertia (Sekretaris, Bendahara, Konten, Pengaturan)
│   │   │   └── Member/        # Halaman Inertia dashboard kader & alumni
│   │   ├── Middleware/        # EnsureIsMember, EnsureRole, EnsurePermission
│   │   ├── Requests/          # FormRequest per aksi
│   │   └── Resources/         # API Resource: MemberPublicResource, MemberAdminResource
│   ├── Models/
│   ├── Policies/
│   └── Support/               # Helper: NomorAnggota, NomorDokumen, PathGambar
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── docs/                      # dokumen Fase 0 ini
├── resources/
│   ├── views/                  # 🆕 BLADE — halaman publik (server-rendered)
│   │   ├── layouts/            # public.blade.php, app.blade.php (root Inertia untuk admin)
│   │   ├── components/         # komponen Blade: kartu, badge, tombol, tabel, form, pagination
│   │   ├── public/             # beranda, profil, publikasi, lso, event, perpustakaan, verifikasi
│   │   └── emails/             # template email (dwibahasa)
│   ├── js/
│   │   ├── app.ts              # entry halaman publik: Alpine + interaksi halus
│   │   ├── inertia.ts          # entry Inertia untuk area admin & dashboard
│   │   ├── Layouts/            # AdminLayout, MemberLayout, AuthLayout (Vue)
│   │   ├── Pages/              # Admin/, Member/, Auth/ (Vue)
│   │   ├── Components/         # komponen Vue: Tabel, Filter, MediaPicker, RichEditor
│   │   ├── Composables/        # usePermission, useFormat, useConfirm
│   │   └── types/              # TypeScript types dari model
│   └── css/app.css             # token neo-brutalism (terang + gelap)
├── routes/
│   ├── public.php
│   ├── admin.php
│   ├── member.php
│   └── auth.php
└── tests/Feature/             # Alur kritis
```

## 5. Konvensi Kode

| Aspek | Aturan |
|---|---|
| Controller | Tipis. Logika bisnis ke `Actions` (satu class = satu tugas, mis. `SetujuiPendaftaranAnggota`) |
| Validasi | `FormRequest` terpisah, pesan error berbahasa Indonesia |
| Enum | PHP 8.1 backed enum (`StatusArtikel::TERBIT`) — jangan pakai string liar di kode |
| Relasi & query | Selalu eager load (`with`) untuk hindari N+1; index DB sesuai `04-data-model.md` |
| Policy | Untuk kepemilikan & aksi sensitif; middleware untuk role/permission |
| Nama route | `public.*`, `admin.*`, `member.*` — dipakai lewat Ziggy di Vue |
| Komponen Vue (admin) | `<script setup lang="ts">`, `defineProps` bertipe, satu berkas satu komponen |
| **Komponen Blade (publik)** 🆕 | Satu berkas = satu komponen, props eksplisit (`@props`), **tanpa logika berat** — logika di Controller/Action |
| **Interaksi publik** 🆕 | **Alpine.js**, bukan Vue — agar halaman publik ringan dan tanpa hidrasi |
| Halaman publik | **Server-rendered**, wajib tampil baik walau JavaScript mati (progressive enhancement) |
| Form | `useForm` dari Inertia; error ditampilkan per field; tombol disabled saat submit |
| Teks UI | **Tidak ada teks langsung di komponen Vue** — semua lewat `$t('...')`; istilah organisasi konsisten (Mapaba, PKD, LSO, Rayon, Mabinra) |
| Terjemahan konten | Kolom JSON ID+EN (`spatie/laravel-translatable`); Indonesia wajib, Inggris opsional dengan fallback → `10-lokalisasi-bilingual.md` |
| Tema | Token warna terang & gelap berdampingan (`@custom-variant dark`); uji 4 kombinasi: terang/gelap × mobile/desktop |
| Tanggal | Simpan UTC, tampilkan `Asia/Jakarta` format `d F Y` (contoh: 26 September 2026) |
| Uang | Format `Rp1.250.000` via helper terpusat |
| Warna & desain | Token Tailwind terpusat (primary/secondary), bukan warna tersebar ⚠️ menunggu branding |
| Test | Pest. Minimal 1 feature test per alur kritis di `03-alur-bisnis.md` |

## 6. Alur Deploy (rencana)

> 🆕 **Deploy MVP kini memakai free tier** — langkah lengkap ada di `09-deploy-mvp-free-tier.md`.
> Bagian di bawah ini berlaku untuk **hosting berbayar murah** (tahap 9B).

```text
1. Lokal : php artisan test            → semua lulus
2. Lokal : npm run build               → hasil di public/build
3. Lokal : php artisan config:cache / route:cache  (opsional, hati-hati saat dev)
4. Upload: seluruh proyek ke luar public_html, isi public/ ke public_html
5. Server: set .env produksi (APP_ENV=production, APP_DEBUG=false, APP_URL=https)
6. Server: import database via phpMyAdmin (dari file .sql hasil mysqldump lokal)
7. Server: php artisan migrate --force
8. Server: php artisan db:seed --class=RolePermissionSeeder  (sekali saja)
9. Server: buat symlink storage (atau aktifkan fallback uploads)
10. Server: set cron (* * * * * php /path/artisan schedule:run)
11. Server: php artisan optimize
12. Uji manual checklist  ✓
```

**Pembaruan berikutnya (tanpa SSH):** upload file yang berubah → jalankan `migrate --force` lewat halaman/URL maintenance khusus (dengan token rahasia satu-kali-pakai) ⚠️, lalu `optimize`.

> ⚠️ **Perlu keputusanmu:** kalau ternyata bisa dapat **SSH + git** di paket murah, deploy akan jauh lebih aman dan cepat. Saya sarankan cek dulu ke penyedia hosting sebelum kita masuk Fase 9.

## 7. Cron & Antrean

```cron
* * * * * php /home/USER/raab/artisan schedule:run >> /dev/null 2>&1
```

| Jadwal | Tugas |
|---|---|
| Setiap menit | `schedule:run` |
| Setiap 5 menit | Proses antrean: `queue:work --stop-when-empty --max-time=280` |
| Setiap 5 menit (free tier) 🆕 | cron-job.org memanggil `GET /internal/scheduler/{token}` → menjalankan `schedule:run` (pengganti cron server) |
| Harian 01.00 | Bersihkan token kedaluwarsa, reservasi buku lewat masa berlaku, tandai pinjaman terlambat |
| Harian 07.00 | Email pengingat jatuh tempo pinjaman (H-1) & pengingat keterlambatan |
| Harian 02.00 | Backup database + file penting (retensi 14 hari) |
| Bulanan | Ringkasan statistik organisasi untuk Superadmin ⚠️ (opsional) |

## 8. Email ✅

| Aspek | Keputusan |
|---|---|
| Penyedia | Brevo (utama) atau Resend — keduanya punya kuota gratis harian |
| Domain pengirim | `sekretariat@domain-rayon` ⚠️ perlu domain resmi |
| Verifikasi | DNS: SPF + DKIM (wajib, agar tidak masuk spam) |
| Konfigurasi | Pengiriman lewat API key di `.env`, bukan SMTP cPanel |
| Template | 1 base layout + template: verifikasi email, akun aktif, penolakan, undangan kader, konfirmasi event, kode aspirasi, pengingat pinjam, notifikasi prestasi |
| Kendali | Rute dev: semua email ke satu alamat penampung (mis. sebutkan alamat) ⚠️ perlu alamat |
| Log | Semua email tercatat di `email_logs` agar mudah menelusuri kegagalan |

## 9. SEO (mode SPA tanpa SSR)

| Teknik | Implementasi |
|---|---|
| Meta per halaman | Komponen `<Head>` Inertia: title, description, canonical, Open Graph, Twitter card |
| JSON-LD | `Organization` di beranda, `Article` di artikel, `Person` di profil kader |
| `sitemap.xml` | `spatie/laravel-sitemap` digenerate via cron harian — **satu sitemap per bahasa** 🆕 |
| `hreflang` 🆕 | `id`, `en`, dan `x-default` (ID) pada setiap halaman yang punya dua versi; versi EN yang belum lengkap diberi `noindex, follow` |
| `robots.txt` | Blokir `/admin`, `/saya`, area privat |
| Prerender sederhana ⚠️ | Opsional: halaman penting (beranda, sejarah, visi-misi) bisa di-cache HTML ringan untuk crawler. Usulan: **belum perlu**, cukup meta + sitemap |
| Gambar | `alt` wajib diisi dari media library (validasi server) |
| URL bersih | Slug bahasa Indonesia tanpa tanda baca |

> ✅ **Perubahan keputusan (kabar baik):** halaman publik kini dirender **server-side dengan Blade**, sehingga crawler menerima HTML lengkap **tanpa** perlu JavaScript dan **tanpa** perlu SSR Inertia. Kekhawatiran SEO yang saya tulis sebelumnya **hilang**. Area admin/dashboard tetap memakai Inertia (tidak memerlukan SEO).

## 10. Keamanan

| Area | Kendali |
|---|---|
| Autentikasi | Password minimal 8 karakter + cek password bocor (opsional), throttle login, lockout sementara |
| Otorisasi | Middleware role/permission di setiap route + Policy untuk kepemilikan data |
| Area anggota | Middleware `EnsureIsMember` — memblokir pengurus tanpa status keanggotaan ✅ |
| Form publik | Rate limit per IP, honeypot field, captcha ringan (hCaptcha/Turnstile gratis) ⚠️ perlu akun |
| Upload | Validasi mime & ukuran, nama file diacak, file sensitif di disk `local` (tidak URL-able) |
| Data sensitif | NIM/HP/email/tanggal lahir tidak pernah tampil publik; bersihkan PII di isi aspirasi |
| Kartu kader | Token UUID rahasia; halaman verifikasi hanya menampilkan field aman |
| Audit | `activity_log` pada aksi: verifikasi, ubah status, void transaksi, hapus konten, ubah role |
| Backup | Database harian + retensi 14 hari, uji restore sebelum go-live |
| Header | CSP ringan, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |
| 2FA ⚠️ | Usulan: wajib untuk Superadmin, opsional untuk pengurus lain |

## 11. Performa

| Teknik | Detail |
|---|---|
| Query | Eager loading, `select` kolom yang perlu, pagination server-side (bukan load semua) |
| Cache | `site_settings`, navigasi, halaman statis, statistik beranda (TTL 5–60 menit) |
| Gambar | Konversi WebP, thumbnail (96/480/1600 px), lazy load, `srcset` |
| Pencarian | FULLTEXT MySQL untuk artikel & buku; pencarian sederhana pakai `LIKE` pada kolom ber-index |
| Aset | Build Vite (cache busting), kompresi, hindari library besar; chart & peta dimuat *lazy* |
| Export berat | Selalu lewat queued job + notifikasi setelah selesai |

## 12. Testing

| Jenis | Cakupan minimal |
|---|---|
| Feature test | Pendaftaran → verifikasi → login; akses ditolak antar role; pendaftaran event + kuota; pinjam buku + antrian; void transaksi; publikasi artikel |
| Unit test | Helper nomor anggota/voucher, perhitungan poin, logika antrian reservasi |
| Uji manual per fase | Checklist di `05-roadmap-fase.md` + uji di layar 360px |
| Uji sebelum rilis | Semua alur `03-alur-bisnis.md` di server produksi + cek backup/restore |

## 13. Menjalankan di Lokal

> ✅ Keputusan: pengembangan memakai **Docker Compose** agar versi PHP & mesin basis data
> **sama dengan produksi**, dan agar masalah "di laptop saya jalan" tidak terjadi.
> Tanpa Docker pun aplikasi tetap bisa dijalankan (memakai SQLite) — Docker bukan ketergantungan mati.

### Dengan Docker (cara utama)

```bash
docker compose up -d          # app + db + mailpit + queue + scheduler
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
docker compose logs -f app    # lihat log
```

| Layanan | Alamat |
|---|---|
| Situs | http://localhost:8100 |
| Basis data | `localhost:3309` — `raab` / `rahasia` |
| Email masuk (Mailpit) | http://localhost:8025 (SMTP `localhost:1025`) |

> **Kenapa 8100, bukan 8000?** Pada mesin pengembangan ini port 8000 sempat tertinggal pada
> daftar port-forward editor dan tidak lagi dapat dijangkau. Port 8100 bersih — dan tetap
> bisa diubah hanya dari `docker-compose.yml` bila diperlukan.

**Catatan penting:**
- Port DB memakai **3309** karena 3308 sudah dipakai proyek lain di mesin ini.
- `APP_UID` / `APP_GID` di `.env` disetel ke UID pengguna host (**1002**) agar berkas yang
  dibuat container (cache, log, media) tetap bisa ditulis dari sisi host.
- Aset frontend dibangun dari host: `npm run build` (sekali) atau `npm run dev` (selama mengembangkan).
  Berkas `public/hot` ikut terlihat container karena proyek di-*bind mount*.
- Email pengembangan **ditangkap Mailpit** — tidak pernah terkirim ke alamat asli. Ini penting
  untuk menguji alur verifikasi email & reset kata sandi tanpa mengganggu orang lain.
- Scheduler dijalankan service `scheduler` (`php artisan schedule:work`) — meniru cron di produksi.
- **Ekstensi `gd`/`intl`/`zip` bersifat best effort.** Bila repositori apt tidak dapat dijangkau
  saat build, ketiganya dilewati dan **build tetap berhasil**. Dampaknya hanya pada konversi
  gambar/thumbnail; unggah berkas tetap berjalan. Di server produksi yang dapat menjangkau apt,
  ketiganya otomatis terpasang tanpa perubahan apa pun.

### Tanpa Docker (cadangan)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# ubah DB_CONNECTION=sqlite pada .env
php artisan migrate --seed
npm run dev
php artisan serve
```

## 14. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Hosting tidak mendukung PHP 8.2 atau ekstensi wajib | Aplikasi tidak jalan | Cek spesifikasi hosting **sebelum Fase 1**; siapkan 2–3 alternatif penyedia murah |
| Symlink storage diblokir | Gambar tidak tampil | Fallback upload ke `public/uploads` |
| Email masuk spam | Anggota tidak bisa verifikasi | SPF/DKIM + domain resmi; sediakan opsi verifikasi manual oleh Sekretaris ⚠️ |
| Email gratis kena limit harian | Verifikasi tertunda | Antrean + template ringkas; siap jalur verifikasi manual |
| Tanpa SSH | Deploy lambat & rawan salah | Dokumentasi langkah + checklist; pertimbangkan upgrade |
| Data lama tidak sinkron | Data ganda alumni | Fitur pencocokan saat pendaftaran alumni + alat penggabungan data ⚠️ |
| Buku/aset hilang tanpa jejak | Kerugian | Wajib serah terima + kondisi keluar/masuk + penanggung jawab untuk eksternal |
| Kebocoran data anggota | Masalah privasi | Resource terpisah, uji manual menyeluruh, permission ketat, log akses |
| Tim berganti pengurus | Sulit dipelihara | Dokumentasi pengguna per role + struktur kode konsisten |
| **Bilingual** menambah beban penulisan konten | Versi Inggris terbengkalai | Indikator kelengkapan terjemahan + filter "belum diterjemahkan"; versi EN opsional dengan fallback |
| **Free tier**: aplikasi tidur saat idle | Pengunjung pertama menunggu 30–60 detik | Halaman pemuatan yang ramah + ping berkala dari cron-job.org |
| **Free tier**: kuota DB/email terbatas | Error atau verifikasi tertunda | Pagination & cache wajib; jalur verifikasi manual oleh Sekretaris; siap pindah ke hosting berbayar |
| **Free tier**: tanpa backup otomatis | Data hilang permanen | Halaman **Ekspor Cadangan** untuk Superadmin + pengingat unduh bulanan |
