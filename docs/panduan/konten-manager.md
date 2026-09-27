# Panduan Pengguna — Konten Manager

Panduan ini untuk pengurus dengan peran **Konten Manager** (pengelola situs
publik).

---

## 1. Apa yang bisa kamu buka

| Menu panel | Untuk apa |
|---|---|
| Dasbor | Ringkasan |
| Publikasi | Menulis, mereview, menerbitkan artikel |
| Halaman | Sejarah, Visi & Misi, Sambutan |
| Media | Pustaka gambar & berkas |
| Galeri | Album foto kegiatan |
| Prestasi | Menyetujui/menonjolkan prestasi kader |
| Pengumuman | Pengumuman, termasuk yang tampil di situs |
| Laporan | Membuat laporan publikasi |

Yang **tidak** bisa kamu buka: Keuangan, Keanggotaan, Verifikasi Pendaftar,
Inventaris, Perpustakaan & Peminjaman, serta panel Superadmin.

---

## 2. Alur menulis artikel

### 2.1 Menulis

1. Buka **Publikasi** → **Tulis Baru**.
2. Pilih tipe: berita, opini, kajian, esai, atau sastra.
   Berita acara **bukan** milikmu — itu wewenang Sekretaris.
3. Isi judul, ringkasan, dan isi. Isi ditulis dalam bentuk teks biasa;
   penanda sederhana (tebal, tautan, daftar) sudah didukung.
4. Isi **versi Inggris** bila artikelnya layak dibaca dua bahasa. Kolomnya
   boleh dikosongkan — kalau kosong, versi Inggris menampilkan versi Indonesia.
5. Simpan sebagai **Draf**.

### 2.2 Review & terbit

1. Ajukan lewat **Kirim untuk Review**.
2. Kalau ada yang perlu diperbaiki, akan dikembalikan dengan **catatan review**.
   Catatan itu harus dibaca, bukan diabaikan.
3. Setelah disetujui, artikel bisa **Diterbitkan**.
4. Bisa juga **Dijadwalkan**: tentukan tanggal terbit, dan artikel akan muncul
   sendiri saat waktunya tiba.

### 2.3 Judul SEO

Setiap artikel punya kolom **Judul SEO** dan **Deskripsi SEO**.

- Judul SEO muncul di hasil pencarian, bukan judul aslinya.
- Bila dikosongkan, judul asli yang dipakai. Dikosongkan **bukan kesalahan** —
  kadang judul yang enak dibaca terlalu panjang untuk hasil pencarian.
- Panjang yang baik: sekitar 60 karakter untuk judul, 150 karakter untuk
  deskripsi.

---

## 3. Halaman statis, media, dan galeri

### 3.1 Halaman statis

1. Buka **Halaman** → pilih Sejarah, Visi & Misi, atau Sambutan.
2. Sunting, lalu simpan dengan status **Terbit** agar tampil di situs.
3. Perubahan **langsung terlihat** oleh pengunjung setelah disimpan; cache
   halaman dibuang otomatis.

### 3.2 Media

1. Buka **Media** → unggah berkas.
2. Unggah gambar berukuran wajar. Berkas besar memperlambat situs dan membuat
   kader dengan kuota terbatas menunggu terlalu lama.
3. Ukuran dan rasio tampilan di situs sudah ditentukan oleh templat; gambar
   akan dipotong mengikuti bingkainya. Pilih potret atau lanskap sesuai
   kebutuhan.

### 3.3 Galeri

1. Buka **Galeri** → **Buat Album**.
2. Isi judul, deskripsi, dan pilih foto yang sudah diunggah ke Media.
3. Susun urutan foto. Foto pertama biasanya dipakai sebagai sampul album di
   halaman daftar galeri.

---

## 4. Prestasi kader

1. Kader mengajukan prestasinya sendiri lewat area anggota.
2. Ajuan itu masuk ke antrean **Prestasi → Menunggu Verifikasi**.
3. Periksa buktinya, lalu **Setujui** atau **Tolak** dengan alasan.
4. Prestasi yang disetujui tampil di halaman publik prestasi **hanya jika**
   kader memilih membuka profilnya. Jangan mengubah pilihan itu tanpa
   memberitahunya.

---

## 5. Hal yang sering salah

**Artikel tidak muncul di situs.** Periksa dua hal: statusnya harus `terbit`,
dan bila dijadwalkan, waktunya sudah lewat. Artikel berstatus draf, menunggu
review, atau perlu revisi tidak akan pernah muncul di halaman publik.

**Perubahan halaman tidak terlihat.** Klik simpan, lalu muat ulang halaman
publiknya (Ctrl+Shift+R). Cache halaman sudah dibuang otomatis saat disimpan.

**Tidak bisa menerbitkan artikel.** Beberapa hal butuh persetujuan orang lain
lebih dulu. Kalau tombolnya tidak ada, artikelnya belum lolos review.

**Halaman galeri kosong.** Album tanpa foto di dalamnya tidak tampil.

---

## 6. Yang perlu kamu tahu tentang situs publik

- Setiap halaman publik punya **alamat versi Inggris** di bawah `/en`. Judul dan
  deskripsi versi Inggris diambil dari terjemahan yang kamu isi.
- **Peta situs** (`/sitemap.xml`) dan **robots.txt** sudah diurus otomatis.
  Kamu tidak perlu menyentuhnya.
- Halaman yang butuh masuk (panel, area anggota) **tidak** masuk peta situs dan
  ditutup di robots.txt. Itu disengaja.
- Judul dan deskripsi halaman diambil dari judul artikel lalu dipotong. Karena
  itu **ringkasan artikel penting**: ringkasan itulah yang muncul di hasil
  pencarian dan di pratinjau saat tautannya dibagikan ke WhatsApp.

---

## 7. Kalau ada masalah

Minta Superadmin membuka **Panel → Diagnostik** untuk melihat galat hari ini,
keadaan antrean, dan cadangan terakhir.
