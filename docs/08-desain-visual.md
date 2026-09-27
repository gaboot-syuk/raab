# 08 — Desain Visual & Sistem Interaksi

> **Fase 0 — Dokumen Spesifikasi.** Status: *menunggu review*.
> Arah dari kamu ✅: **Neo-brutalism**, kombinasi **biru + kuning**, **mobile-first**, dan **interaktif**.
> ⚠️ = perlu keputusanmu. Logo PMII RAAB *akan diletakkan di root workspace* (belum ada saat dokumen ini ditulis).

---

## 1. Prinsip Gaya: Neo-Brutalism

Neo-brutalism (juga disebut *neubrutalism*) adalah gaya web yang **sengaja menampilkan "kerangka mentah"** antarmuka: garis tebal, warna blok, dan bayangan keras. Bukan minimalis-lembut, tapi tegas dan berani.

| # | Prinsip | Aturan teknis |
|---|---|---|
| 1 | **Garis tebal** | Border **2–3px solid** warna ink (hitam), bukan abu-abu tipis |
| 2 | **Bayangan keras tanpa blur** | `box-shadow: 4px 4px 0 0 #0B0B0B` — **offset, blur 0**, warna solid |
| 3 | **Warna blok datar** | Warna solid penuh; **tidak ada gradient**, tidak ada glassmorphism |
| 4 | **Tipografi tebal & besar** | Judul display sangat bold, huruf kapital untuk label, jarak huruf rapat |
| 5 | **Sudut tegas** | `border-radius` 0 atau maks. `0.5rem`; hindari pill besar kecuali badge kecil |
| 6 | **Kontras tinggi** | Rasio minimal 4.5:1; hitam di atas kuning, putih di atas biru tua |
| 7 | **Elemen "stiker"** | Badge, label miring (-2°/+2°), bintang/panah dekoratif, garis bawah tebal |
| 8 | **Gerak bermakna** | Interaksi meniru tombol fisik: *naik lalu ditekan* (translate + shadow) |
| 9 | **Grid terlihat** | Garis pemisah tegas, tabel bergaris penuh, tidak ada pemisah samar |
| 10 | **Fungsional dulu** | Gaya tidak boleh mengorbankan keterbacaan, aksesibilitas, dan kecepatan |

### Yang dilarang

❌ Gradient · ❌ blur/glassmorphism · ❌ shadow lembut (`blur > 0`) · ❌ sudut terlalu bulat ·
❌ teks abu-abu kontras rendah · ❌ animasi berlebihan/parallax berat · ❌ autoplay video/audio ·
❌ emoji berlebihan sebagai ikon · ❌ font tipis (weight < 400 untuk isi panjang)

---

## 2. Palet Warna ⚠️

Kombinasi **biru + kuning** sebagai identitas. Hex di bawah adalah usulan saya — perlu persetujuanmu (atau ambil dari logo resmi).

### Token warna

| Token | Hex | Dipakai untuk |
|---|---|---|
| `ink` | `#0B0B0B` | Semua border, teks utama, bayangan keras |
| `paper` | `#FFFDF5` | Latar halaman (putih hangat, bukan putih murni) |
| `paper-alt` | `#F5F1E4` | Latar kartu sekunder / baris tabel bergantian |
| `primary-600` | `#2E3192` | **Biru utama (dari logo)**: navbar, tombol utama, tautan, header tabel |
| `primary-800` | `#1E2168` | Biru tua: hover, footer, teks di atas kuning |
| `primary-500` | `#1B75BB` | **Biru terang (dari perisai logo)**: aksen sekunder, grafik, tautan dalam teks |
| `primary-300` | `#9AA0DC` | Biru muda: latar pilihan, badge info |
| `primary-50` | `#EEF0FA` | Latar sangat muda (baris terpilih, kotak info) |
| `accent-400` | `#FFD100` | **Kuning utama (dari logo)**: tombol sekunder/CTA, sorotan, badge penting |
| `accent-600` | `#D9AE00` | Kuning tua: hover, garis bawah, teks di atas kuning |
| `accent-100` | `#FFF7CC` | Latar kartu sorotan / peringatan ringan |
| `success` | `#00A86B` | Status berhasil / terverifikasi / lunas |
| `danger` | `#E5321B` | Status ditolak / bahaya / void |
| `warning` | `#FF9F0A` | Perlu perhatian |
| `muted` | `#6B6B5E` | Teks sekunder (hanya untuk teks ≥ 14px, tetap ≥ 4.5:1 di atas `paper`) |

**Aturan pemakaian warna**
- Satu layar maksimal **1 warna dominan + 1 aksen**; jangan biru dan kuning sama-sama memenuhi layar.
- Kuning **tidak** untuk teks di atas putih (kontras gagal) — kuning dipakai sebagai **latar** atau **aksen tepi**.
- Biru utama untuk elemen aksi; kuning untuk elemen perhatian/CTA sekunder.
- Status selalu disertai **ikon/teks**, bukan hanya warna (aksesibilitas buta warna).

### Penerapan ke Tailwind v4

```css
/* resources/css/app.css */
@import "tailwindcss";

@theme {
  /* Warna */
  --color-ink: #0B0B0B;
  --color-paper: #FFFDF5;
  --color-paper-alt: #F5F1E4;
  --color-primary-50: #EEF0FA;
  --color-primary-300: #9AA0DC;
  --color-primary-500: #1B75BB;
  --color-primary-600: #2E3192;
  --color-primary-800: #1E2168;
  --color-accent-100: #FFF7CC;
  --color-accent-400: #FFD100;
  --color-accent-600: #D9AE00;
  --color-success: #00A86B;
  --color-danger: #E5321B;
  --color-warning: #FF9F0A;
  --color-muted: #6B6B5E;

  /* Tipografi */
  --font-display: "Archivo Black", "Plus Jakarta Sans", system-ui, sans-serif;
  --font-sans: "Plus Jakarta Sans", system-ui, sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, monospace;

  /* Bentuk & bayangan */
  --radius-brutal: 0.25rem;
  --shadow-brutal-sm: 2px 2px 0 0 var(--color-ink);
  --shadow-brutal: 4px 4px 0 0 var(--color-ink);
  --shadow-brutal-lg: 8px 8px 0 0 var(--color-ink);

  /* Breakpoint (mobile-first) */
  --breakpoint-sm: 40rem;   /* 640px  — HP besar */
  --breakpoint-md: 48rem;   /* 768px  — tablet */
  --breakpoint-lg: 64rem;   /* 1024px — laptop */
  --breakpoint-xl: 80rem;   /* 1280px — desktop */
}

/* Utilitas khas neo-brutalism */
@utility brutal {
  border: 3px solid var(--color-ink);
  box-shadow: var(--shadow-brutal);
}
@utility brutal-sm {
  border: 2px solid var(--color-ink);
  box-shadow: var(--shadow-brutal-sm);
}
@utility brutal-hover {
  transition: transform 120ms ease, box-shadow 120ms ease;
  &:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0 0 var(--color-ink); }
  &:active { transform: translate(2px, 2px); box-shadow: 1px 1px 0 0 var(--color-ink); }
}
```

> ⚠️ Font **self-host** (paket `@fontsource/...`), bukan dari CDN Google — agar tetap cepat di hosting murah dan tidak bergantung pihak ketiga.

---

## 3. Tipografi

| Peran | Font | Ukuran mobile → desktop | Weight | Catatan |
|---|---|---|---|---|
| Judul hero | Archivo Black | 32px → 64px | 400 (font sudah sangat tebal) | Kapital, leading rapat (`leading-[0.95]`) |
| Judul halaman | Archivo Black | 26px → 40px | 400 | — |
| Judul bagian | Plus Jakarta Sans | 20px → 28px | 800 | — |
| Subjudul kartu | Plus Jakarta Sans | 17px → 20px | 700 | — |
| Isi teks | Plus Jakarta Sans | 16px → 18px | 400 | Lebar baris maks. 68 karakter |
| Isi artikel sastra | Plus Jakarta Sans / serif opsi ⚠️ | 17px → 19px | 400 | `leading-relaxed`, paragraf lebih renggang |
| Label/badge | Plus Jakarta Sans | 11px → 12px | 800 | Kapital + `tracking-wide` |
| Data/angka | JetBrains Mono | sesuai konteks | 500 | Nomor anggota, kode, nominal uang |
| Catatan kaki | Plus Jakarta Sans | 13px → 14px | 500 | Warna `muted` |

**Ritme vertikal:** kelipatan 4px (`space-y-1`=4px … `space-y-16`=64px). Antar bagian halaman: `py-12` (mobile) → `py-20` (desktop).

---

## 4. Komponen Dasar

Setiap komponen punya **spesifikasi terkunci** supaya konsisten di seluruh halaman.

### Tombol

| Varian | Tampilan | Contoh pemakaian |
|---|---|---|
| `primary` | Latar `primary-600`, teks putih, border ink 3px, shadow brutal | "Daftar Sekarang", "Simpan" |
| `accent` | Latar `accent-400`, teks ink, border ink 3px, shadow brutal | "Lihat Program", CTA sekunder |
| `outline` | Latar `paper`, teks ink, border ink 3px, shadow brutal | "Batal", aksi netral |
| `danger` | Latar `danger`, teks putih, border ink | "Hapus", "Void" |
| `ghost` | Tanpa border & shadow, garis bawah tebal saat hover | Tautan dalam teks, aksi di tabel |
| Ukuran | `sm` 36px · `md` 44px (default) · `lg` 52px | Tinggi minimum **44px** untuk area sentuh |

Semua tombol: `brutal-hover` + `disabled:opacity-50 disabled:translate-none disabled:shadow-none` + fokus ring.

### Kartu

```text
┌──────────────────────────────┐   border 3px ink, radius 4px,
│  [ Badge ]          12 Sep    │   shadow 4px 4px 0 ink, latar paper
│  Judul Kartu Tebal            │   hover: naik 2px, shadow jadi 6px
│  Ringkasan dua baris…         │   gambar: border bawah 3px ink, rasio 16:9
│  ─────────────────────────    │
│  Meta · Penulis      →        │
└──────────────────────────────┘
```

- **Kartu artikel**: gambar + badge tipe (warna per tipe) + judul + ringkasan 2 baris + meta.
- **Kartu LSO**: latar warna bergantian per LSO (biru/kuning/putih) + logo + nama + tagline + jumlah pengurus.
- **Kartu statistik**: angka besar (mono) + label kapital + garis bawah tebal.
- **Kartu kosong** (`EmptyState`): ilustrasi garis + pesan + tombol aksi.

### Badge & Status

| Status | Latar | Teks |
|---|---|---|
| `terverifikasi` / `terbit` / `lunas` | `success` | Putih |
| `menunggu` / `draft` / `diproses` | `accent-400` | Ink |
| `ditolak` / `void` / `terlambat` | `danger` | Putih |
| `arsip` / `nonaktif` | `paper-alt` | `muted` |

Badge berbentuk kotak (bukan pill), border 2px ink, huruf kapital 11px.

### Form

- Label **selalu** di atas input, tebal, kapital.
- Input: latar `paper`, border 2px ink, radius 4px, tinggi 44px, fokus `ring-3 ring-primary-600`.
- Error: border `danger` + teks bantuan di bawah + ikon peringatan. **Bukan** hanya warna merah.
- Petunjuk di samping label: `(wajib)` / `(opsional)`.
- Pilihan (radio/checkbox): kotak tegas, area sentuh 44px, terpilih → latar `primary-50` + border tebal.
- Input file: kotak putus-putus tebal + tombol "Pilih berkas" + nama berkas terpilih.

### Tabel

- Header: latar `primary-600`, teks putih kapital, border tebal semua sisi.
- Baris: zebra `paper` / `paper-alt`, hover `primary-50`.
- **Mobile:** tabel berubah menjadi **daftar kartu** (bukan scroll horizontal) — kecuali tabel keuangan yang memang butuh lebar.

### Modal / Dialog

- Desktop: modal tengah, border 3px, shadow 8px, judul kapital tebal, footer berisi tombol.
- **Mobile: wajib pakai bottom sheet** (naik dari bawah) agar mudah dijangkau jempol.
- Aksi destruktif (hapus/void/cabut) selalu butuh konfirmasi + alasan bila relevan.

### Navigasi

| Elemen | Mobile (< 640px) | Desktop |
|---|---|---|
| Header | Logo + tombol menu (kotak) + ikon notifikasi | Logo + 7 menu utama + tombol "Masuk/Daftar" |
| Menu | Drawer penuh dari kiri (animasi slide) | Dropdown untuk menu beranak |
| **Bottom nav** | 5 item: Beranda · Publikasi · Pendaftaran · LSO · Akun | Tidak dipakai |
| Footer | Akordeon per grup | 4 kolom |
| Breadcrumb | Hanya halaman dalam | Selalu tampil |

---

## 5. Pola Interaksi (permintaan "lebih interaktif") ✅

Semua interaksi harus **ringan** (CSS + sedikit JS) agar aman di hosting murah.

| # | Interaksi | Di mana | Biaya | Fase |
|---|---|---|---|---|
| I1 | Hover naik + tekan turun pada kartu/tombol | Seluruh situs | 🟢 | 1 |
| I2 | Rangka berjalan (marquee) untuk pengumuman/pers release terbaru | Beranda, atas halaman | 🟢 | 3 |
| I3 | Penghitung angka bergerak (jumlah anggota, LSO, prestasi) | Beranda | 🟢 | 1 |
| I4 | Muncul saat digulir (fade/geser ringan) — `IntersectionObserver` | Semua halaman | 🟢 | 1 |
| I5 | Bottom sheet filter & pencarian | Direktori, perpustakaan | 🟡 | 4 |
| I6 | Carousel hero yang bisa digeser (swipe) + indikator kotak | Beranda | 🟡 | 1 |
| I7 | Baris kartu bisa digeser horizontal tanpa scrollbar (drag-to-scroll) | Prestasi, LSO | 🟡 | 4 |
| I8 | Chip filter yang bisa di-toggle + URL tersinkron `?filter=` | Publikasi, perpustakaan, alumni | 🟡 | 3 |
| I9 | Tab bergaris tebal dengan indikator bergerak | Profil kader, keuangan | 🟡 | 4 |
| I10 | Progress bar poin kontribusi + animasi naik | Dashboard kader | 🟡 | 8 |
| I11 | Papan peringkat kader teraktif (kartu podium) | Dashboard & publikasi internal | 🟡 | 8 |
| I12 | Skeleton loading (kotak berkedip) saat memuat data | Semua list | 🟢 | 1 |
| I13 | Efek "stempel" saat aksi sukses (verifikasi, publish, bayar) | Panel admin | 🟡 | 2 |
| I14 | Konfeti ringan saat kartu kader terbit / prestasi diverifikasi | Dashboard | 🟢 | 8 |
| I15 | Kalender kegiatan interaktif (klik tanggal → agenda) | Dashboard kader | 🔴 | 8 |
| I16 | Peta sebaran alumni: titik bisa diklik menampilkan nama | Halaman Alumni | 🔴 | 4 |
| I17 | Scan QR presensi dari kamera HP | Presensi kegiatan | 🔴 | 8 |
| I18 | Perpustakaan: status ketersediaan hidup + antrian bergerak | Perpustakaan | 🟡 | 6 |
| I19 | Editor tulisan dengan tinjauan langsung (WYSIWYG) | Submisi karya | 🔴 | 3 |
| I20 | Transisi antar halaman halus (View Transitions API bila didukung) | Seluruh situs | 🟢 | 9 |
| I21 | Mode gelap ⚠️ | Opsional | 🟡 | 9 |

**Preferensi kamu ✅: animasi halus saja.** Prioritas interaksi yang dikerjakan: **I1–I4, I8–I12, I20** (halus & murah).
**I2 (marquee)** dibuat lambat dan berhenti saat disentuh/di-hover. **I6/I11** tanpa gerak otomatis.
**I14 (konfeti)** default **nonaktif**. **I15–I17** disederhanakan bila terasa berat.

**Aturan gerak**
- Durasi 120–250ms, `ease-out`. Tidak ada animasi > 400ms untuk elemen interaktif.
- Hormati `prefers-reduced-motion` → semua animasi dimatikan, tampilan tetap benar.
- Animasi hanya untuk **memperjelas aksi**, bukan sekadar hiasan.
- Tidak ada animasi yang memicu *layout shift* (harus pakai `transform`/`opacity`, bukan `top/left/width`).

---

## 6. Mobile-First: Aturan Wajib

1. **Rancang di 360px dulu**, baru naikkan. Breakpoint: 640 → 768 → 1024 → 1280.
2. **Area sentuh minimal 44×44px**, jarak antar target minimal 8px.
3. **Tidak ada scroll horizontal** di semua halaman (kecuali carousel yang disengaja).
4. **Tabel → daftar kartu** di mobile.
5. **Modal → bottom sheet** di mobile.
6. **Bottom nav 5 item** sebagai navigasi utama di HP (situs ini paling banyak dibuka dari HP).
7. **Filter masuk ke bottom sheet**, bukan sidebar.
8. **Gambar**: `width`/`height` wajib diisi (cegah layout shift) + `loading="lazy"` + `srcset`.
9. Uji di **jaringan lambat** (throttle 3G): halaman publik target < 2,5 detik tampil.
10. Tidak ada teks di bawah 13px. Tidak ada satu pun teks yang terpotong.

---

## 7. Struktur Halaman Kunci (garis besar)

### Beranda

```text
┌─ Header sticky (logo + menu + masuk) ────────────┐
├─ HERO: judul besar 3 baris + 2 tombol + kolase    │
│  foto berbingkai tebal (komposisi miring 2°)      │
├─ MARQUEE pengumuman/pers release terbaru          │
├─ 4 KARTU STATISTIK (anggota, LSO, prestasi, tahun)│
├─ SAMBUTAN SINGKAT: foto ketua + 2 paragraf + ➜    │
├─ BERITA TERBARU: 1 kartu utama + 3 kartu kecil    │
├─ SAREKAT LSO: 5 kartu warna bergantian            │
├─ PRESTASI UNGGULAN: baris kartu geser             │
├─ CTA PENDAFTARAN: blok kuning besar + hitung mundur│
└─ Footer 4 kolom + sosmed ─────────────────────────┘
[Bottom nav — hanya di mobile]
```

### Detail Artikel
Judul besar → meta (penulis, tanggal, tipe, waktu baca) → gambar berbingkai → isi lebar 68 karakter →
kotak "Karya Penulis" + profil kader → artikel terkait → tombol bagikan (salin tautan).

### Katalog LSO (`/lso`)
5 kartu besar warna berbeda → klik masuk ke halaman LSO: hero berwarna khas LSO → visi/tagline →
pengurus (kartu kecil berbingkai) → kegiatan → karya/galeri → kontak LSO.

### Dashboard Kader
Kartu status keanggotaan (dengan QR) → 4 kartu ringkas (presensi, poin, pinjaman aktif, prestasi) →
agenda terdekat → pengumuman → bilah "lengkapi profil" bila belum 100%.

### Dashboard Admin
Sidebar (desktop) / drawer (mobile) → 4 kartu statistik → grafik sederhana → tugas menunggu
(verifikasi, pengajuan pinjam, aspirasi baru) → aktivitas terakhir.

---

## 8. Aksesibilitas (wajib)

- Kontras teks ≥ 4.5:1; teks besar ≥ 3:1. Warna `muted` tidak untuk teks < 14px.
- **Fokus terlihat**: `outline: 3px solid #0B0B0B; outline-offset: 2px` pada semua elemen interaktif.
- Semua gambar punya `alt` (wajib diisi di media library; validasi server menolak `alt` kosong).
- Form: setiap input punya `<label for>`; error diumumkan lewat `aria-live`.
- Navigasi keyboard penuh: skip-link "Lompat ke konten", urutan tab logis, drawer bisa ditutup `Esc`.
- Ikon dekoratif: `aria-hidden`. Status tidak pernah disampaikan lewat warna saja.
- Teks di gambar tidak boleh jadi satu-satunya sumber informasi.

---

## 9. Aset & Gambar

| Aset | Perlakuan |
|---|---|
| Logo PMII RAAB | Versi berwarna (header terang) & versi putih (di atas biru). Ukuran: `sm` 32px, `md` 48px, `lg` 96px |
| Logo LSO | Kotak berbingkai tebal, latar warna khas LSO, rasio 1:1 |
| Foto kegiatan | Rasio 16:9 & 4:3, dibingkai 3px ink + shadow |
| Foto kader | Rasio 1:1, berbingkai, `object-cover` |
| Sertifikat/prestasi | Diberi bingkai + ketebalan berbeda untuk tingkat nasional/internasional |
| Placeholder (sebelum ada foto) | Pola garis diagonal biru-kuning + ikon besar, **bukan** gambar abu-abu polos |
| Optimasi | Konversi WebP, thumbnail 96/480/1600px, maks. 5 MB per unggahan |

---

## 10. Checklist Implementasi Desain

**Fase 1**
- [ ] Token warna, font, shadow, utilitas `brutal*` terpasang di Tailwind
- [ ] Komponen dasar: Button, Card, Badge, Input, Select, Textarea, Checkbox, Table, Modal/BottomSheet, Toast, Tabs, Accordion, Pagination, EmptyState, Skeleton, Breadcrumb
- [ ] Layout publik (header sticky + drawer + bottom nav + footer)
- [ ] Layout admin (sidebar + drawer + tabel responsif)
- [ ] Beranda versi neo-brutalism lengkap (hero, marquee, statistik, kartu)
- [ ] Uji 360px & 1280px + uji aksesibilitas dasar

**Fase 3–4**
- [ ] Chip filter, bottom sheet filter, kartu geser, tab bergaris
- [ ] Kartu LSO berwarna khas + halaman LSO
- [ ] Peta alumni interaktif

**Fase 8–9**
- [ ] Kartu kader + QR, progress poin, papan peringkat, kalender
- [ ] Mikro-animasi akhir + uji `prefers-reduced-motion` + audit kontras

---

## 11. Mode Gelap ✅

Mode gelap **wajib ada** dan disiapkan sejak Fase 1 (bukan tambahan di akhir).

### Token warna gelap

| Token | Terang | **Gelap** | Catatan |
|---|---|---|---|
| `ink` (garis & teks) | `#0B0B0B` | `#F5F1E4` | Di mode gelap, **garis jadi terang** — ciri khas neo-brutalism gelap |
| `paper` (latar) | `#FFFDF5` | `#12120F` | Hitam hangat, bukan hitam pekat |
| `paper-alt` | `#F5F1E4` | `#1C1C18` | Kartu sekunder / baris tabel |
| `primary-600` | `#2E3192` | `#5A60D6` | Dicerahkan agar kontras di latar gelap |
| `primary-50` | `#EEF0FA` | `#171A2E` | Latar terpilih |
| `accent-400` | `#FFD400` | `#FFD400` | Kuning tetap (sudah sangat kontras) |
| Teks isi | `#0B0B0B` | `#F5F1E4` | Rasio ≥ 4.5:1 |
| Teks sekunder | `#6B6B5E` | `#A8A79C` | Naikkan kecerahan agar terbaca |
| Status berhasil/gagal | tetap | tetap | Warna status **tidak** diubah agar makna konsisten |

**Bayangan** di mode gelap memakai warna `ink` gelap-terang → `box-shadow: 4px 4px 0 0 #F5F1E4` (bayangan terang).

### Cara kerja

```css
/* Tailwind v4: strategi kelas, bukan prefers-color-scheme saja */
@custom-variant dark (&:where(.dark, .dark *));
```

| Aspek | Aturan |
|---|---|
| Pemicu | Tiga mode: **Terang**, **Gelap**, **Ikut Sistem** |
| Penyimpanan | `localStorage` + kolom `users.preferensi_tema` (bila login) |
| Anti-kedip | Skrip kecil di `<head>` menempelkan kelas `dark` **sebelum** halaman dirender |
| Gambar/foto | Tetap berbingkai tebal; logo versi putih dipakai di mode gelap |
| Placeholder | Pola diagonal menyesuaikan mode, jangan putih menyilaukan |
| Cetak | PDF (kartu kader, berita acara, laporan) **selalu versi terang** |
| Uji | Setiap halaman diuji di 4 kombinasi: terang/gelap × mobile/desktop |

## 12. Keputusan yang Sudah Dijawab ✅

| # | Jawabanmu | Konsekuensi |
|---|---|---|
| V1 | **Setuju** token warna — **hex difinalkan dari logo resmi**: biru `#2E3192`, biru terang `#1B75BB`, kuning `#FFD100`, ink `#0B0B0B` | Token warna dikunci |
| V2 | **Setuju & cocok** Archivo Black + Plus Jakarta Sans | Font self-host disiapkan |
| V3 | **Perlu mode gelap** | Ditambahkan sebagai §11; disiapkan sejak Fase 1 |
| V5 | **Perlu galeri & agenda sendiri**, dikelola **Konten Manager** | Tabel `galleries`, `gallery_items`, `unit_agendas` + permission baru |
| V6 | **Animasi halus saja** | Interaksi berat/berlebihan diturunkan prioritasnya |
| — | **Koreksi: frontend Blade + Tailwind + Inertia** | Halaman publik **Blade (server-rendered)**; panel admin & dashboard **Inertia + Vue**; interaksi halus **Alpine.js** |

## 13. Keputusan Desain Tambahan ✅ (diserahkan ke saya)

| # | Keputusan | Alasan |
|---|---|---|
| **V4** | **Sastra memakai font serif** (Lora), artikel biasa tetap sans-serif | Nuansa sastra lebih terasa, tetap mudah dibaca |
| **V7** | ✅ **Hanya satu berkas logo: `logo-pmii-raab.png`** (sudah ada di root workspace). Saya pindahkan ke `public/brand/logo-pmii-raab.png`. **Versi putih dibuat lewat CSS** — `filter: brightness(0) invert(1)` — untuk header biru & mode gelap, jadi **tidak perlu berkas terpisah**. Aset lain (favicon, ikon PWA, gambar Open Graph) memakai **placeholder** yang digenerate dari logo ini | Satu berkas cukup; tidak meminta berkas yang belum ada |
| **V8** | Mode gelap default: **Ikut sistem** | Menghormati preferensi perangkat; bisa diganti manual |
| **V9** | Galeri LSO: **satu galeri berisi banyak foto + filter album sederhana** | Cukup untuk kebutuhan LSO tanpa struktur berlapis yang menyulitkan pengurus |
| **V10** | Interaksi halaman publik memakai **Alpine.js**, bukan Vue | Sesuai keputusan Blade-first: halaman publik ringan, tanpa hidrasi |

## 14. Implementasi Blade vs Vue 🆕

| Area | Teknologi | Letak komponen |
|---|---|---|
| Halaman publik | **Blade + Tailwind + Alpine.js** | `resources/views/components/*` |
| Panel admin & dashboard | **Inertia + Vue 3 (TS)** | `resources/js/Components/*` |
| Token desain | Tailwind `@theme` | Dipakai bersama keduanya |
| Aturan | Komponen publik **tidak** memakai Vue; komponen admin **tidak** memakai Blade | Logika tetap di Controller/Action/Composable |

> ⚠️ Konsekuensi yang harus disadari: beberapa komponen inti (kartu, badge, tombol, pagination) akan ada **dua versi** — satu Blade, satu Vue. Ini wajar dan disengaja: versi Blade untuk halaman publik yang cepat & ramah SEO, versi Vue untuk panel admin yang interaktif.
