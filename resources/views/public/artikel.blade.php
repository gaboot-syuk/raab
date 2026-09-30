@extends('layouts.public')

@php
    $sastra = $artikel->tipe === \App\Models\Article::TIPE_SASTRA;
    $slug = $artikel->getTranslation('slug', 'id', false);

    /*
     * Judul & deskripsi untuk mesin pencari.
     *
     * Pengurus bisa mengisi judul SEO sendiri — kadang judul yang enak dibaca
     * manusia terlalu panjang untuk hasil pencarian. Kalau diisi, itu yang
     * dipakai; kalau kosong, judul aslinya. Mengosongkannya bukan kesalahan,
     * jadi keduanya harus tetap menghasilkan sesuatu yang masuk akal.
     */
    $judulSeo = $artikel->seo_judul ?: $artikel->judul;
    $deskripsiSeo = $artikel->seo_deskripsi
        ?: ($artikel->ringkasan ?: \Illuminate\Support\Str::limit(strip_tags((string) $artikel->konten), 160));

    $sampul = $artikel->cover_media_id
        ? \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($artikel->cover_media_id)?->getUrl()
        : null;
@endphp

@section('judul', $judulSeo)

{{--
    Gambar pratinjau artikel memakai GAMBAR COVERNYA SENDIRI, bukan gambar
    halaman publikasi. Inilah yang membuat setiap artikel punya pratinjau yang
    berbeda saat tautannya dibagikan — dan gambar itulah yang paling mewakili
    isinya. Halaman tetap kebagian gambar bawaan bila artikelnya belum punya
    cover.
--}}
@section('og_gambar', $sampul ?? asset('og/publikasi.png'))
@section('deskripsi', $deskripsiSeo)
@section('og_tipe', 'article')

@push('kepala')
    {{--
        Waktu terbit & bagian artikel.
        og:title, og:description, og:image, dan og:type TIDAK ditulis di sini:
        kerangka halaman sudah menuliskannya dari @section di atas. Menulisnya
        dua kali membuat mesin pencari membaca dua nilai berbeda untuk satu
        halaman yang sama.
    --}}
    @if ($artikel->terbit_pada)
        <meta property="article:published_time" content="{{ $artikel->terbit_pada->toIso8601String() }}">
        <meta property="article:modified_time" content="{{ $artikel->updated_at?->toIso8601String() }}">
    @endif
    <meta property="article:section" content="{{ $artikel->labelTipe() }}">

    {{--
        Data terstruktur artikel. Ini yang membuat hasil pencarian menampilkan
        judul, tanggal, dan penulis dengan rapi — bukan sekadar tautan biru.
        Nilai kosong dibuang lebih dulu supaya mesin pencari tidak menerima
        kolom hampa.
    --}}
    <script type="application/ld+json">
        {!! json_encode(array_filter([
            // Kunci pertama ditulis terpisah karena Blade memperlakukan
            // kata itu sebagai direktif bila ditulis utuh — lihat catatan
            // lengkapnya di layouts/public.blade.php.
            '@'.'context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => \Illuminate\Support\Str::limit($artikel->judul, 110),
            'description' => \Illuminate\Support\Str::limit(strip_tags($deskripsiSeo), 300),
            'inLanguage' => app()->getLocale(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => url()->current(),
            ],
            'datePublished' => $artikel->terbit_pada?->toIso8601String(),
            'dateModified' => $artikel->updated_at?->toIso8601String(),
            'articleSection' => $artikel->labelTipe(),
            'author' => $artikel->penulis?->name ? [
                '@type' => 'Person',
                'name' => $artikel->penulis->name,
            ] : null,
            'publisher' => [
                '@type' => 'Organization',
                'name' => \App\Support\Pengaturan::ambil('nama_rayon', __('umum.nama_organisasi')),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('brand/logo-pmii-raab.png'),
                ],
            ],
            // getUrl() sudah mengembalikan alamat lengkap, jadi tidak perlu
            // dibungkus asset() lagi.
            'image' => $sampul ? [$sampul] : null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('konten')
    <article class="mx-auto {{ $sastra ? 'max-w-2xl' : 'max-w-3xl' }} px-4 py-10 lg:py-14">
        <nav class="text-xs font-bold uppercase tracking-wide text-muted" aria-label="{{ __('umum.menu.beranda') }}">
            <a href="{{ route('public.beranda') }}" class="underline">{{ __('umum.menu.beranda') }}</a>
            <span aria-hidden="true"> › </span>
            <a href="{{ route('public.publikasi.tipe', $artikel->tipe) }}" class="underline">{{ $artikel->labelTipe() }}</a>
        </nav>

        <header class="mt-5 border-b-2 border-ink pb-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="border-2 border-ink bg-accent-400 px-2 py-0.5 text-[11px] font-bold uppercase text-primary-800">
                    {{ $artikel->labelTipe() }}
                </span>
                @if ($artikel->kategori)
                    <span class="border-2 border-ink bg-paper-alt px-2 py-0.5 text-[11px] font-bold uppercase">
                        {{ $artikel->kategori->nama }}
                    </span>
                @endif
            </div>

            <h1 class="mt-4 font-display text-3xl leading-tight sm:text-4xl">{{ $artikel->judul }}</h1>

            @if ($artikel->ringkasan)
                <p class="mt-3 text-lg text-muted">{{ $artikel->ringkasan }}</p>
            @endif

            <p class="mt-4 text-sm text-muted">
                {{ __('umum.publikasi.oleh') }} <span class="font-bold text-ink">{{ $artikel->penulis?->name }}</span>
                · {{ $artikel->terbit_pada?->translatedFormat('d F Y') }}
                @if ($artikel->waktu_baca_menit)
                    · {{ $artikel->waktu_baca_menit }} {{ __('umum.publikasi.menit') }}
                @endif
                · {{ $artikel->dilihat }}x {{ __('umum.publikasi.dibaca') }}
            </p>
        </header>

        @if ($artikel->kelengkapanTerjemahan() < 100 && app()->getLocale() !== 'id')
            <p class="brutal-sm mt-6 border-accent-600 bg-accent-100 px-3 py-2 text-xs font-semibold">
                {{ __('umum.umum.belum_terjemah') }}
            </p>
        @endif

        {{-- ===== Isi ===== --}}
        {{-- Sastra dibaca dengan tipografi yang lebih lapang: huruf serif,
             paragraf berjarak, dan lebar baris yang nyaman. --}}
        <div
            @class([
                'mt-8 space-y-4 leading-relaxed',
                'font-serif text-lg leading-[1.9]' => $sastra,
                'text-base' => ! $sastra,
            ])
        >
            {!! $artikel->konten !!}
        </div>

        @if ($artikel->tags->isNotEmpty())
            <ul class="mt-8 flex flex-wrap gap-1 border-t-2 border-ink pt-4">
                @foreach ($artikel->tags as $tag)
                    <li>
                        <a
                            href="{{ route('public.publikasi', ['tag' => $tag->getTranslation('slug', 'id', false)]) }}"
                            class="inline-block border-2 border-ink bg-paper-alt px-2 py-0.5 text-xs font-bold hover:bg-accent-100"
                        >#{{ $tag->nama }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- ===== Berbagi ===== --}}
        <section class="mt-8 border-t-2 border-ink pt-5">
            <h2 class="text-xs font-bold uppercase tracking-wide text-muted">{{ __('umum.publikasi.bagikan') }}</h2>
            @php $tautan = url()->current(); @endphp
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="https://wa.me/?text={{ urlencode($artikel->judul.' '.$tautan) }}" target="_blank" rel="noopener noreferrer" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">WhatsApp</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($tautan) }}" target="_blank" rel="noopener noreferrer" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode($tautan) }}&text={{ urlencode($artikel->judul) }}" target="_blank" rel="noopener noreferrer" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">X</a>
                <button
                    type="button"
                    class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold"
                    x-data="{ tersalin: false }"
                    @click="navigator.clipboard.writeText('{{ $tautan }}').then(() => { tersalin = true; setTimeout(() => tersalin = false, 2000) })"
                    x-text="tersalin ? '{{ __('umum.publikasi.tautan_tersalin') }}' : '{{ __('umum.publikasi.salin_tautan') }}'"
                >{{ __('umum.publikasi.salin_tautan') }}</button>
            </div>
        </section>

        {{-- ===== Navigasi antar artikel ===== --}}
        @if ($sebelumnya || $berikutnya)
            <nav class="mt-8 grid gap-3 border-t-2 border-ink pt-5 sm:grid-cols-2" aria-label="{{ __('umum.publikasi.navigasi') }}">
                @if ($sebelumnya)
                    <a href="{{ route('public.publikasi.detail', ['tipe' => $sebelumnya->tipe, 'slug' => $sebelumnya->getTranslation('slug', 'id', false)]) }}" class="brutal-sm block bg-paper p-3 hover:bg-accent-100">
                        <span class="text-xs font-bold uppercase text-muted">← {{ __('umum.publikasi.sebelumnya') }}</span>
                        <span class="mt-1 block text-sm font-bold">{{ $sebelumnya->judul }}</span>
                    </a>
                @endif

                @if ($berikutnya)
                    <a href="{{ route('public.publikasi.detail', ['tipe' => $berikutnya->tipe, 'slug' => $berikutnya->getTranslation('slug', 'id', false)]) }}" class="brutal-sm block bg-paper p-3 hover:bg-accent-100 sm:col-start-2 sm:text-right">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('umum.publikasi.berikutnya') }} →</span>
                        <span class="mt-1 block text-sm font-bold">{{ $berikutnya->judul }}</span>
                    </a>
                @endif
            </nav>
        @endif

        {{-- ===== Artikel terkait ===== --}}
        @if ($terkait->isNotEmpty())
            <section class="mt-10 border-t-2 border-ink pt-6">
                <h2 class="font-display text-xl">{{ __('umum.publikasi.terkait') }}</h2>
                <ul class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ($terkait as $lain)
                        @include('public.partials.kartu-artikel', ['artikel' => $lain, 'sorotan' => true])
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
@endsection
