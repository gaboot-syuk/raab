# Panduan Pengguna — Sekretaris

Panduan ini untuk pengurus dengan peran **Sekretaris**. Isinya bukan penjelasan
fitur, melainkan urutan langkah untuk pekerjaan yang benar-benar kamu kerjakan.

---

## 1. Apa yang bisa kamu buka

| Menu panel | Untuk apa |
|---|---|
| Dasbor | Ringkasan keanggotaan & keuangan |
| Anggota | Data kader, status, nomor anggota |
| Verifikasi Pendaftar | Menyetujui/menolak pendaftar Mapaba & PKD |
| Organisasi | Periode, unit (LSO), jabatan, penugasan, daftar mentor |
| Kegiatan | Agenda rayon, absensi, poin kontribusi |
| Presensi | Membuka absensi, memindai QR, mengoreksi kehadiran |
| Prestasi | Menyetujui prestasi yang diajukan kader |
| Pengumuman | Menyusun pengumuman internal |
| Arsip Dokumen | Menyimpan dokumen dengan kendali audiens |
| Berita Acara | Menulis & menerbitkan berita acara rapat |
| Inventaris | Katalog aset & mutasi stok |
| Perpustakaan | Katalog buku & salinan |
| Peminjaman | Menyetujui, menyerahkan, menerima kembali |
| Aspirasi | Membaca & menanggapi aspirasi (identitas hanya bila diizinkan) |
| Laporan | Membuat & mengekspor laporan |
| Diagnostik / Cadangan | **Tidak bisa** — hanya Superadmin |
| Keuangan | **Tidak bisa** — milik Bendahara |
| Publikasi & Halaman | **Tidak bisa** — milik Konten Manager |

---

## 2. Pekerjaan rutin

### 2.1 Menyetujui anggota baru

1. Buka **Anggota** → tab **Menunggu Verifikasi**.
2. Periksa datanya satu per satu. Kolom bertanda sensitif (NIK, alamat, kontak)
   hanya terlihat oleh peran yang berhak; kalau kamu tidak melihatnya, itu
   memang disengaja.
3. **Setujui** atau **Tolak**. Untuk data yang kurang, pilih
   **Minta Revisi** — jangan tolak hanya karena kolom kosong, karena yang
   bersangkutan bisa memperbaikinya sendiri.
4. Setelah disetujui, **nomor anggota belum terbit sendiri**. Terbitkan lewat
   aksi **Terbitkan Kartu** di halaman detail anggota. Nomor yang sama tidak
   bisa dipakai dua orang.

### 2.2 Menutup kegiatan dan mengabsen

1. Buka **Kegiatan** → pilih kegiatan → **Buka Presensi**.
2. Kader memindai QR lewat ponselnya.
3. Setelah kegiatan selesai, **Tutup Presensi**. Setelah ditutup, kehadiran
   masih bisa dikoreksi manual oleh pengurus — kader tidak bisa lagi menambah.
4. Poin kontribusi yang muncul dari kegiatan **tidak dihitung otomatis**;
   sesuaikan bila perlu lewat **Poin Kontribusi → Sesuaikan**, dan tulis
   alasannya. Angka tanpa alasan akan jadi pertanyaan enam bulan kemudian.

### 2.3 Berita acara rapat

1. Buka **Berita Acara** → **Tulis Baru**.
2. Isi nomor dokumen, tanggal agenda, agenda, keputusan, penandatangan, dan
   jabatan penandatangan.
3. Berita acara bisa **langsung diterbitkan** oleh Sekretaris tanpa melewati
   review Konten Manager — dokumennya administratif, bukan jurnalistik.
4. Tautan PDF-nya bisa dibagikan; berkasnya dibuat otomatis.

### 2.4 Arsip dokumen

1. Buka **Arsip Dokumen** → unggah berkas.
2. **Pilih audiens dengan benar.** Ini bagian yang paling mudah salah:
   - `publik` — bisa diunduh siapa saja
   - `anggota` — hanya kader yang sudah masuk
   - `pengurus` — hanya pengurus
3. Berkas arsip disimpan **di luar folder publik**. Alamat berkasnya tidak bisa
   ditebak dan tidak bisa dibuka langsung — hanya bisa keluar lewat tombol
   **Unduh**, yang memeriksa audiensnya lebih dulu.
4. Mengubah audiens mengubah siapa yang bisa mengunduh; daftarnya tidak
   menyaring berkas, jadi jangan menganggap berkas sudah aman hanya karena
   tidak tampil di daftar.

### 2.5 Aspirasi

1. Buka **Aspirasi** → daftar aspirasi masuk.
2. Identitas pengirim **tidak pernah ditampilkan**, bahkan kepada pengurus.
   Nama, email, dan telepon disamarkan; yang terlihat hanya nomor tiket,
   kategori, dan isi.
3. Balas lewat **Tanggapi**. Balasan pengurus **selalu** tampil di papan publik
   — tulis dengan sadar bahwa itu akan dibaca.
4. Setelah selesai, **Tutup** aspirasi agar tidak terus muncul di antrean.

### 2.6 Laporan

1. Buka **Laporan** → pilih jenis laporan.
2. **Ekspor** menghasilkan CSV. Berkasnya dibuka di Excel atau Google Sheets.
3. Data sensitif hanya muncul di laporan bila kamu punya izinnya. Kalau kolom
   yang kamu butuhkan kosong, hubungi Superadmin — jangan meminta data itu
   dikirim lewat WhatsApp.

---

## 3. Hal yang sering salah

**Nomor anggota kosong.** Persetujuan anggota dan penerbitan nomor adalah dua
langkah terpisah. Kalau kartu kader menampilkan "Nomor anggotamu .", nomornya
belum diterbitkan.

**Kader tidak bisa memindai QR.** Pastikan presensi masih **terbuka**. Setelah
ditutup, pemindaian ditolak.

**Poin tidak bertambah.** Poin hanya bertambah dari kegiatan yang sudah ditutup
dan dicatat, atau lewat penyesuaian manual. Cek **Poin Kontribusi** untuk
riwayatnya.

**Tidak bisa membuka Keuangan.** Memang begitu. Peran Keuangan terpisah supaya
satu orang tidak memegang pencatatan dan pengesahannya sekaligus.

---

## 4. Kalau ada masalah

1. Minta Superadmin membuka **Panel → Diagnostik**. Halaman itu menunjukkan
   keadaan basis data, antrean, email, cadangan, dan penjadwal, plus galat hari
   ini.
2. Kalau email pemberitahuan tidak sampai: cek **Diagnostik → Gagal**. Angka
   lebih dari nol berarti ada pesan yang tidak terkirim.
3. Kalau sesuatu tampak "tidak tersimpan", coba muat ulang halaman. Kalau tetap
   sama, laporkan waktu kejadiannya — halaman galat 500 sudah mencatatnya di log.
