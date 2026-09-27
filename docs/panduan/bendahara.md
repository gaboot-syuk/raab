# Panduan Pengguna — Bendahara

Panduan ini untuk pengurus dengan peran **Bendahara**.

---

## 1. Apa yang bisa kamu buka

| Menu panel | Untuk apa |
|---|---|
| Dasbor | Ringkasan keuangan |
| Buku Kas | Mencatat transaksi masuk & keluar |
| Iuran | Menagih, memverifikasi bukti bayar |
| Anggaran (RKAT) | Rencana anggaran per kegiatan |
| Hibah & Dukungan | Mencatat donasi & dukungan |
| Laporan Keuangan | Rekap, neraca sederhana, ekspor |
| Pengumuman | Hanya membaca arsip dokumen |
| Anggota | **Hanya membaca nama & nomor** — bukan data pribadi |
| Publikasi, Halaman, Keanggotaan | **Tidak bisa** — milik peran lain |

Yang **tidak** bisa kamu buka: mengubah data anggota, menyetujui keanggotaan,
menyunting halaman situs, dan membuka panel Superadmin.

---

## 2. Pekerjaan rutin

### 2.1 Mencatat transaksi

1. Buka **Buku Kas** → **Catat Transaksi**.
2. Pilih jenis: **masuk** atau **keluar**.
3. Isi tanggal, kategori, jumlah, akun kas, dan keterangan.
4. Lampirkan bukti (foto nota) bila ada. Bukti sangat membantu saat
   pemeriksaan.
5. Simpan. Transaksi yang sudah dicatat **tidak dihapus** — kalau salah, pakai
   **Batalkan (void)**. Jejaknya tetap ada, dan itu disengaja: buku kas yang
   bisa dihapus diam-diam tidak bisa diaudit.

### 2.2 Iuran anggota

1. Buka **Iuran** → **Buat Tagihan**. Tentukan kategori iuran, nominal, dan
   periode.
2. Kader membayar, lalu mengunggah bukti lewat area anggota.
3. Bukti itu masuk ke antrean **Verifikasi Pembayaran**. Periksa nominalnya
   cocok, lalu setujui.
4. Tolak dengan alasan bila bukti tidak jelas — jangan biarkan menggantung.

### 2.3 Anggaran & realisasi

1. Buka **Anggaran (RKAT)** → **Tambah Anggaran**.
2. Isi kegiatan, pagu anggaran, dan periode.
3. Realisasi terisi dari transaksi yang kamu tautkan ke anggaran itu.
4. Selisih antara pagu dan realisasi itulah yang dibaca pengurus saat rapat
   kerja — jadi taatkan transaksi ke anggarannya.

### 2.4 Hibah & donasi

1. Buka **Hibah & Dukungan** → **Catat**.
2. Donasi masuk lewat dua jalur: dicatat manual olehmu, atau diajukan sendiri
   oleh anggota lewat area anggota (menu **Hibah**).
3. Ajuan anggota **harus diverifikasi** sebelum masuk ke laporan.
4. Isi nama pemberi **hanya bila pemberinya setuju**. Kalau tidak, tulis
   "Hamba Allah" / "Anonim" — jangan mengisi nama yang tidak diminta.

### 2.5 Laporan

1. Buka **Laporan Keuangan**.
2. Pilih rentang tanggal, lalu jenis laporan.
3. **Ekspor** menghasilkan CSV. Simpan salinannya di luar server: laporan
   keuangan yang hanya ada di server yang sama belum disebut aman.

---

## 3. Hal yang sering salah

**Angka tidak cocok dengan catatan manual.** Cek transaksi yang dibatalkan —
transaksi void tetap muncul di daftar, tetapi tidak dihitung di laporan.

**Tidak bisa melihat data pribadi anggota.** Memang begitu. Peran Bendahara
hanya diberi akses baca ringkas (nama & nomor anggota). Kalau kamu butuh data
lengkap untuk suatu keperluan, minta Sekretaris yang mengekspornya — jangan
meminta data itu dikirim lewat obrolan.

**Tagihan tidak muncul di area anggota.** Pastikan tagihannya ditautkan ke
anggota yang benar dan periodenya sudah berjalan.

**Saldo kas tidak sama dengan jumlah transaksi.** Buka **Buku Kas** dan saring
per akun kas. Saldo dihitung per akun, bukan digabung.

---

## 4. Batas yang disengaja

Beberapa hal memang tidak bisa dilakukan satu orang sendirian:

- **Pencatatan dan pengesahan dipisahkan.** Kamu mencatat; verifikasi transaksi
  adalah izin tersendiri. Ini melindungi kamu juga — kalau nanti ada
  pertanyaan, ada dua pihak yang terlibat.
- **Penghapusan transaksi tidak disediakan.** Hanya pembatalan, dan alasannya
  wajib.
- **Cadangan basis data bukan milik Bendahara.** Berkasnya memuat seluruh isi
  basis data termasuk data pribadi anggota, jadi hanya Superadmin yang boleh
  menyentuhnya.

---

## 5. Kalau ada masalah

Minta Superadmin membuka **Panel → Diagnostik**. Di sana terlihat keadaan
basis data, cadangan terakhir, dan galat hari ini. Kalau transaksi terasa
"hilang", periksa dulu saringan tanggal dan akun kas sebelum melapor.
