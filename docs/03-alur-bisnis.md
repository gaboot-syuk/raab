# 03 — Alur Bisnis

> **Fase 0 — Dokumen Spesifikasi.** Status: *menunggu review*. ⚠️ = asumsi, ✅ = sudah dikonfirmasi.

Daftar alur:
1. Registrasi & verifikasi akun anggota
2. Naik status Kader Aktif → Alumni
3. Pendaftaran event Mapaba & PKD
4. Submisi & publikasi karya kader
5. Berita Acara & Pers Release
6. Verifikasi prestasi kader
7. Peminjaman aset (internal & pihak luar)
8. Perpustakaan: pinjam, perpanjang, reservasi
9. Presensi kegiatan & poin kontribusi
10. Iuran & pembayaran
11. Aspirasi
12. Kartu kader & verifikasi QR
13. Pencatatan kas & laporan keuangan
14. Hibah & dukungan alumni 🆕

---

## Alur 1 — Registrasi & Verifikasi Akun Anggota ✅

**Aktor:** Publik (pendaftar), Sistem (email), Sekretaris/Superadmin (verifikator)

```mermaid
flowchart TD
  A["Buka /daftar"] --> B{"Pilih jenis"}
  B -->|Kader Aktif| C["Form kader"]
  B -->|Alumni| D["Form alumni"]
  C --> E["Akun dibuat (belum terverifikasi)<br/>aplikasi: menunggu_verifikasi_email"]
  D --> E
  E --> F["Kirim email verifikasi (signed link, 60 menit)"]
  F --> G{"Link diklik ≤ 60 menit?"}
  G -->|Tidak| H["kedaluwarsa<br/>bisa kirim ulang"]
  G -->|Ya| I{"Pendaftar pemegang role organisasi?"}
  I -->|Ya| J["AUTO-VERIFIKASI ✅<br/>status = kader_aktif<br/>email 'akun aktif'"]
  I -->|Tidak| K["status aplikasi = menunggu_verifikasi<br/>email ke Sekretaris + cc Superadmin"]
  K --> L["Sekretaris buka Antrean Verifikasi"]
  L --> M{"Kelengkapan data?"}
  M -->|Kurang| N["perlu_perbaikan + email link perbaikan"]
  N --> K
  M -->|Valid| O["Disetujui: status = kader_aktif / alumni<br/>nomor_anggota dibuat otomatis"]
  M -->|Tidak valid| P["Ditolak + alasan + email"]
  O --> Q["Bisa login ke dashboard + kartu kader terbit"]
```

**Aturan & validasi**
- 1 email = 1 akun. Email harus unik dan belum pernah dipakai.
- NIM wajib untuk kader aktif; divalidasi unik.
- Untuk alumni: wajib isi angkatan (tahun Mapaba) + tahun selesai mandat → sistem **mencari kecocokan** dengan `members` lama (nama + angkatan). Bila cocok → ditautkan; bila tidak → dibuat record baru dengan catatan "perlu penelusuran". ✅
- Nomor anggota otomatis, format: `RAAB-{tahun}-{urut 3 digit}` ⚠️ (perlu persetujuan format).
- Sekretaris **tidak boleh** memverifikasi pendaftaran dirinya sendiri ✅ (diproses Superadmin).
- Semua keputusan tercatat: `member_applications` + `member_status_histories` + `activity_log`.
- Alasan penolakan wajib diisi (min. 20 karakter) agar pendaftar paham.

**Notifikasi email (via Brevo/Resend ✅)**

| Pemicu | Penerima | Isi |
|---|---|---|
| Pendaftaran dibuat | Pendaftar | Link verifikasi email |
| Email terverifikasi | Sekretaris + Superadmin | Ada pendaftar baru menunggu verifikasi |
| Diverifikasi | Pendaftar | Akun aktif + nomor anggota + tombol masuk |
| Ditolak | Pendaftar | Alasan + opsi ajukan ulang |
| Perlu perbaikan | Pendaftar | Daftar data yang harus dilengkapi |
| Diterima jadi alumni | Pendaftar | Status alumni + ajakan lengkapi profil |

---

## Alur 2 — Naik Status Kader Aktif → Alumni

**Aktor:** Superadmin/Sekretaris

```mermaid
flowchart LR
  A["Kader aktif terdeteksi lulus/mandat berakhir"] --> B["Sekretaris pilih kader<br/>(satu atau massal)"]
  B --> C["Isi data alumni:<br/>tahun selesai mandat, jabatan terakhir"]
  C --> D["Email pemberitahuan ke kader"]
  D --> E["Kader lengkapi profil alumni sendiri"]
  E --> F["Status = alumni<br/>keluar dari direktori Anggota<br/>masuk direktori Alumni"]
```

**Aturan**
- Perubahan status **tidak** menghapus: jabatan, presensi, prestasi, karya, riwayat pinjam tetap tersimpan.
- Kader yang pindah status ke alumni otomatis kehilangan akses: kartu kader, presensi, poin kontribusi, iuran.
- Alumni tetap bisa: pinjam buku & aset ✅, kirim aspirasi, lihat direktori alumni, tawarkan diri jadi mentor.
- Bisa dibatalkan (revert ke kader aktif) bila salah, dengan alasan tercatat.

---

## Alur 3 — Pendaftaran Event Mapaba & PKD ✅

**Aktor:** Sekretaris (pengelola), Publik (pendaftar)

```mermaid
flowchart TD
  A["Sekretaris buat event"] --> B["Status draft:<br/>nama, poster, lokasi, tanggal,<br/>kuota, info biaya, syarat, field tambahan"]
  B --> C["Buka pendaftaran:<br/>set tanggal buka & tutup"]
  C --> D["Pengunjung lihat landing event:<br/>info + sisa kuota + deadline"]
  D --> E{"Pendaftaran dibuka &<br/>kuota tersedia?"}
  E -->|Tidak| E1["Tombol nonaktif + pesan alasan"]
  E -->|Ya| F["Isi form"]
  F --> G["Validasi server-side"]
  G --> H{"Valid?"}
  H -->|Tidak| F
  H -->|Ya| I["Simpan pendaftaran<br/>kode unik: MAPABA-2026-0001"]
  I --> J["Email konfirmasi + kode ke pendaftar"]
  I --> K["Email notifikasi ke Sekretaris"]
  J --> L["Sekretaris verifikasi peserta<br/>atau tandai kehadiran saat acara"]
  L --> M["Export Excel / cetak kartu peserta"]
  L --> N["Peserta lulus Mapaba?"]
  N -->|Ya| O["Undang jadi Kader Aktif<br/>data terisi otomatis ✅"]
```

**Aturan**
- Pendaftar **tidak perlu punya akun** ✅. Form diisi sebagai tamu.
- Kuota: bila `jumlah_pendaftaran_terverifikasi ≥ kuota`, pendaftaran ditutup otomatis. ⚠️ Usulan: sisa kuota dihitung dari pendaftaran berstatus `menunggu` + `terverifikasi`.
- Event bisa ditutup manual kapan saja oleh Sekretaris (status `ditutup`).
- Field tambahan fleksibel per event (mis. ukuran kaos, alergi makanan) disimpan di `events.field_tambahan` (JSON) dan jawabannya di `event_registrations.data_tambahan` (JSON).
- **Tanpa pembayaran** ✅. Info biaya hanya teks (mis. "Kontribusi Rp50.000 dibayar di lokasi").
- Email konfirmasi wajib berisi: nama event, tanggal, lokasi (link maps), kode pendaftaran, narahubung.
- Arsip: setelah selesai, event tetap bisa dilihat publik di daftar event lampau (`/pendaftaran/mapaba`).

**Field form standar (usulan ⚠️)**

| Field | Wajib | Catatan |
|---|---|---|
| Nama lengkap | ✅ | |
| Jenis kelamin | ✅ | L/P |
| NIM | ✅ | |
| Email | ✅ | untuk konfirmasi |
| No. HP / WhatsApp | ✅ | |
| Fakultas & Program Studi | ✅ | |
| Angkatan kuliah | ✅ | |
| Tempat & tanggal lahir | ⚠️ | usulan: ya (kebutuhan data keanggotaan) |
| Alamat domisili | ⚠️ | usulan: ya (kota saja) |
| Asal sekolah | ⚠️ | usulan: tidak |
| Asal komisariat/kampus | ⚠️ | usulan: ya, untuk peserta luar |
| Motivasi mengikuti | ⚠️ | usulan: ya, 1 paragraf |
| Ukuran kaos | ⚠️ | usulan: ya |
| Catatan kesehatan | ⚠️ | usulan: ya |

---

## Alur 4 — Submisi & Publikasi Karya Kader ✅

**Aktor:** Kader/Alumni (penulis), Konten Manager (editor), Sekretaris (jalur khusus)

```mermaid
flowchart TD
  A["Kader buka Dashboard > Karya Saya > Tulis Baru"] --> B["Pilih tipe:<br/>berita / opini / kajian / esai / sastra"]
  B --> C["Isi judul, cover, isi (editor), tag"]
  C --> D["Simpan draft"] --> E["Kirim untuk review"]
  E --> F["status = menunggu_review<br/>notifikasi ke Konten Manager"]
  F --> G{"Review"}
  G -->|Perlu revisi| H["perlu_revisi + catatan<br/>notifikasi ke penulis"]
  H --> C
  G -->|Tolak| I["ditolak + alasan"]
  G -->|Setujui| J["status = terbit<br/>published_at diisi / dijadwalkan"]
  J --> K["Muncul di /publikasi/{tipe}/{slug}<br/>penulis tertaut ke profil kader"]
  S["Sekretaris buat berita_acara / pers_release"] --> T["Boleh publish langsung ✅"]
```

**Aturan**
- Hanya 5 tipe di atas (+ 2 tipe khusus Sekretaris) ✅.
- Penulis **tidak** boleh menerbitkan sendiri; wajib lewat review. Kecuali Sekretaris untuk `berita_acara`/`pers_release` ✅.
- Sastra (puisi/cerpen): tampilan halaman beda — tipografi lebih lega, tanpa kolom sidebar ⚠️ (bisa juga sama seperti tipe lain; usulan: layout khusus).
- Artikel bisa **dijadwalkan** (`published_at` di masa depan) — pemrosesan via cron `schedule:run`.
- Setiap perubahan setelah terbit disimpan di `article_revisions` (min. 10 revisi terakhir).
- Penulis hanya bisa mengedit karyanya sendiri, dan **hanya saat** `draft` atau `perlu_revisi` (setelah terbit, harus lewat Konten Manager).
- Slug otomatis dari judul, harus unik; bisa diubah manual oleh Konten Manager.

---

## Alur 5 — Berita Acara & Pers Release ✅

**Aktor:** Sekretaris

```mermaid
flowchart LR
  A["Sekretaris pilih 'Buat Berita Acara'"] --> B["Isi: nomor dokumen,<br/>agenda, tanggal, lokasi,<br/>pemimpin rapat, notulis, daftar hadir"]
  B --> C["Isi keputusan / hasil (poin-poin)"]
  C --> D["Tentukan penandatangan (Ketua + Sekretaris)"]
  D --> E{"Jenis"}
  E -->|Berita Acara| F["Publikasi tipe berita_acara,<br/>nomor dokumen tampil sebagai metadata"]
  E -->|Pers Release| G["Publikasi tipe pers_release,<br/>gaya tulisan untuk publik"]
  F --> H["Export PDF (kop + tanda tangan)"]
  G --> G1["Boleh kirim notifikasi ke media/tautan"]
```

**Aturan**
- **Bukan** modul surat-menyurat penuh (tanpa surat masuk/keluar) ✅.
- Nomor dokumen: `{urut}/BA/RAAB/{bulan romawi}/{tahun}` ⚠️ (perlu persetujuan format).
- Nomor dokumen harus unik; sistem mengusulkan nomor berikutnya otomatis.
- Daftar hadir bisa diambil dari `attendances` kegiatan terkait (opsional) atau diketik manual.
- Export PDF memakai kop surat (logo + nama rayon + alamat). ⚠️ Perlu file kop resmi.

---

## Alur 6 — Verifikasi Prestasi Kader ✅

**Aktor:** Kader/Alumni (pengaju), Sekretaris/Konten Manager (verifikator)

```mermaid
flowchart TD
  A["Kader: Dashboard > Prestasi > Ajukan"] --> B["Isi: nama lomba/kegiatan,<br/>tingkat, peringkat, penyelenggara, tahun,<br/>deskripsi, unggah sertifikat"]
  B --> C["status = diajukan<br/>notifikasi verifikator"]
  C --> D{"Verifikasi"}
  D -->|Tolak| E["ditolak + alasan"]
  D -->|Setujui| F["terverifikasi"]
  F --> G["Tampil di /prestasi<br/>+ muncul di halaman profil kader"]
  G --> H["Verifikator bisa tandai unggulan<br/>→ tampil di beranda"]
```

**Aturan**
- Tingkat prestasi: `rayon`, `komisariat`, `kota`, `provinsi`, `nasional`, `internasional` ⚠️ (perlu konfirmasi daftar).
- Sekretaris/Konten Manager juga bisa **input prestasi manual** untuk kader (mis. data lama).
- Prestasi yang sudah terverifikasi tidak bisa dihapus, hanya dinonaktifkan.
- Satu kader bisa punya banyak prestasi; halaman profil kader menampilkan urut tahun terbaru.

---

## Alur 7 — Peminjaman Aset (internal & pihak luar) ✅

**Aktor:** Kader/Alumni (peminjam internal), Sekretaris (pengelola & pencatat pinjaman eksternal)

```mermaid
flowchart TD
  A{"Peminjam"} -->|Kader / Alumni| B["Dashboard > Pinjam Aset<br/>pilih barang, jumlah, tujuan, tanggal"]
  A -->|Pihak luar| C["Sekretaris input manual:<br/>nama, instansi, kontak,<br/>PENANGGUNG JAWAB INTERNAL wajib ✅"]
  B --> D["status = diajukan"]
  D --> E["Sekretaris periksa ketersediaan"]
  C --> E
  E --> F{"Disetujui?"}
  F -->|Tidak| G["ditolak + alasan"]
  F -->|Ya| H["Serah terima: catat kondisi keluar"]
  H --> I["status = dipinjam<br/>stok tersedia berkurang"]
  I --> J{"Jatuh tempo lewat?"}
  J -->|Ya| K["Pengingat otomatis<br/>status = terlambat"]
  J -->|Tidak| L["Dikembalikan"]
  K --> L
  L --> M{"Kondisi saat kembali"}
  M -->|Baik| N["status = dikembalikan<br/>stok bertambah, movement 'pengembalian'"]
  M -->|Rusak / hilang| O["Catat movement rusak/hilang<br/>status = bermasalah + catatan"]
```

**Aturan**
- Peminjam internal: Kader Aktif + Alumni ✅.
- Peminjam eksternal: **hanya Sekretaris** yang boleh mencatat ✅, dan **wajib** mencantumkan penanggung jawab internal (kader/pengurus yang bertanggung jawab).
- Pengajuan internal boleh disetujui otomatis **atau** manual — saya usulkan **manual** (melibatkan barang fisik), dan bisa diubah per barang di CMS ⚠️.
- Barang dengan `is_loanable = false` (mis. inventaris penting) tidak bisa diajukan.
- Jumlah pinjam tidak boleh melebihi `jumlah_tersedia`.
- Pengingat email H-1 jatuh tempo + saat terlambat (harian, via cron).
- **Tanpa denda** ✅ untuk semua peminjaman (termasuk buku).
- Bila rusak/hilang → tercatat sebagai `inventory_movements` tipe `rusak`/`hilang` + catatan siapa yang bertanggung jawab.

---

## Alur 8 — Perpustakaan: Pinjam, Perpanjang, Reservasi ✅

**Aktor:** Kader/Alumni (peminjam), Sekretaris (pengelola)

```mermaid
flowchart TD
  A{"Pengguna"} -->|Login| B["/perpustakaan atau Dashboard > Perpustakaan"]
  B --> C["Cari buku (judul/penulis/ISBN/kategori)"]
  C --> D["Detail buku: sinopsis + status eksemplar"]
  D --> E{"Ada eksemplar tersedia?"}
  E -->|Ya| F["Ajukan pinjam<br/>pilih eksemplar, durasi"]
  E -->|Tidak| G["Ajukan RESERVASI (antrian)"]
  G --> H["masuk antrian ke-N<br/>status menunggu"]
  H --> I["Eksemplar dikembalikan"]
  I --> J["Orang ke-1 di antrian jadi 'siap diambil'<br/>+ email notifikasi + masa berlaku 2x24 jam"]
  J --> K{"Diambil dalam masa berlaku?"}
  K -->|Tidak| L["kedaluwarsa → lanjut ke antrian berikutnya"]
  K -->|Ya| F
  F --> M{"Disetujui Sekretaris?"}
  M -->|Ya| N["status dipinjam<br/>jatuh tempo otomatis"]
  M -->|Tidak| O["ditolak + alasan"]
  N --> P{"Jatuh tempo mendekat?"}
  P --> Q["Email pengingat H-1"]
  Q --> R{"Peminjam pilih?"}
  R -->|Perpanjang| S["Ajukan perpanjangan<br/>maks. 1x ✅, disetujui Sekretaris"]
  S --> N
  R -->|Kembalikan| T["Sekretaris catat pengembalian<br/>kondisi diperiksa"]
  T --> U{"Ada antrian?"}
  U -->|Ya| J
  U -->|Tidak| V["eksemplar = tersedia"]
```

**Parameter awal (usulan, bisa diubah lewat CMS ⚠️)**

| Parameter | Usulan default | Configurable? |
|---|---|---|
| Masa pinjam | 7 hari | ✅ |
| Perpanjangan | Maks. 1x, masing-masing 7 hari ✅ | ✅ |
| Jumlah buku per peminjam | 2 buku | ✅ |
| Masa berlaku "siap diambil" | 2×24 jam | ✅ |
| Reservasi/antrian | ✅ Ada | – |
| Denda | ❌ Tidak ada ✅ | – |
| Perpanjangan otomatis | ❌ Tidak (perlu persetujuan) | ✅ |
| Pemberi persetujuan pinjam | Sekretaris | ⚠️ bisa juga auto untuk buku |

**Aturan tambahan**
- Hanya kader aktif & alumni yang boleh meminjam ✅ (publik hanya melihat katalog).
- Bila eksemplar rusak/hilang, peminjam bertanggung jawab; status eksemplar diubah + catatan.
- Riwayat pinjam tampil di dashboard peminjam ("Sedang dipinjam", "Riwayat").

---

## Alur 9 — Presensi Kegiatan & Poin Kontribusi

**Aktor:** Sekretaris (pembuat kegiatan), Kader (peserta)

```mermaid
flowchart TD
  A["Sekretaris buat kegiatan:<br/>nama, jenis, waktu, lokasi, poin, wajib/tidak"] --> B["Buka presensi:<br/>mode manual atau QR"]
  B --> C{"Mode"}
  C -->|QR| D["Tampilkan/ cetak QR token di lokasi<br/>kader scan → halaman absen → login → hadir"]
  C -->|Manual| E["Sekretaris centang daftar kader"]
  A --> F["Kader lihat agenda di dashboard<br/>+ RSVP hadir/tidak"]
  D --> G["attendances tercatat<br/>hadir / terlambat / izin / sakit / alpa"]
  E --> G
  G --> H["Poin otomatis masuk ledger<br/>contribution_points"]
  H --> I["Peringkat kader teraktif<br/>per periode"]
```

**Aturan**
- Target peserta bisa disaring: semua kader, per angkatan, per unit/LSO, atau pengurus saja.
- Poin per kegiatan diatur di kegiatan (mis. hadir = 10, terlambat = 5, izin = 2, alpa = 0).
- Penyesuaian poin manual oleh Sekretaris **wajib** disertai alasan ⚠️.
- Poin bersifat **per periode kepengurusan** ⚠️ (usulan: hitung per periode agar papan peringkat adil).
- **Agenda publik unit/LSO** (`unit_agendas`) terpisah dari kegiatan internal ini dan **dikelola Konten Manager** ✅ — tanpa presensi.

---

## Alur 10 — Iuran & Pembayaran

**Aktor:** Bendahara (pengelola), Kader Aktif / Pengurus / Alumni (pembayar)

```mermaid
flowchart LR
  A["Bendahara buat KATEGORI IURAN:<br/>iuran anggota aktif, iuran pengurus, iuran alumni ✅"] --> A2["Bendahara terbitkan tagihan:<br/>kategori, bulan/tahun, nominal, jatuh tempo"]
  A2 --> B["Penerima tagihan melihatnya di dashboard<br/>(sesuai kategori)"]
  B --> C{"Bayar"}
  C -->|Tunai ke Bendahara| D["Bendahara catat pembayaran<br/>status terverifikasi"]
  C -->|Transfer| E["Kader unggah bukti transfer<br/>status menunggu"]
  E --> F["Bendahara verifikasi bukti"]
  F -->|Valid| D
  F -->|Tidak valid| G["ditolak + alasan + minta unggah ulang"]
  D --> H["Transaksi kas masuk otomatis<br/>finance_categories = iuran"]
  H --> I["Rekap: siapa sudah / belum bayar<br/>+ export Excel"]
```

**Aturan**
- ✅ **Kategori iuran diatur Bendahara**, tiga kategori awal: **iuran anggota aktif**, **iuran pengurus**, **iuran alumni**. Bendahara bebas menambah kategori lain.
- ✅ Iuran **alumni** kini ada (sebelumnya dianggap tidak wajib) — dipisah agar nominalnya bisa berbeda.
- Penerima tagihan otomatis dari kategori: `kader_aktif` → semua kader aktif; `pengurus` → pemegang role organisasi; `alumni` → semua alumni.
- **Nominal iuran tidak ditetapkan di dokumen ini** — sepenuhnya wewenang Bendahara lewat CMS ⚠️ (boleh memakai nominal placeholder dulu).
- Menandai lunas tanpa pembayaran (mis. dibebaskan) **wajib** disertai alasan.
- Rekap "belum bayar" bisa dikirim via email (manual dari Bendahara).
- Kategori iuran boleh **dinonaktifkan** tanpa menghapus riwayat tagihan.
- Semua laporan keuangan **internal** ✅ — tidak ada halaman publik.

---

## Alur 11 — Aspirasi ✅

**Aktor:** Publik/Kader/Alumni (pengirim), Sekretaris (penanggap)

```mermaid
flowchart TD
  A["Buka /aspirasi"] --> B["Form: WAJIB identitas<br/>(nama, email, no HP, jenis: internal/umum),<br/>kategori, judul, isi, lampiran opsional"]
  B --> C["Validasi + rate limit + honeypot + captcha"]
  C --> D["Simpan: nomor tiket ASP-2026-0001<br/>status baru"]
  D --> E["Email kode tiket ke pengirim<br/>+ notifikasi ke Sekretaris"]
  E --> F["Sekretaris baca (identitas terlihat)"]
  F --> G{"Tindakan"}
  G -->|Tidak layak| I["ditutup / spam (tidak tampil publik)"]
  G -->|Perlu tindak lanjut| H["status diproses"]
  G -->|Ditanggapi| J["Tulis tanggapan resmi<br/>+ pilih 'tampilkan di papan publik'"]
  J --> K["Tampil di papan aspirasi publik<br/>TANPA identitas pengirim ✅<br/>(hanya 'Aspirasi #ASP-2026-0001', kategori, isi, tanggapan)"]
  F --> L["Bisa balas privat ke email pengirim"]
```

**Aturan**
- Identitas **wajib** diisi ✅, tapi di papan publik **selalu anonim** ✅.
- Menu "papan aspirasi" menampilkan: nomor tiket (atau nomor urut acak), kategori, isi (bisa disunting PII oleh Sekretaris bila pengirim menuliskan identitas di dalam isi), status, dan tanggapan resmi.
- Pengirim bisa cek status dengan **kode tiket** (tanpa login): `/aspirasi/lacak`.
- Status: `baru` → `dibaca` → `diproses` → `ditanggapi` → `selesai` → `ditutup` (+ `spam`).
- ⚠️ Bila pengirim adalah kader/alumni yang login, identitas terisi otomatis dan aspirasinya bisa otomatis diberi label "Aspirasi Kader".
- Sekretaris **tidak** bisa mengubah isi aspirasi, hanya menambah tanggapan & mengaburkan data pribadi di isi (fitur redaksi) ⚠️.

---

## Alur 12 — Kartu Kader & Verifikasi QR ✅

**Aktor:** Sistem, Kader, Publik (verifikator)

```mermaid
flowchart LR
  A["Status kader_aktif disetujui"] --> B["Sistem terbitkan kartu:<br/>nomor kartu + QR token (UUID)"]
  B --> C["Kader lihat/unduh kartu<br/>(PDF/PNG) di dashboard"]
  C --> D["Publik scan QR saat kegiatan"]
  D --> E["Buka /verifikasi-kader/{token}<br/>TANPA login"]
  E --> F{"Token valid & status aktif?"}
  F -->|Ya| G["Tampil: foto, nama, nomor anggota,<br/>status keanggotaan, periode aktif, masa berlaku"]
  F -->|Tidak| H["Tampil: kartu tidak valid / dicabut"]
```

**Aturan**
- Halaman verifikasi **hanya** menampilkan data aman: foto, nama, nomor anggota, status, periode aktif. **Tanpa** NIM, email, HP, alamat.
- Token bersifat rahasia & tidak bisa ditebak (UUID v4). Bisa dicabut dan diterbitkan ulang oleh Sekretaris.
- Masa berlaku kartu mengikuti periode kepengurusan aktif ⚠️ (usulan: 1 tahun akademik, otomatis diperbarui).
- ⚠️ **Perlu konfirmasi:** apakah alumni juga mendapat kartu alumni? Usulan: ya, tanpa presensi/poin, sebagai tanda identitas alumni.

---

## Alur 13 — Pencatatan Kas & Laporan Keuangan ✅ (internal)

**Aktor:** Bendahara, Superadmin (audit)

```mermaid
flowchart TD
  A["Bendahara pilih akun kas:<br/>Kas Utama / Kas Kegiatan"] --> B{"Jenis transaksi"}
  B -->|Masuk| C["Sumber: iuran / donasi / dana kegiatan / lain"]
  B -->|Keluar| D["Kategori belanja + unggah bukti/nota"]
  C --> E["Isi: tanggal, jumlah, keterangan,<br/>kategori, bukti, tautkan ke kegiatan/budget bila ada"]
  D --> E
  E --> F["Nomor voucher otomatis<br/>status draft"]
  F --> G["Konfirmasi → saldo akun diperbarui"]
  G --> H["Buku kas per akun & per periode"]
  H --> I["Laporan: rekap kategori,<br/>anggaran vs realisasi,<br/>arus kas bulanan, saldo akhir"]
  I --> J["Export PDF / Excel"]
  G --> K["Salah input?"]
  K -->|Ya| L["Void (tidak menghapus)<br/>+ alasan wajib + saldo dikoreksi"]
```

**Aturan**
- Transaksi **tidak pernah dihapus** — hanya di-`void` ✅ (praktik akuntansi & audit).
- Konfirmasi transaksi mengubah `saldo_berjalan` akun. Void mengembalikannya.
- Setiap transaksi wajib punya bukti bila nominal ⚠️ (usulan: di atas Rp100.000 wajib bukti).
- Laporan tersedia: harian, bulanan, per periode kepengurusan, per kategori, per kegiatan, per akun.
- Akses: Bendahara + Superadmin saja ✅. Tidak ada halaman publik.
- ⚠️ **Perlu konfirmasi:** apakah donasi dari pihak luar dicatat sebagai transaksi masuk biasa (sumber `donasi`) tanpa kanal pembayaran online? Usulan: ya.

---

## Alur 14 — Hibah & Dukungan Alumni 🆕

**Aktor:** Alumni (pemberi), Bendahara (penerima & pencatat), Superadmin (audit)

```mermaid
flowchart TD
  A["Alumni buka Dashboard > Hibah & Dukungan"] --> B["Pilih jenis: dana / barang / jasa"]
  B --> C["Isi: tujuan, deskripsi, estimasi nilai,<br/>tanggal rencana, catatan"]
  C --> D["status = diajukan<br/>notifikasi ke Bendahara"]
  D --> E{"Bendahara tinjau"}
  E -->|"Tidak dapat diterima"| F["ditolak + alasan"]
  E -->|"Disetujui"| G["status = dijanjikan"]
  G --> H{"Jenis hibah"}
  H -->|"Dana"| I["Alumni transfer<br/>+ unggah bukti bila perlu"]
  I --> J["Bendahara verifikasi"]
  J --> K["status = diterima<br/>transaksi kas masuk (sumber: hibah) ditautkan"]
  H -->|"Barang"| L["Serah terima barang<br/>foto + kondisi dicatat"]
  L --> M["status = diterima<br/>masuk sebagai mutasi inventaris"]
  H -->|"Jasa"| N["Dijadwalkan sebagai pemateri/dukungan<br/>dicatat pada kegiatan"]
  K --> O["Rekap hibah per alumni, per periode, per jenis"]
  M --> O
  N --> O
  O --> P["Laporan Bendahara + export"]
```

**Aturan**
- Hibah bersifat **internal saja** — tidak ada halaman publik dan tidak ada pembayaran online ✅ (di luar lingkup).
- Jenis hibah: **dana**, **barang**, **jasa** ⚠️ (usulan; bisa ditambah/dikurangi).
- Hibah **dana** wajib ditautkan ke transaksi kas masuk → muncul di laporan keuangan dengan sumber `hibah`.
- Hibah **barang** wajib menghasilkan mutasi inventaris tipe `masuk` 🆕 → otomatis menambah stok aset.
- Hibah **jasa** dicatat sebagai kesediaan (mis. menjadi pemateri PKD) dan bisa ditautkan ke kegiatan.
- Nomor hibah otomatis: `HIB-2026-0001` ⚠️ (format perlu persetujuan).
- Alur status: `diajukan` → `dijanjikan` → `diterima` → `diverifikasi` (atau `ditolak` / `dibatalkan`).
- Bendahara boleh **mencatat hibah secara langsung** (mis. alumni menyerahkan dana tunai di sekretariat tanpa lewat dashboard).
- **Opsi anonim** ⚠️: alumni bisa memilih tidak ditampilkan namanya, termasuk di laporan internal (identitas hanya bisa dilihat Superadmin).
- Rekap: total hibah per tahun & per periode masa juang, daftar alumni pemberi (untuk apresiasi), tren bulanan.
- Apresiasi: Bendahara/Ketua bisa mengirim **ucapan terima kasih** via email + nomor dokumen ⚠️ (opsional).
