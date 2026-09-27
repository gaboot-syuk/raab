# Dokumentasi Proyek — Website & CMS PMII Rayon Ali Ahmad Baktsir

Folder ini berisi **dokumen spesifikasi Fase 0** untuk pembangunan web app + CMS organisasi.

**Status keseluruhan:** � **Spesifikasi DISETUJUI & DIKUNCI (26 Sep 2026)** — Fase 1 sedang dikerjakan.

---

## Daftar Dokumen

| File | Isi | Untuk siapa |
|---|---|---|
| [`01-ringkasan-produk.md`](01-ringkasan-produk.md) | Visi, kamus istilah, aktor, peta situs publik, peta panel admin, ruang lingkup | Semua |
| [`02-role-permission.md`](02-role-permission.md) | Model dua lapis (role vs keanggotaan), katalog permission, matriks hak akses, aturan verifikasi | Pengurus |
| [`03-alur-bisnis.md`](03-alur-bisnis.md) | 13 alur kerja lengkap beserta diagram (pendaftaran, event, publikasi, pinjam, iuran, aspirasi, dll.) | Semua |
| [`04-data-model.md`](04-data-model.md) | ERD per domain, ±52 tabel, enum status, index, penyimpanan media, seeder | Teknis |
| [`05-roadmap-fase.md`](05-roadmap-fase.md) | Rencana pembangunan Fase 0–9 beserta kriteria selesai tiap fase | Semua |
| [`06-teknis-hosting.md`](06-teknis-hosting.md) | Stack, paket, struktur folder, konvensi kode, deploy shared hosting, cron, SEO, keamanan, risiko | Teknis |
| [`07-asumsi-terbuka.md`](07-asumsi-terbuka.md) | **Asumsi saya & pertanyaan yang perlu kamu jawab** | **Kamu (prioritas)** |
| [`08-desain-visual.md`](08-desain-visual.md) | Sistem desain **neo-brutalism** (biru-kuning), token warna, komponen, mobile-first, 21 pola interaksi, aksesibilitas | Desain & frontend |
| [`09-deploy-mvp-free-tier.md`](09-deploy-mvp-free-tier.md) | Rencana deploy **free tier** (Render + TiDB + R2 + Brevo), konsekuensi arsitektur, strategi placeholder, risiko | Teknis & kamu |
| [`10-lokalisasi-bilingual.md`](10-lokalisasi-bilingual.md) | Rencana **bilingual Indonesia–Inggris**: ruang lingkup, strategi URL, kolom terjemahan, alur kerja, SEO, dampak fase | **Kamu (prioritas)** |
| [`panduan/`](panduan/README.md) | **Panduan pemakaian** per peran (Sekretaris, Bendahara, Konten Manager, Kader) dan **panduan deploy & pembaruan** | Pengurus & pengelola server |

---

## Cara Review (disarankan urut)

1. Baca **`07-asumsi-terbuka.md`** → jawab **Q1–Q8** (memblokir Fase 1). Sisanya bisa menyusul.
2. Baca **`01-ringkasan-produk.md`** → pastikan peta situs & ruang lingkup sudah sesuai keinginanmu.
3. Baca **`03-alur-bisnis.md`** → cek alurnya realistis dengan kebiasaan rayon.
4. Baca **`02-role-permission.md`** → pastikan tidak ada yang punya akses kurang/berlebih.
5. **`05-roadmap-fase.md`** → setujui urutan pengerjaan.
6. `04` & `06` bisa dibaca sambil lalu (lebih teknis).

---

## Menjalankan Proyek (Docker)

```bash
docker compose up -d                 # app + db + mailpit + queue + scheduler
docker compose exec app php artisan migrate --seed
docker compose logs -f app           # lihat log
```

| Layanan | Alamat |
|---|---|
| Situs | http://localhost:8100 |
| Panel pengurus | http://localhost:8100/panel |
| Kotak surat pengembangan | http://localhost:8025 |
| Basis data | `localhost:3309` (raab / rahasia) |

Akun uji: `ketua@raab.test` / `rahasia123` (role **superadmin**).

> Aset frontend dibangun dari host: `npm run build` (sekali) atau `npm run dev` (selama mengembangkan).

## Keputusan yang Sudah Final ✅

| Aspek | Keputusan |
|---|---|
| Stack | **Laravel 13** + Vite 8 + Tailwind v4 · lokal: **SQLite** · produksi: MySQL |
| Frontend | **Publik: Blade + Alpine.js** (server-rendered) · **Admin/Dashboard: Inertia v2 + Vue 3 (TypeScript)** |
| Penerjemahan | **Terjemahan otomatis saat simpan** (DeepL/Google/none) + boleh disunting; Indonesia sumber utama, Inggris opsional |
| Istilah resmi | **Biro** & **Kepala Biro (Kabiro)** — bukan "Divisi"/"Kadiv" |
| Warna merek | Biru `#2E3192` · biru terang `#1B75BB` · kuning `#FFD100` (diekstrak dari logo resmi) |
| Autentikasi | **Laravel Fortify** (bukan Breeze) — headless, termasuk 2FA opsional; UI dibuat sendiri dengan Blade |
| Lingkungan | **Docker Compose** (app + MariaDB 11 + Mailpit + queue + scheduler) · tanpa Docker tetap bisa (SQLite) |
| Versi framework | Laravel 13 · Inertia v3 · Tailwind v4 · Vite 8 |
| Hosting | Shared hosting / cPanel murah (tanpa SSR, tanpa Redis, queue via cron) |
| Email | Brevo / Resend |
| Publikasi | Satu model artikel + kategori; 5 tipe + berita acara/pers release |
| Keuangan | Internal saja (Bendahara + Superadmin) |
| Inventaris | Manajemen penuh + katalog publik read-only |
| Perpustakaan | Pinjam–perpanjang–reservasi/antrian, **tanpa denda** |
| Role vs Keanggotaan | Terpisah; pengurus harus mendaftar agar dapat akses anggota |
| Verifikasi | Dua gerbang: verifikasi email → verifikasi Sekretaris |
| Kader → Alumni | Satu record, status naik |
| Struktur | Multi-periode |
| Peminjam | Kader aktif + alumni; pihak luar lewat Sekretaris (wajib PIC internal) |
| Aspirasi | Wajib identitas, tampil publik anonim |
| Metode kerja | Bertahap per fase, dari awal sampai akhir |
| Komisariat | PMII Komisariat Raden Mas Said — UIN Raden Mas Said Surakarta |
| Sarekat LSO | 5 unit dengan pengurus & halaman sendiri: Mutasi, Harokatuna, LDR, LPM Albiruni, MJT |
| Periode (Masa Juang) | 1 tahun; dikelola **hanya oleh Superadmin**; riwayat mulai 2017 |
| Desain | **Neo-brutalism**, biru + kuning, **mobile-first**, interaktif |
| Hosting | MVP **free tier**; rilis ke hosting murah |
| Hibah alumni 🆕 | Alumni dapat memberi hibah **dana/barang/jasa**; dikelola Bendahara, **internal saja** |
| Data lama | Ada, tetapi MVP memakai placeholder dulu; impor sebagai sub-fase menyusul |
| Bahasa | **Bilingual Indonesia (default) + Inggris** — halaman publik saja; panel admin Indonesia |
| Tema | Mode **terang + gelap + ikut sistem** sejak Fase 1 |
| Galeri & agenda LSO | Dikelola **Konten Manager** |
| Iuran | Berkategori: **anggota aktif, pengurus, alumni** (diatur Bendahara) |
| Nomor anggota | **Acak unik** — `RAAB-{tahun}-{6 digit}`, mulai periode sekarang |
| 2FA | **Opsional** untuk pengurus |

---

## Langkah Berikutnya

1. ✅ Kamu sudah menjawab **Q1–Q8** → dokumen 01–06 telah direvisi sesuai jawaban itu.
2. **Kamu:** jawab sisa aturan bisnis kapan saja (tidak menghambat): `07-asumsi-terbuka.md` Bagian 3 → **Q9–Q11, Q13–Q17, Q20–Q22, Q24**
3. **Saya:** ✅ spesifikasi dikunci → **Fase 1 sedang berjalan** (fondasi, Blade publik + Inertia/Vue admin, bilingual, mode gelap, auth, role)

> Catatan: selama Fase 1 belum berjalan, belum ada satu file kode pun di workspace ini — yang ada hanya folder `docs/` dan `NotebookLM Mind Map.png` (sumber asli fitur).
