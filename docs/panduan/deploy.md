# Panduan Deploy & Pembaruan

Panduan ini untuk orang yang memasang dan merawat situs ini di server. Bukan
panduan pemakaian harian — itu ada di panduan per peran.

Pilihan penyedia untuk rilis pertama dibahas di `../09-deploy-mvp-free-tier.md`.
Berkas ini membahas **langkah-langkahnya**, bukan pilihannya.

---

## 1. Yang dibutuhkan

| Kebutuhan | Catatan |
|---|---|
| PHP 8.3 atau lebih baru | Diuji pada 8.4 |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `intl`, `zip`, `xml`, `curl` |
| Node 22 | Hanya untuk membangun aset depan |
| Composer 2 | |
| Basis data | MySQL/MariaDB (produksi) atau SQLite (pengujian) |
| Surat keluar | SMTP dari penyedia (Brevo, Mailgun, Resend) |
| Penjadwal | Cron, ATAU layanan penjadwal yang memanggil alamat pemicu |

**Ekstensi `gd` atau `imagick` wajib ada di produksi.** Tanpa keduanya, gambar
yang diunggah tidak diperkecil: kader dengan kuota internet terbatas akan
mengunduh foto berukuran penuh di setiap halaman. Lingkungan pengembangan tidak
memiliki ekstensi ini, sehingga hal ini mudah terlewat saat pindah ke server.

---

## 2. Cara A — Docker (disarankan)

Ada `Dockerfile` di akar proyek. Citranya berisi nginx, php-fpm, dan pekerja
antrean dalam satu wadah, karena hosting gratis umumnya hanya memberi satu
wadah.

```sh
# 1. Siapkan berkas konfigurasi
cp .env.production.example .env
#    Isi setiap nilai bertanda WAJIB DIISI.
#    Buat APP_KEY:       php artisan key:generate --show   (lalu tempel hasilnya)
#    Buat SCHEDULER_TOKEN: openssl rand -hex 32

# 2. Bangun dan jalankan
docker build -t raab .
docker run -d --name raab --env-file .env -p 8080:8080 raab

# 3. Periksa
curl http://localhost:8080/up
```

Port mengikuti variabel `PORT` dari penyedia. Wadahnya memeriksa `/up` setiap
30 detik; wadah yang hidup tetapi tidak bisa melayani permintaan akan dianggap
tidak sehat dan diganti penyedia.

### Yang dilakukan wadah saat dinyalakan

Urutannya ada di `docker/prod/masuk.sh`, dan urutan itu disengaja:

1. Menulis konfigurasi nginx dengan port yang benar.
2. **Menunggu** basis data siap — wadah aplikasi dan basis data biasanya
   menyala bersamaan, dan basis datanya selalu siap belakangan.
3. Menjalankan migrasi (`APP_JALANKAN_MIGRASI=false` untuk mematikannya).
4. Menyiapkan cache konfigurasi, rute, dan tampilan.
5. Menyediakan tautan penyimpanan publik.
6. Menjalankan nginx, php-fpm, dan antrean.

Langkah 2–5 dijalankan saat wadah **dinyalakan**, bukan saat citra dibangun,
karena alamat basis data baru tersedia saat itu.

---

## 3. Cara B — Tanpa Docker (VPS biasa)

```sh
# 1. Ambil kode
git clone <repo> /var/www/raab && cd /var/www/raab

# 2. Pustaka PHP
composer install --no-dev --optimize-autoloader

# 3. Konfigurasi
cp .env.production.example .env
php artisan key:generate
#    Isi .env, lalu:
php artisan migrate --force

# 4. Aset depan
npm ci && npm run build

# 5. Cache produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# 6. Izin folder
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

Arahkan akar dokumen server web ke `public/`. **Jangan** ke akar proyek:
`.env` akan bisa diunduh siapa saja.

---

## 4. Penjadwal — bagian yang paling sering terlewat

Ada dua perintah terjadwal:

| Perintah | Kapan | Gunanya |
|---|---|---|
| `pinjaman:pengingat` | harian | Mengingatkan peminjaman yang jatuh tempo |
| `cadangan:buat` | 02:30 WIB | Membuat cadangan basis data harian |

### Kalau servernya punya cron

```
* * * * * cd /var/www/raab && php artisan schedule:run >> /dev/null 2>&1
```

### Kalau servernya TIDAK punya cron

Ini keadaan hosting gratis. Sudah disediakan alamat pemicu:

```
GET https://ALAMAT-SITUS/internal/scheduler/ISI-TOKEN-NYA
```

Daftarkan alamat itu di layanan penjadwal (mis. cron-job.org) untuk dipanggil
**setiap 5 menit**. Jawabannya berbentuk JSON:

```json
{ "dijalankan": ["cadangan:buat"], "galat": {}, "waktu": "2026-09-26T21:00:00+07:00" }
```

Hal-hal penting:

- `SCHEDULER_TOKEN` **wajib diisi**. Selama kosong, alamat ini menjawab 404 —
  fitur yang belum dikonfigurasi memang harus mati, bukan terbuka.
- Token dibandingkan dengan `hash_equals`, jadi tidak bisa ditebak lewat waktu
  balasan.
- Alamatnya dibatasi 12 panggilan per menit. Penjadwal setiap 5 menit jauh di
  bawah batas itu.
- Kalau ada perintah yang gagal, jawabannya 500 dan nama perintahnya muncul di
  `galat`. Perintah lain tetap dijalankan — satu kegagalan tidak menahan
  sisanya.

### Memastikan penjadwal benar-benar berjalan

Buka **Panel → Diagnostik** sebagai Superadmin:

- Bagian **Penjadwal** menunjukkan perintah yang terdaftar. "Terdaftar" belum
  berarti "berjalan".
- Bagian **Penyimpanan** menunjukkan cadangan terakhir beserta waktunya. Kalau
  tanggalnya lebih tua dari dua hari, penjadwalnya tidak berjalan.

---

## 5. Antrean

Aplikasi mengirim surat lewat antrean. Pilihannya dua:

**`QUEUE_CONNECTION=database`** (disarankan untuk produksi). Pekerja antrean
harus berjalan. Di citra Docker, pekerja antrean sudah ikut berjalan di dalam
wadah. Tanpa Docker, jalankan dengan supervisor:

```ini
[program:raab-queue]
command=php /var/www/raab/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```

**`QUEUE_CONNECTION=sync`** — surat dikirim saat itu juga, tidak perlu pekerja
antrean. Cocok untuk free tier yang hanya memberi satu proses. Konsekuensinya
jelas: pengunjung menunggu sampai suratnya benar-benar terkirim.

Kalau pengiriman gagal, kegagalannya tercatat di **log** dan di tabel
`failed_jobs`, dan jumlahnya terlihat di halaman Diagnostik.

---

## 6. Cadangan & pemulihan

### Membuat

- Otomatis: setiap 02:30 WIB (lihat bagian Penjadwal).
- Manual: **Panel → Cadangan Basis Data → Buat Cadangan Sekarang**, atau
  `php artisan cadangan:buat`.

Cadangan disimpan di `storage/app/private/cadangan/`, dan hanya 14 berkas
terbaru yang disimpan — sisanya dibuang otomatis.

### Yang wajib diingat

**Cadangan yang hanya ada di server yang sama belum disebut cadangan.**
Unduh berkasnya secara berkala dan simpan di tempat lain. Kalau servernya
hilang, cadangan yang tersimpan di sana ikut hilang.

### Memulihkan

Pemulihan **menghapus seluruh data yang ada sekarang**, jadi tidak disediakan
lewat tombol di panel.

```sh
php artisan cadangan:daftar
php artisan cadangan:pulihkan cadangan-2026-09-26-023000.sql
#    Perintah ini menuntut nama berkasnya diketik ulang.
#    Keadaan sekarang dicadangkan lebih dulu secara otomatis.
php artisan cache:clear
```

`cache:clear` di akhir bukan formalitas: pengaturan situs disimpan di cache,
dan tanpa membersihkannya, panel akan menampilkan pengaturan dari basis data
yang sudah tidak ada.

### Bukti bahwa pemulihan benar-benar bekerja

Ada uji otomatisnya (`tests/Feature/CadanganTest.php`). Ujinya membuat data
berisi tanda kutip, titik koma, dan baris baru, mencadangkan, menghapus
datanya, memulihkan, lalu memastikan isinya kembali **persis sama** — bukan
hanya jumlah barisnya.

---

## 7. Memperbarui aplikasi

```sh
# 1. Cadangkan lebih dulu. Ini bukan saran, ini urutannya.
php artisan cadangan:buat

# 2. Ambil versi baru
git pull

# 3. Pustaka & aset
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 4. Migrasi
php artisan migrate --force

# 5. Bersihkan cache LAMA, lalu bangun yang baru
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Nyalakan ulang pekerja antrean
php artisan queue:restart
```

`queue:restart` pada langkah 6 bukan pilihan. Pekerja antrean menahan kode lama
selama ia hidup; tanpa memulai ulang, surat akan tetap dikirim memakai kode
versi lama sampai pekerja itu berhenti sendiri.

**Urutan langkah 1 dan 4 tidak boleh dibalik.** Migrasi yang menghapus kolom
tidak bisa dibatalkan.

---

## 8. Memeriksa kesehatan setelah deploy

Jalankan berurutan:

```sh
# 1. Aplikasi menjawab
curl -I https://ALAMAT-SITUS/up

# 2. Tidak ada rute yang terbuka tanpa hak
php artisan audit:izin

# 3. Peta situs benar
curl -s https://ALAMAT-SITUS/sitemap.xml | head -20

# 4. Halaman publik memuat
curl -s -o /dev/null -w "%{http_code}\n" https://ALAMAT-SITUS/

# 5. Panel menolak tamu
curl -s -o /dev/null -w "%{http_code}\n" https://ALAMAT-SITUS/panel
#    Harus 302 (dialihkan ke halaman masuk), bukan 200
```

`audit:izin` memeriksa tiga hal: rute GET tanpa autentikasi yang tidak
seharusnya publik, rute panel tanpa izin, dan izin yang disebut tanpa
autentikasi. Keluarannya harus "Tidak ada temuan" dengan kode keluar 0.

Lalu buka **Panel → Diagnostik** dan periksa:

- [ ] **Mode debug** — harus `Tidak`
- [ ] **Basis data tersambung** — harus `Ya`
- [ ] **Cadangan terakhir** — harus lebih baru dari dua hari
- [ ] **Gagal** (antrean) — harus `0`
- [ ] **Galat hari ini** — harus `0`
- [ ] **Penjadwal** — semua harus `Ya`

---

## 9. Daftar periksa sebelum diumumkan ke publik

- [ ] `APP_DEBUG=false` — **paling penting**
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` dibuat khusus untuk server ini, bukan disalin dari laptop
- [ ] `APP_URL` memakai `https://` yang benar
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SCHEDULER_TOKEN` diisi dan penjadwalnya sudah dipanggil terjadwal
- [ ] `audit:izin` bersih
- [ ] Cadangan dibuat **dan diunduh ke luar server**
- [ ] Pemulihan pernah diuji di tempat lain, bukan hanya dipikirkan
- [ ] Surat uji benar-benar sampai (bukan hanya "terkirim")
- [ ] Satu akun per peran sudah diuji: Superadmin, Sekretaris, Bendahara, Konten
      Manager, dan satu kader
- [ ] Halaman 404 dan 500 tampil rapi, bukan halaman putih
- [ ] Nomor telepon dan alamat di **Pengaturan → Situs** sudah benar
- [ ] Surat pengguna (SPF & DKIM) sudah dipasang di penyedia email, kalau
      memakai domain sendiri

---

## 10. Kalau ada yang salah

| Gejala | Periksa |
|---|---|
| Halaman putih | `storage/logs/laravel.log`; mode debug penyebabnya |
| "500 Server Error" terus | Diagnostik → Galat hari ini |
| Email tidak sampai | Diagnostik → Gagal; cek juga spam dan SPF/DKIM |
| Cadangan tidak muncul | Penjadwal: apakah alamat pemicunya benar-benar dipanggil? |
| Gambar tidak muncul | `php artisan storage:link` sudah dijalankan? |
| Perubahan tidak terlihat | `php artisan optimize:clear` lalu bangun lagi |
| Terlempar keluar terus | `SESSION_DRIVER` harus `database`, bukan `file` |
| Panel menampilkan data lama | `php artisan cache:clear` — pengaturan situs ada di cache |
