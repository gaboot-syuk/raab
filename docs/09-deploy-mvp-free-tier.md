# 09 — Deploy MVP di Free Tier

> **Fase 0 — Dokumen Spesifikasi.** Status: *menunggu review*.
> ✅ Konfirmasi kamu: **belum ada domain & hosting**, MVP akan dijalankan di **free tier** dulu.
> ⚠️ Semua kuota/layanan gratis di bawah **perlu diverifikasi ulang saat Fase 9** — penyedia sering mengubah kebijakannya.

---

## 1. Kesimpulan Penting: Hosting Belum Dibutuhkan Sekarang

| Fase | Butuh hosting? |
|---|---|
| 1 – 8 | ❌ **Tidak.** Semua dikembangkan & diuji di komputermu (localhost) |
| 9A | ✅ Ya — deploy MVP ke free tier untuk demo & uji |
| 9B | ✅ Ya — pindah ke hosting berbayar murah saat rilis resmi |

Artinya, jawaban "belum ada hosting" **tidak memblokir Fase 1**. Yang perlu saya pastikan hanya: komputermu bisa menjalankan PHP 8.2+, Composer, Node.js, dan MySQL (atau MariaDB). Saya akan cek saat Fase 1 dimulai.

---

## 2. Pilihan Free Tier ⚠️

### Perbandingan

| Jalur | Tumpukan | Biaya | Kelebihan | Kekurangan |
|---|---|---|---|---|
| **A. PaaS + DB terpisah** | Render Free (Docker) + TiDB Cloud Serverless (MySQL) + Cloudflare R2 (media) + Brevo (email) | Rp0 | Mirip produksi, deploy dari Git, HTTPS otomatis | Cold start 30–60 detik setelah idle, tanpa cron bawaan, disk sementara |
| **B. Free PHP hosting** | InfinityFree / sejenis (PHP + MySQL) | Rp0 | Paling dekat cara kerja hosting biasa, tanpa Docker | Tanpa SSH, unggah lewat FTP, MySQL kecil, performa rendah, akun gratis bisa dihapus tanpa peringatan |
| **C. Hosting murah berbayar** (target rilis) | Shared hosting lokal ±Rp15–30rb/bulan, atau VPS kecil | ±Rp180–400rb/tahun | Stabil, ada SSH/cron, cepat, bisa `storage:link` | Tidak gratis |

### Rekomendasi saya

1. **Untuk MVP/demo → Jalur A.** Paling aman dari sisi kode dan paling mirip produksi, sehingga perpindahan ke Jalur C nanti hanya soal konfigurasi.
2. **Jalur B hanya bila kamu ingin melihat situs "seperti hosting biasa" tanpa Docker** — dan sadari risikonya (data bisa hilang bila akun gratis bermasalah).
3. **Untuk rilis resmi (Fase 9B) → Jalur C.** Dengan ±Rp15rb/bulan, hampir semua kekurangan free tier hilang. Sangat layak untuk website organisasi.

### Catatan penyedia (perlu diverifikasi saat Fase 9)

| Kebutuhan | Pilihan gratis | Catatan |
|---|---|---|
| Hosting PHP ber-kontainer | Render Free, Koyeb Free | Baca batas jam & kebijakan idle |
| MySQL | TiDB Cloud Serverless (kompatibel MySQL), Aiven Free | Batas penyimpanan & koneksi |
| Penyimpanan berkas | Cloudflare R2 (10 GB), Backblaze B2 | Karena disk free tier bersifat sementara |
| Email transaksional | Brevo (kuota harian gratis), Resend | Wajib verifikasi pengirim; tanpa domain, keandalan menurun |
| Cron eksternal | cron-job.org, UptimeRobot | Memanggil URL penjadwal kita tiap 5 menit |
| Domain | Subdomain gratis penyedia dulu; nanti `.my.id` (±Rp15rb/tahun) atau `.or.id` | Bisa menyusul |

---

## 3. Konsekuensi Arsitektur (diputuskan sekarang)

Supaya perpindahan hosting nanti **tanpa menulis ulang kode**, saya kunci dari Fase 1:

| # | Keputusan | Alasan |
|---|---|---|
| K1 | **Penyimpanan berkas lewat abstraksi `disk`** — `local` saat dev, `s3` (R2/B2) saat free tier, `public` saat hosting biasa | Disk free tier bersifat sementara; file hilang saat deploy ulang |
| K2 | **Berkas privat tidak pernah punya URL langsung** — selalu diunduh lewat route ber-proteksi (`/berkas/{media}/unduh`) | Keamanan: sertifikat, bukti transfer, laporan keuangan |
| K3 | **Berkas publik disajikan lewat route `/media/{media}/{nama}`** bila symlink tidak tersedia | Banyak hosting murah/free memblokir `storage:link` |
| K4 | **`QUEUE_CONNECTION=sync` untuk MVP**, target `database` di hosting berbayar | Tanpa worker permanen di free tier, email tetap terkirim |
| K5 | **Endpoint penjadwal**: `GET /internal/scheduler/{token}` → menjalankan `schedule:run`, dilindungi token dari `.env` | Free tier tidak punya cron; dipanggil dari cron-job.org tiap 5 menit |
| K6 | **`SESSION_DRIVER=database` & `CACHE_STORE=database`** | Sesi & cache tidak hilang saat kontainer di-deploy ulang |
| K7 | **Konfigurasi lewat `.env`** — tidak ada nilai rahasia atau URL yang ditulis di kode | Pindah hosting = ganti `.env`, bukan ganti kode |
| K8 | **Migrasi & seeder otomatis saat deploy** (dikendalikan variabel `.env`) | Deploy tanpa SSH |
| K9 | **Aset disajikan server web statis** (hasil `npm run build` ikut ke dalam kontainer) | Cepat & hemat |
| K10 | **Cadangan manual terjadwal**: halaman "Ekspor Cadangan" khusus Superadmin (unduh `.sql` + media terpilih) | Free tier tidak menyediakan backup otomatis |
| K11 | **Semua identitas situs dari `site_settings`**, bukan hardcode | Karena alamat & detail lain masih placeholder |
| K12 | **Aplikasi tidak bergantung pada ekstensi langka** (tanpa Redis, tanpa Imagick wajib, tanpa FFmpeg) | Agar jalan di semua free tier |
| K13 | **Bahasa & tema dari pengaturan, bukan hardcode** — `APP_LOCALE=id`, `APP_FALLBACK_LOCALE=en`, tema default `sistem` | Mudah dipindah & diubah tanpa sentuh kode |
| K14 | **Halaman publik server-rendered (Blade)** 🆕 | SEO bagus & tetap cepat di free tier **tanpa** SSR; tidak bergantung pada JavaScript pengunjung |

| K15 | **Pengembangan & produksi sama-sama memakai Docker** 🆕 | Berkas `docker-compose.yml` (dev: app + MariaDB + Mailpit + queue + scheduler) dan image prod akan memakai basis Dockerfile yang sama — risiko "beda lingkungan" hilang |

---

## 4. Rencana Fase 9 — Dua Tahap

### Fase 9A — Deploy MVP ke Free Tier

```text
1. Tambah Dockerfile (multi-tahap):
   a. Node: npm ci && npm run build          → hasil public/build
   b. PHP : install composer, vendor (tanpa dev), copy hasil build
   c. Runtime: nginx + php-fpm + supervisord, mendengarkan $PORT
2. Siapkan bucket Cloudflare R2 + kredensial S3-compatible → .env
3. Siapkan database TiDB Cloud + kredensial → .env
4. Siapkan pengirim Brevo + verifikasi alamat pengirim → .env
5. Hubungkan repo ke Render → deploy otomatis dari Git
6. Set variabel lingkungan produksi (APP_ENV, APP_KEY, APP_URL, dsb.)
7. Migrasi + seeder role/permission/pengaturan (sekali)
8. Buat cron-job.org → panggil /internal/scheduler/{token} tiap 5 menit
9. Uji checklist fungsi inti di server
10. Catat: aplikasi akan "tidur" setelah idle → akses pertama lambat
```

### Fase 9B — Pindah ke Hosting Berbayar (saat siap rilis)

```text
1. Pilih shared hosting murah / VPS kecil, pastikan: PHP 8.2+, MySQL, cron, SSH (bila bisa)
2. Daftarkan domain (.my.id / .or.id) + email resmi (mis. sekretariat@domain)
3. Pasang SPF + DKIM di DNS → email tidak masuk spam
4. Pindahkan database (impor hasil cadangan) & berkas media
5. Ubah .env: FILESYSTEM_DISK, MAIL, APP_URL, QUEUE_CONNECTION=database
6. Aktifkan cron server (* * * * * php artisan schedule:run) → endpoint penjadwal bisa dinonaktifkan
7. Aktifkan Symlink /storage (atau tetap pakai route /media)
8. Uji menyeluruh + cadangan otomatis harian
```

---

## 5. Checklist Uji di Server (Fase 9A)

- [ ] Beranda, Sejarah, Visi & Misi, Sambutan tampil dengan gambar & font yang benar
- [ ] Login Superadmin berhasil; halaman admin terbuka
- [ ] Ubah pengaturan situs → langsung tampil di footer
- [ ] Unggah gambar di media library → tersimpan di R2 dan tampil
- [ ] Pendaftaran anggota → email verifikasi benar-benar terkirim
- [ ] Pendaftaran event Mapaba → data masuk & bisa diekspor
- [ ] Pinjam buku & pengajuan peminjaman berjalan
- [ ] Catat transaksi keuangan & laporan bisa dibuka
- [ ] Berkas sensitif (bukti transfer) **tidak bisa** diakses tanpa login
- [ ] Halaman di HP (360px) rapi & tidak ada scroll horizontal
- [ ] Endpoint penjadwal dipanggil cron-job.org → berhasil (cek log)
- [ ] Cadangan `.sql` berhasil diunduh & bisa dipulihkan di lokal
- [ ] Ukur: halaman publik < 2,5 detik (setelah "bangun" dari idle)

---

## 6. Risiko Free Tier & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Aplikasi "tidur" saat idle (cold start 30–60 detik) | Pengunjung pertama menunggu lama | Pasang halaman pemuatan yang ramah; ping berkala dari cron-job.org |
| Berkas hilang saat deploy ulang | Kehilangan gambar/dokumen | Wajib pakai penyimpanan objek (R2/B2) sejak Fase 9A |
| Tanpa cron bawaan | Artikel terjadwal, pengingat, kedaluwarsa reservasi tidak jalan | Endpoint penjadwal + cron-job.org tiap 5 menit |
| Kuota email harian terbatas | Verifikasi pendaftar tertunda | Antrean + template ringkas + **jalur verifikasi manual oleh Sekretaris** (sudah dirancang) |
| Tanpa SSH | Sulit memperbaiki masalah di server | Deploy dari Git (otomatis), migrasi otomatis, halaman diagnostik khusus Superadmin |
| Kuota database/koneksi terbatas | Error saat pemakaian bersamaan | Query ringan, pagination wajib, cache pengaturan |
| Layanan gratis berubah/ditutup | Situs mati mendadak | Rencana Jalur C sudah siap; cadangan rutin diunduh manual |
| Tanpa backup otomatis | Data hilang permanen | Halaman Ekspor Cadangan + pengingat bulanan untuk Superadmin |
| DNS/domain belum ada | Email kurang andal, tautan kurang meyakinkan | Pakai subdomain penyedia dulu; segera ambil domain saat rilis |
| Performa lambat | Pengalaman buruk | Optimasi gambar, cache, hindari library berat, rajinlah ukur |

---

## 7. Strategi Placeholder (karena belum ada data & identitas resmi)

Kamu meminta detail identitas memakai placeholder ✅. Rencana saya:

| Hal | Placeholder | Cara ganti nanti |
|---|---|---|
| Alamat sekretariat | `Sekretariat PMII RAAB — [alamat belum diisi]` | Ubah di Pengaturan Situs (tanpa sentuh kode) |
| Titik Google Maps | Koordinat UIN Raden Mas Said Surakarta sebagai titik awal | Ubah di Pengaturan Situs |
| Jam operasional | `Senin–Jumat, 09.00–16.00 WIB` | Ubah di Pengaturan Situs |
| Email & telepon | `sekretariat@example.com`, `08xx-xxxx-xxxx` | Ubah di Pengaturan Situs |
| Media sosial | Tautan `#` dengan label platform | Ubah di Pengaturan Situs |
| Logo | ✅ Sudah ada: `logo-pmii-raab.png` | Saya pindahkan ke `public/brand/`; favicon & OG image dibuat placeholder dari logo itu |
| Data anggota/alumni/prestasi lama | Data contoh (untuk MVP) + alat impor CSV | Impor saat datanya siap |
| Riwayat keuangan | Saldo awal = Rp0 | Bendahara mengisi saldo awal |

> Pendekatan ini memastikan halaman tidak pernah menampilkan teks kosong/`null` yang memalukan saat demo.

---

## 8. Keputusan Deploy

| # | Pertanyaan | Default bila tidak dijawab |
|---|---|---|
| D1 | ✅ **Setuju Jalur A** — kamu akan mencobanya sendiri lalu menyesuaikan | Konfigurasi Jalur A disiapkan lengkap |
| D2 | ⏳ **Belum punya akun** — panduan pendaftaran disiapkan saat Fase 9A | Saya buatkan panduan + `Dockerfile` + daftar variabel `.env` |
| D3 | ✅ **Diserahkan ke saya** | Rancangan tetap bisa gratis; biaya rilis (hosting ±Rp15–30rb/bulan + domain `*.my.id` ±Rp15rb/tahun) **ditawarkan hanya saat kamu siap** — tidak ada kewajiban |
| D4 | ✅ **Diserahkan ke saya** | Panduan deploy berupa teks + daftar periksa (tanpa tangkapan layar, karena antarmuka penyedia sering berubah) |
| D5 | ✅ **Diserahkan ke saya** | Deploy percobaan **setelah Fase 4**, agar kamu bisa melihat hasilnya online lebih awal |
