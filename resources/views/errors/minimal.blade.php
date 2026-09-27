@php
    /*
     * Halaman galat SENGAJA berdiri sendiri: tanpa layout, tanpa @vite, tanpa
     * pengaturan dari basis data.
     *
     * Alasannya bukan kerapian, melainkan ketahanan. Halaman galat muncul justru
     * ketika ada yang rusak — basis data tidak bisa dihubungi, berkas aset belum
     * terbangun, atau cache bermasalah. Kalau halaman galatnya sendiri ikut
     * memanggil basis data dan memuat bundel aset, ia akan gagal dengan cara yang
     * sama, dan pengunjung hanya melihat halaman putih.
     *
     * Karena itu seluruh gayanya ditulis langsung di sini, dan tidak ada satu
     * pun nilai yang diambil dari luar berkas ini.
     */
    $kode = $kode ?? 500;
    $warna = [
        403 => ['#FFD100', 'Akses ditolak'],
        404 => ['#1B75BB', 'Halaman tidak ditemukan'],
        419 => ['#FFD100', 'Sesi sudah berakhir'],
        429 => ['#FFD100', 'Terlalu banyak permintaan'],
        500 => ['#FF6B6B', 'Ada yang rusak di sisi kami'],
        503 => ['#FFD100', 'Sedang dalam perawatan'],
    ][$kode] ?? ['#FFD100', 'Terjadi kesalahan'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $kode }} — {{ $warna[1] }} | PMII RAAB</title>
    <link rel="icon" href="/brand/logo-pmii-raab.png">
    <style>
        :root { --ink: #0B0B0B; --paper: #FFFDF5; --paper-alt: #F5F0E1; --brand: #2E3192; --muted: #5A5A5A; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: var(--paper); color: var(--ink); padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
        }
        .kotak { width: 100%; max-width: 560px; border: 3px solid var(--ink); background: var(--paper); }
        .kepala { border-bottom: 3px solid var(--ink); background: {{ $warna[0] }}; padding: 20px 24px; }
        .kode { font-size: 44px; font-weight: 800; letter-spacing: -1px; margin: 0; }
        .judul { margin: 4px 0 0; font-size: 18px; font-weight: 700; }
        .isi { padding: 24px; }
        .isi p { margin: 0 0 12px; }
        .petunjuk { color: var(--muted); font-size: 14px; }
        .aksi { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        a.tombol {
            display: inline-block; border: 3px solid var(--ink); background: var(--brand); color: #fff;
            padding: 10px 18px; font-weight: 700; text-decoration: none;
        }
        a.tombol.sekunder { background: var(--paper-alt); color: var(--brand); }
        .kaki { border-top: 3px solid var(--ink); padding: 14px 24px; font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
    <main class="kotak">
        <div class="kepala">
            <p class="kode">{{ $kode }}</p>
            <p class="judul">{{ $warna[1] }}</p>
        </div>

        <div class="isi">
            <p>{{ $pesan ?? '' }}</p>
            <p class="petunjuk">{{ $saran ?? '' }}</p>

            <div class="aksi">
                <a class="tombol" href="/">Kembali ke beranda</a>
                <a class="tombol sekunder" href="/kontak">Hubungi sekretariat</a>
            </div>
        </div>

        <div class="kaki">
            PMII Rayon Ali Ahmad Baktsir — Komisariat Raden Mas Said.
            @if ($kode >= 500)
                <br>Kalau ini terjadi berulang, sebutkan jam kejadiannya saat menghubungi pengurus.
            @endif
        </div>
    </main>
</body>
</html>
