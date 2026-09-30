@php
    /*
     * Identitas situs dibaca dari pengaturan (sudah di-cache), bukan ditulis di
     * kode, supaya label yang muncul di hasil pencarian ikut berubah begitu
     * pengurus mengubahnya di panel.
     *
     * `seo_judul`/`seo_deskripsi` dipakai sebagai CADANGAN, bukan sebagai
     * pengganti: halaman yang punya judul sendiri tetap memakainya, dan hanya
     * halaman yang tidak punya (mis. beranda) yang jatuh ke nilai bawaan itu.
     */
    $situsSeo = \App\Support\Pengaturan::semua();
    $namaSitus = $situsSeo['nama_rayon'] ?? __('umum.nama_organisasi');
    $judulHalaman = trim($__env->yieldContent('judul')) ?: ($situsSeo['seo_judul'] ?? $namaSitus);
    $deskripsiHalaman = trim($__env->yieldContent('deskripsi')) ?: ($situsSeo['seo_deskripsi'] ?? __('umum.footer.tentang_teks'));
    /*
     * Gambar pratinjau tautan (og:image), berurut dari yang paling khusus:
     *
     *   1. @section('og_gambar') — gambar halaman itu sendiri. Artikel memakai
     *      gambar covernya, dan itulah yang paling mewakili isi tautannya.
     *   2. `og_image` pada Pengaturan Situs — SATU gambar seragam untuk seluruh
     *      situs.
     *   3. gambar bawaan di public/og/.
     *
     * Yang paling khusus menang: artikel yang punya cover tetap memakai
     * covernya walau ada gambar seragam.
     *
     * CATATAN JUJUR soal langkah 2: `og_image` TIDAK ADA di seeder maupun di
     * panel, jadi hari ini langkah itu tidak pernah terpakai — nilainya selalu
     * kosong dan yang berjalan selalu langkah 3. Barisnya sengaja tetap ada
     * supaya menambahkan pengaturan itu kelak cukup dilakukan di panel, tanpa
     * perlu menyentuh berkas ini. Percobaan pertama saya menuliskan seolah
     * pengurus memang bisa menyetelnya; itu tidak benar, dan komentar yang
     * menjanjikan hal yang tidak ada lebih buruk daripada tidak ada komentar.
     *
     * Alamatnya WAJIB lengkap (ada https://…). Pengikis tautan WhatsApp dan
     * Facebook tidak menjalankan kode kita; alamat relatif tidak bisa mereka
     * selesaikan. Karena itu di sini url()/asset() memang benar dipakai —
     * berbeda dengan gambar DI DALAM halaman, yang justru harus relatif supaya
     * tidak diblokir CSP saat APP_URL tidak sama dengan domain yang dibuka.
     */
    $gambarHalaman = trim($__env->yieldContent('og_gambar'));
    $gambarSeo = $gambarHalaman !== '' ? $gambarHalaman : trim((string) ($situsSeo['og_image'] ?? ''));

    if ($gambarSeo !== '' && ! str_starts_with($gambarSeo, 'http')) {
        $gambarSeo = url($gambarSeo);
    }

    $gambarSeo = $gambarSeo !== '' ? $gambarSeo : asset('og/bawaan.png');
    $urlSeo = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL(app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $judulHalaman }} — {{ __('umum.nama_singkat') }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($deskripsiHalaman), 300) }}">

    {{-- Bahasa alternatif untuk mesin pencari (bilingual) --}}
    <link rel="canonical" href="{{ $urlSeo }}">
    @foreach (\Mcamara\LaravelLocalization\Facades\LaravelLocalization::getSupportedLocales() as $kode => $info)
        <link rel="alternate" hreflang="{{ $kode }}" href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL($kode) }}">
    @endforeach

    {{--
        Open Graph & Twitter Card.
        Judul dan deskripsi memakai NILAI YANG SAMA dengan halaman, sehingga
        yang dilihat orang di media sosial tidak berbeda dari yang dilihat
        mesin pencari. Dua sumber yang berbeda akan cepat berbeda isinya.
    --}}
    <meta property="og:type" content="@yield('og_tipe', 'website')">
    <meta property="og:site_name" content="{{ $namaSitus }}">
    <meta property="og:title" content="{{ $judulHalaman }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($deskripsiHalaman), 300) }}">
    <meta property="og:url" content="{{ $urlSeo }}">
    <meta property="og:image" content="{{ $gambarSeo }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'id' ? 'id_ID' : 'en_US' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $judulHalaman }}">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($deskripsiHalaman), 300) }}">
    <meta name="twitter:image" content="{{ $gambarSeo }}">

    {{--
        Pintasan ke layar utama.

        `display: standalone` di dalam manifest-lah yang membuat pintasannya
        terbuka TANPA bilah alamat — di situlah bedanya dengan sekadar penanda
        buku. Tag `apple-*` diperlukan karena iOS tidak membaca sebagian besar
        isi manifest dan tidak punya API pemasangan sama sekali.

        TIDAK ADA service worker di sini, dan itu disengaja: situs ini tidak
        dirancang untuk dibaca tanpa jaringan. Karena itu tidak ada satu byte
        pun yang disimpan di perangkat pengunjung.
    --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" sizes="180x180" href="/ikon/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $situsSeo['nama_singkat'] ?? __('umum.nama_singkat') }}">
    {{--
        `default`, BUKAN `black-translucent`.

        Yang translucent memang terlihat lebih bagus — isinya sampai ke tepi
        layar — tetapi bilah status lalu menimpa bagian atas halaman, dan
        navigasi yang menempel di atas akan tertutup jam serta indikator
        baterai. Situs ini belum punya penyesuaian `env(safe-area-inset-*)`,
        jadi yang aman dipakai dulu.
    --}}
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    {{--
        Dua baris dengan `media` supaya warna bilahnya ikut mode gelap.
    --}}
    <meta name="theme-color" content="#2e3192" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#12120f" media="(prefers-color-scheme: dark)">

    {{--
        Data terstruktur organisasi. Ditulis sekali di kerangka halaman karena
        berlaku untuk SELURUH halaman, bukan hanya beranda — mesin pencari
        membaca data ini untuk mengenali rayon sebagai sebuah organisasi,
        lengkap dengan logo dan alamatnya.
    --}}
    <script type="application/ld+json">
        {!! json_encode(array_filter([
            /*
             * Kunci pertama DITULIS TERPISAH, sengaja.
             *
             * Kalau ia ditulis utuh, Blade mengira itu sebuah direktif dan
             * menggantinya dengan kode PHP — kuncinya berubah menjadi potongan
             * program, dan data terstrukturnya rusak tanpa satu pun galat.
             * Ditulis terpisah begini, Blade tidak mengenalinya sebagai
             * direktif. Jangan disatukan kembali.
             */
            '@'.'context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $namaSitus,
            'alternateName' => $situsSeo['nama_singkat'] ?? __('umum.nama_singkat'),
            'url' => url('/'),
            'logo' => asset('brand/logo-pmii-raab.png'),
            'description' => \Illuminate\Support\Str::limit(strip_tags($deskripsiHalaman), 300),
            'email' => $situsSeo['email'] ?? null,
            'telephone' => $situsSeo['telepon'] ?? null,
            'address' => isset($situsSeo['alamat']) ? [
                '@type' => 'PostalAddress',
                'streetAddress' => $situsSeo['alamat'],
                'addressCountry' => 'ID',
            ] : null,
            'sameAs' => null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    {{-- Anti-kedip: pasang kelas tema sebelum halaman dirender --}}
    <script>
        (function () {
            try {
                var tema = localStorage.getItem('tema') || 'sistem';
                var gelap = tema === 'gelap' ||
                    (tema === 'sistem' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', gelap);
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('kepala')
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <a
        href="#konten"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:brutal focus:bg-accent-400 focus:px-4 focus:py-2 focus:font-bold"
    >{{ __('umum.umum.lompat_konten') }}</a>

    <x-public.navbar />

    <main id="konten" class="pb-20 lg:pb-0">
        @yield('konten')
    </main>

    <x-public.footer />
    <x-public.bottom-nav />

    @stack('skrip')
</body>
</html>
