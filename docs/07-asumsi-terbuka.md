# 07 — Asumsi & Hal yang Perlu Kamu Jawab

> **Fase 0 — Dokumen Spesifikasi.** Ini dokumen **terpenting untuk kamu review**.
> Cara pakai: baca daftar di bawah, lalu jawab dengan menuliskan **kode** (contoh: *"A1 benar, A2 salah — Sarekat itu…, Q3 pakai logo ini"*).

---

## Bagian 1 — Keputusan yang Sudah Final ✅

| # | Keputusan |
|---|---|
| 1 | Stack: Laravel 12 + Inertia v2 + Vue 3 (TS) + Tailwind v4 + MySQL |
| 2 | Publikasi = **satu model artikel + kategori**, 5 tipe + berita acara/pers release |
| 3 | Inventaris = **manajemen penuh**, dan buku memakai sistem **perpustakaan** (pinjam, perpanjang, reservasi/antrian), **tanpa denda** |
| 4 | Halaman publik Inventarisasi **tetap ada** (read-only katalog) |
| 5 | Keuangan = **internal saja** (Bendahara + Superadmin) |
| 6 | Berita acara = tipe artikel khusus, Sekretaris boleh publish langsung |
| 7 | Role organisasi **terpisah** dari status keanggotaan; pengurus **harus mendaftar** untuk dapat akses anggota |
| 8 | Kader & alumni = **satu record orang**, status naik (calon → kader_aktif → alumni) |
| 9 | Verifikasi **dua gerbang**: verifikasi email dulu → verifikasi Sekretaris |
| 10 | Pengurus yang mendaftar anggota → **auto-verifikasi** |
| 11 | Struktur pengurus = **multi-periode** (riwayat) |
| 12 | Peminjam aset = kader aktif + alumni + **pihak luar via Sekretaris** (wajib penanggung jawab internal) |
| 13 | Aspirasi = wajib identitas, **tampil publik tanpa identitas** |
| 14 | Peminjaman buku & aset = kader aktif + alumni |
| 15 | Email via Brevo/Resend |
| 16 | Dibangun **per fase**, Fase 0 (dokumen) sekarang |
| 17 | Semua fitur dashboard yang kamu centang ✅ masuk lingkup (dibagi ke Fase 2, 8, dan sebagian 4) |
| 18 | **Sarekat LSO** = 5 LSO (Mutasi, Harokatuna, LDR, LPM Albiruni, MJT) — masing-masing punya pengurus & halaman sendiri ✅ |
| 19 | Komisariat **PMII Komisariat Raden Mas Said**, kampus **UIN Raden Mas Said Surakarta** ✅ |
| 20 | Desain **neo-brutalism**, biru + kuning, **mobile-first**, interaktif ✅ |
| 21 | Periode **Masa Juang 1 tahun**, dikelola **hanya Superadmin**, riwayat mulai **2017** ✅ |
| 22 | Hosting MVP di **free tier** ✅ |
| 23 | Alumni dapat memberi **hibah dana/barang/jasa** — internal saja ✅ |
| 24 | Data lama **ada**, tetapi MVP memakai **placeholder dulu** ✅ |
| 25 | Hex warna, font, dan **mode gelap** disetujui ✅ |
| 26 | **Bilingual Indonesia–Inggris**: halaman publik saja ✅ |
| 27 | **Galeri & agenda LSO** dikelola **Konten Manager** ✅ |
| 28 | Animasi **halus saja** ✅ |
| 29 | **Nomor anggota acak unik** — `RAAB-{tahun periode}-{6 digit acak}`, dimulai dari periode sekarang ✅ |
| 30 | **Iuran berkategori**: anggota aktif, pengurus, alumni — diatur Bendahara ✅ |
| 31 | **2FA opsional** untuk semua pengurus ✅ |
| 32 | Jumlah buku & data lain: **placeholder** dulu ✅ |
| 33 | Deploy MVP **Jalur A** (Render + TiDB + R2 + Brevo) — akan dicoba sendiri lalu disesuaikan ✅ |
| 34 | Frontend: **Blade + Tailwind** untuk halaman publik (server-rendered) + **Inertia + Vue 3 (TS)** untuk admin/dashboard ✅ |
| 35 | Istilah resmi: **Biro**, **Kepala Biro (Kabiro)** — bukan Divisi/Kadiv ✅ |
| 36 | Bilingual: **terjemahan otomatis saat simpan** (adaptor DeepL/Google/none) + boleh disunting ✅ |
| 37 | Halaman publik wajib berfungsi tanpa JavaScript ✅ |
| 38 | Terjemahan otomatis: **artikel & halaman** otomatis; **event, galeri, agenda, LSO** manual ✅ |
| 39 | Logo: **satu berkas** `logo-pmii-raab.png`; versi putih via filter CSS; aset lain placeholder ✅ |
| 40 | **Spesifikasi dikunci & disetujui** (26 Sep 2026) ✅ |

---

## Bagian 2 — Asumsi Saya ⚠️ (tolong dikonfirmasi)

### A. Konteks & Penamaan

| # | Asumsi saya | Bila salah, dampaknya |
|---|---|---|
| A1 | "PMI" = **PMII** | ✅ **DIKONFIRMASI** |
| A2 | **RAAB** = Rayon Ali Ahmad Baktsir | ✅ **DIKONFIRMASI** |
| A3 | Mapaba / PKD / LSO | ✅ **DIKONFIRMASI** |
| A4 | "Sarekat LSO" = label menu untuk kluster **5 LSO** | ✅ **DIKONFIRMASI** (label menu bisa diganti) |
| A5 | Satuan di atas rayon = **Komisariat** — PMII Komisariat Raden Mas Said | ✅ **DIKONFIRMASI** |
| A6 | Nomor anggota `RAAB-2026-001` | Masih perlu persetujuan format (Q12) |
| A7 | Bahasa tunggal: Indonesia | Perlu keputusan bila ingin multibahasa |
| A8 | Menu publik **"Perpustakaan"** ditambahkan | Masih perlu persetujuan |
| A9 | "Wakil" dibaca **Wakil Ketua**; "Sekertaris" ditulis **Sekretaris** 🆕 | Mohon koreksi bila salah |
| A10 | **LPM Albiruni** = Lembaga Pers Mahasiswa 🆕 | Mohon koreksi kepanjangannya |
| A11 | **Hibah** tidak punya halaman publik 🆕 | Bisa ditambah bila diinginkan |

### B. Aturan Bisnis yang Saya Tetapkan Sendiri

| # | Asumsi saya | Alternatif |
|---|---|---|
| B1 | 1 email = 1 akun; NIM unik | Perlu pengecualian? |
| B2 | Link verifikasi email berlaku **60 menit** | Bisa diperpanjang |
| B3 | Penolakan verifikasi **wajib** disertai alasan (min. 20 karakter) | — |
| B4 | Ada status **`perlu_perbaikan`** (Sekretaris minta pendaftar melengkapi data) | Bisa dihapus dari alur bila terlalu rumit |
| B5 | Kader → alumni: akses kartu, presensi, poin, iuran dicabut; pinjam buku & aset tetap | Perlu keputusan |
| B6 | Alumni tidak dihitung poin kontribusi, tetapi **ada kategori iuran alumni** ✅ | ✅ DIKONFIRMASI |
| B7 | Alumni **boleh** mengirim karya (berita/opini/kajian/esai/sastra), diberi label "Alumni" | Label tipe "Opini **Kader**" mungkin harus diganti |
| B8 | Pengurus auto-verifikasi berlaku untuk pendaftaran **kader**; klaim **alumni** tetap diverifikasi Sekretaris | Perlu keputusan |
| B9 | Masa pinjam buku **7 hari**, maks. **2 buku**/orang, perpanjang **1×**, siap-diambil **2×24 jam** | Semua bisa diubah di CMS |
| B10 | Pengajuan pinjam **aset** disetujui manual (tidak otomatis) | Bisa dibuat otomatis per barang |
| B11 | Transaksi keuangan **tidak bisa dihapus**, hanya di-`void` + alasan | Standar audit |
| B12 | Bukti transaksi **wajib** di atas Rp100.000 | Perlu keputusan nominal |
| B13 | Iuran **ada**, dibagi 3 kategori (anggota aktif, pengurus, alumni); nominal diatur Bendahara ✅ | ✅ DIKONFIRMASI (nominal menyusul) |
| B26 | Bilingual: **hanya halaman publik** diterjemahkan; panel admin & dashboard tetap Indonesia 🆕 | Perlu konfirmasi (L1) |
| B27 | Versi Inggris artikel **opsional** per artikel, dengan fallback ke Indonesia + `noindex` 🆕 | Perlu konfirmasi (L2) |
| B28 | Format nomor anggota acak `RAAB-2026-482913` 🆕 | Perlu konfirmasi bentuk akhirnya |
| B24 | Hibah alumni bersifat **internal** — tanpa halaman publik, tanpa pembayaran online 🆕 | Perlu keputusan |
| B25 | Hibah **dana** → kas masuk (sumber `hibah`); **barang** → menambah stok inventaris; **jasa** → ditautkan ke kegiatan 🆕 | Perlu keputusan |
| B14 | Berita acara: format nomor `001/BA/RAAB/IX/2026` | Perlu persetujuan |
| B15 | Poin kontribusi dihitung **per periode kepengurusan** | Alternatif: akumulatif seumur keanggotaan |
| B16 | Aspirasi: pendaftar bisa **melacak status dengan kode tiket** tanpa login | Bisa dihapus bila dianggap bocor |
| B17 | Aspirasi: Sekretaris boleh **menyunting bagian isi** yang memuat data pribadi | Perlu keputusan |
| B18 | Kartu kader berlaku mengikuti periode aktif (1 tahun), terbit otomatis | Bisa manual |
| B19 | Alumni **juga mendapat kartu alumni** (tanpa presensi/poin) | Perlu keputusan |
| B20 | Tidak ada komentar publik di artikel | Bisa ditambah nanti |
| B21 | Ada RSS feed untuk publikasi | Bisa dihapus, murah untuk ditambah |
| B22 | Data keuangan lama **tidak** dimigrasi; mulai dari saldo awal saja | Perlu keputusan |
| B23 | 404: halaman publik menampilkan navigasi + saran artikel | — |

### C. Asumsi Teknis

| # | Asumsi saya | Catatan |
|---|---|---|
| C1 | Paket hosting yang dipilih mendukung **PHP 8.2+**, MySQL 8/MariaDB 10.4+, cron, dan symlink (atau fallback) | **Harus dicek sebelum Fase 1** |
| C2 | Tanpa SSR (SPA Inertia) — SEO mengandalkan meta tag + sitemap | Bisa upgrade ke SSR di VPS nanti |
| C3 | Kerangka awal memakai **Laravel Breeze (Vue + TypeScript)** lalu dikembangkan | Alternatif: Jetstream (lebih berat) |
| C4 | Editor konten memakai **TipTap** | Alternatif: TinyMCE (berat), Quill |
| C5 | Peta alumni memakai **Leaflet + OpenStreetMap** (tanpa API key/biaya) | Alternatif: Google Maps embed |
| C6 | Captcha memakai layanan gratis (Cloudflare Turnstile / hCaptcha) | Perlu akun; bila kamu tidak mau, cukup honeypot + rate limit |
| C7 | Tidak ada Redist/Redis — cache & session berbasis file/database | Sesuai hosting murah |
| C8 | 2FA **wajib untuk Superadmin**, opsional untuk pengurus lain | Perlu keputusan |
| C9 | Backup otomatis harian (retensi 14 hari) memakai job + cron | Bila storage kecil, kirim ke email/unduhan mingguan |
| C10 | Pencarian pakai FULLTEXT MySQL, tanpa Meilisearch/Algolia | Cukup untuk skala rayon |
| C11 | Upload gambar maks. 5 MB, dokumen PDF maks. 10 MB | Bisa disesuaikan |
| C12 | Zona waktu simpan UTC, tampil `Asia/Jakarta` | — |

---

## Bagian 3 — Pertanyaan Terbuka

### ✅ Fase 1 — SUDAH DIJAWAB SEMUA

| # | Jawabanmu | Konsekuensi di dokumen |
|---|---|---|
| Q1 | 5 LSO: **Mutasi** (musik/teater/aksi/tari), **Harokatuna** (hadrah), **LDR**, **LPM Albiruni**, **MJT** (pubdok) — punya pengurus & halaman sendiri | Menu LSO jadi indeks + 5 halaman; `UnitSeeder` & `PositionSeeder` disiapkan |
| Q2 | Logo akan ditaruh di root workspace; desain **neo-brutalism biru-kuning**, mobile-first, interaktif | Lahir dokumen `08-desain-visual.md` |
| Q3 | Komisariat **Raden Mas Said**, kampus **UIN Raden Mas Said Surakarta**; detail lain placeholder | Semua identitas lewat Pengaturan Situs |
| Q4 | Mabinra → Ketua → Wakil → Sekretaris 1&2 → Bendahara 1&2 → 8 Divisi | Seed placeholder di `04-data-model.md` |
| Q5 | Masa juang **1 tahun**, diatur **Superadmin saja**, riwayat mulai **2017** | `PeriodSeeder` 2017–sekarang |
| Q6 | Belum ada domain & email | MVP pakai subdomain penyedia; email Brevo; sediakan verifikasi manual |
| Q7 | Belum ada hosting, MVP di **free tier** | Lahir dokumen `09-deploy-mvp-free-tier.md`; Fase 1–8 dikerjakan **lokal** |
| Q8 | Data lama **ada**, tetapi **placeholder dulu** | Sub-fase Impor Data I1–I4 ditambahkan ke roadmap |

> 🎉 **Tidak ada lagi penghambat Fase 1.**

### ✅ Jawaban Ronde 2

| Kategori | Status |
|---|---|
| **L1–L6** (bilingual) | ✅ Terjawab semua — lihat `10-lokalisasi-bilingual.md` §9 |
| **V1–V3, V5, V6** (desain) | ✅ Terjawab · V4/V7/V8/V9 **diserahkan ke saya** → ditetapkan di `08-desain-visual.md` §13 |
| **D1–D5** (deploy) | ✅ D1 setuju Jalur A · D2–D5 **diserahkan ke saya** → ditetapkan di `09-deploy-mvp-free-tier.md` §8 |
| **Q12, Q18, Q19, Q23** (bisnis) | ✅ Terjawab |
| Koreksi arsitektur | ✅ Frontend **Blade + Tailwind** (publik, server-rendered) + **Inertia + Vue** (admin/dashboard) |
| Koreksi istilah | ✅ **Biro** (bukan "Divisi"), **Kepala Biro/Kabiro** (bukan "Kadiv") |

### 🟡 Memblokir Fase 2–5

| # | Pertanyaan | Default bila tidak dijawab |
|---|---|---|
| **Q9** | Field wajib pendaftaran **kader aktif**: setuju dengan daftar di `03-alur-bisnis.md` Alur 1? Ada yang ditambah/dikurangi? | Saya pakai daftar usulan |
| **Q10** | Field wajib pendaftaran **alumni**: apa yang perlu ditanyakan? (tahun masuk/lulus, instansi, domisili, riwayat organisasi?) | Saya pakai daftar di data model |
| **Q11** | Data apa yang **boleh tampil publik** di halaman Anggota & Alumni? (nama, foto, angkatan, unit, jabatan?) | Saya pakai: nama, foto, angkatan, unit, jabatan. NIM/HP/email **tersembunyi** |
| **Q12** | ✅ **Terjawab**: nomor anggota **acak unik**, dimulai dari periode sekarang | Format usulan: `RAAB-2026-482913` |
| **Q13** | Field form **Mapaba & PKD** — setuju daftar usulan? Ada yang wajib ditambah (mis. rekomendasi, tes wawancara)? | Saya pakai daftar usulan |
| **Q14** | Apakah peserta Mapaba/PKD boleh dari **luar rayon/kampus**? | Saya asumsikan boleh, ada field "asal komisariat" |
| **Q15** | Ada **kuota** yang biasa dipakai? Berapa peserta tipikal Mapaba/PKD? | Default kuota kosong (tanpa batas) |
| **Q16** | Apakah perlu **form pendaftaran dicetak/PDF** (bukti daftar) atau cukup email + kode? | Saya sediakan halaman status pendaftaran + PDF kartu peserta |

### 🟢 Memblokir Fase 6–9

| # | Pertanyaan | Default bila tidak dijawab |
|---|---|---|
| **Q17** | **Inventaris aset**: kategori apa saja yang dipakai? (elektronik, perlengkapan, ATK, kendaraan?) Ada kode inventaris berformat tertentu? | Saya buat kategori umum, kode otomatis |
| **Q18** | ✅ **Terjawab**: jumlah koleksi buku pakai **placeholder** | Demo: 50 judul / 150 eksemplar; impor CSV tetap disiapkan |
| **Q19** | ✅ **Terjawab**: iuran **ada** — 3 kategori (anggota aktif, pengurus, alumni), nominal diatur Bendahara | Kategori & tagihan berjenjang disiapkan |
| **Q20** | **Keuangan**: daftar akun kas (Kas Utama / Kas Kegiatan / Kas LSO?), apakah ada dana LSO terpisah? | Saya buat 2 akun awal |
| **Q21** | **Dokumen internal** apa saja yang perlu diarsipkan? (AD/ART, template surat, hasil rapat, SK pengurus?) | Saya buat kategori bebas |
| **Q22** | **Prestasi**: daftar tingkat lomba yang dipakai? Ada kategori (akademik, olahraga, seni, keagamaan, organisasi)? | Saya pakai tingkat rayon→internasional + kategori bebas |
| **Q23** | ✅ **Terjawab**: 2FA **opsional** untuk semua pengurus | Tersedia per akun, tidak diwajibkan |
| **Q24** | Apakah kamu mau lihat **pratinjau browser tiap fase** sebelum lanjut? | Saya sediakan cara menjalankan lokal + tangkapan layar |

---

## Bagian 4 — Cara Menjawab

Cukup balas di chat, contohnya:

> **"A1–A7 benar. Q1: Sarekat itu… Q2: logo sudah saya taruh, warna hijau tua & kuning. Q5: periode 2025/2026. Q9 setuju. Q11: tambahkan prodi juga."**

Sudah selesai ✅: Q1–Q8, Q12, Q18, Q19, Q23, **L1–L7**, V1–V3, V5–V6, D1, plus koreksi arsitektur frontend & istilah Biro. **Spesifikasi telah dikunci.**

Yang masih bisa dijawab sambil Fase 1 berjalan (tidak menghambat):

- **Q9–Q11, Q13–Q17, Q20–Q22, Q24** di Bagian 3
- **Logo** ✅ sudah tersedia (`logo-pmii-raab.png`); aset lain (favicon, OG image) memakai placeholder

---

## Bagian 5 — Yang Saya Lakukan Setelah Kamu Jawab

1. ✅ Dokumen `docs/` sudah diperbarui sesuai jawabanmu (01–07) + 2 dokumen baru (08, 09).
2. Kunci spesifikasi & tandai dokumen sebagai **disetujui** — menunggu jawaban Q9–Q24 / V / D.
3. Mulai **Fase 1**: inisialisasi proyek Laravel 12 + Inertia + Vue + Tailwind, lalu laporkan hasilnya langkah demi langkah.
4. Uji lokal olehmu → perbaikan → lanjut Fase 2.
