@php
    /**
     * Menu utama publik — hanya tampil bila rutenya sudah tersedia,
     * sehingga navigasi tidak pernah menautkan ke halaman kosong.
     */
    $situs = \App\Support\Pengaturan::semua();

    $menu = [
        ['label' => __('umum.menu.beranda'), 'rute' => 'public.beranda', 'anak' => []],
        ['label' => __('umum.menu.profil'), 'rute' => 'public.sejarah', 'anak' => [
            ['label' => __('umum.submenu.sejarah'), 'rute' => 'public.sejarah'],
            ['label' => __('umum.submenu.visi_misi'), 'rute' => 'public.visi-misi'],
            ['label' => __('umum.submenu.sambutan'), 'rute' => 'public.sambutan'],
        ]],
        ['label' => __('umum.menu.organisasi'), 'rute' => 'public.struktur', 'anak' => [
            ['label' => __('umum.submenu.struktur'), 'rute' => 'public.struktur'],
            ['label' => __('umum.submenu.anggota'), 'rute' => 'public.anggota'],
            ['label' => __('umum.menu.lso'), 'rute' => 'public.lso'],
            ['label' => __('umum.submenu.alumni'), 'rute' => 'public.alumni'],
        ]],
        /*
         * Rute artikel adalah `public.publikasi.tipe` BERPARAMETER, bukan satu
         * rute per tipe. Kelima butir di bawah dulu menulis
         * `public.publikasi.berita` dan kawan-kawan — nama yang tidak pernah
         * didaftarkan. Karena setiap butir dibungkus `Route::has()`, semuanya
         * jatuh ke cabang <span> dan seluruh dropdown menjadi teks mati yang
         * tidak bisa diklik, tanpa satu pun galat.
         */
        ['label' => __('umum.menu.publikasi'), 'rute' => 'public.publikasi', 'anak' => [
            ['label' => __('umum.submenu.berita'), 'rute' => 'public.publikasi.tipe', 'params' => ['tipe' => 'berita']],
            ['label' => __('umum.submenu.opini'), 'rute' => 'public.publikasi.tipe', 'params' => ['tipe' => 'opini']],
            ['label' => __('umum.submenu.kajian'), 'rute' => 'public.publikasi.tipe', 'params' => ['tipe' => 'kajian']],
            ['label' => __('umum.submenu.esai'), 'rute' => 'public.publikasi.tipe', 'params' => ['tipe' => 'esai']],
            ['label' => __('umum.submenu.sastra'), 'rute' => 'public.publikasi.tipe', 'params' => ['tipe' => 'sastra']],
            ['label' => __('umum.menu.galeri'), 'rute' => 'public.galeri'],
        ]],
        /*
         * Tanpa submenu. Dulu di sini ada tautan "Profil Kader" ke
         * `public.prestasi.kader` — rute itu butuh slug kader tertentu, jadi
         * menu ini tidak akan pernah bisa dibuat dan membuat SELURUH halaman
         * publik gagal dengan UrlGenerationException begitu rutenya ada.
         * Halaman /prestasi sendiri yang menautkan ke profil tiap kader.
         */
        ['label' => __('umum.menu.prestasi'), 'rute' => 'public.prestasi', 'anak' => []],
        ['label' => __('umum.menu.layanan'), 'rute' => 'public.kontak', 'anak' => [
            ['label' => __('umum.menu.kontak'), 'rute' => 'public.kontak'],
            ['label' => __('umum.menu.lokasi'), 'rute' => 'public.lokasi'],
            ['label' => __('umum.menu.media_sosial'), 'rute' => 'public.media-sosial'],
            ['label' => __('umum.menu.perpustakaan'), 'rute' => 'public.perpustakaan'],
            ['label' => __('umum.menu.inventaris'), 'rute' => 'public.inventaris'],
            ['label' => __('umum.menu.pengumuman'), 'rute' => 'public.pengumuman'],
            ['label' => __('umum.menu.arsip'), 'rute' => 'public.arsip'],
            ['label' => __('umum.menu.aspirasi'), 'rute' => 'public.aspirasi'],
        ]],
    ];
@endphp

<header
    x-data="{ buka: false }"
    @keydown.escape.window="buka = false"
    class="sticky top-0 z-40 border-b-2 border-ink bg-brand text-on-brand"
>
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 lg:px-6" aria-label="{{ __('umum.menu.layanan') }}">
        {{-- Identitas --}}
        <a href="{{ route('public.beranda') }}" class="flex min-w-0 items-center gap-3">
            {{-- Berkas logo belum transparan, jadi ditampilkan di dalam kotak putih
                 dan TIDAK difilter menjadi putih (akan menjadi kotak kosong). --}}
            <img
                src="{{ asset('brand/logo-pmii-raab.png') }}"
                alt="Logo {{ __('umum.nama_organisasi') }}"
                class="h-10 w-10 shrink-0 border-2 border-ink bg-on-brand object-contain p-0.5 lg:h-12 lg:w-12"
                width="96" height="96" loading="eager" decoding="async"
            >
            <span class="min-w-0">
                <span class="block truncate font-display text-sm leading-tight lg:text-base">{{ __('umum.nama_singkat') }}</span>
                    <span class="block truncate text-[11px] leading-tight text-on-brand/75">{{ $situs['nama_komisariat'] ?? __('umum.nama_komisariat') }}</span>
            </span>
        </a>

        {{-- Menu desktop --}}
        <ul class="hidden items-center gap-1 lg:flex">
            @foreach ($menu as $item)
                @php $adaInduk = Route::has($item['rute']); @endphp
                <li class="relative" @if (count($item['anak'])) x-data="{ sub: false }" @mouseenter="sub = true" @mouseleave="sub = false" @endif>
                    @if ($adaInduk)
                        <a
                            href="{{ route($item['rute']) }}"
                            class="block border-2 border-transparent px-3 py-2 text-sm font-bold hover:border-ink hover:bg-accent-400 hover:text-primary-800"
                            @if (count($item['anak'])) aria-haspopup="true" :aria-expanded="sub" @endif
                        >{{ $item['label'] }}</a>
                    @else
                        <span
                            class="block cursor-not-allowed border-2 border-transparent px-3 py-2 text-sm font-bold text-on-brand/50"
                            title="{{ __('umum.umum.segera') }}"
                        >{{ $item['label'] }}</span>
                    @endif

                    @if (count($item['anak']))
                        <ul
                            x-show="sub" x-transition.opacity.duration.150ms
                            class="absolute left-0 top-full z-50 hidden w-64 border-2 border-ink bg-paper py-1 text-ink shadow-brutal lg:block"
                        >
                            @foreach ($item['anak'] as $anak)
                                <li>
                                    @if (Route::has($anak['rute']))
                                        <a href="{{ route($anak['rute'], $anak['params'] ?? []) }}" class="block px-4 py-2 text-sm font-semibold hover:bg-accent-400">
                                            {{ $anak['label'] }}
                                        </a>
                                    @else
                                        <span class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-muted">
                                            {{ $anak['label'] }}
                                            <span class="border-2 border-ink bg-paper-alt px-1 text-[10px] font-bold uppercase">{{ __('umum.umum.segera') }}</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>

        {{-- Aksi kanan --}}
        <div class="flex items-center gap-2">
            <x-public.locale-switch />

            <div class="hidden lg:block">
                <x-public.theme-toggle />
            </div>

            @auth
                <a
                    href="{{ route('panel') }}"
                    class="hidden border-2 border-ink bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800 brutal-hover lg:block"
                >{{ __('umum.tombol.panel') }}</a>
            @else
                @if (Route::has('login'))
                    <a
                        href="{{ route('login') }}"
                        class="hidden border-2 border-ink bg-paper px-4 py-2 text-sm font-bold text-ink brutal-hover lg:block"
                    >{{ __('umum.tombol.masuk') }}</a>
                @endif
            @endauth

            @if (Route::has('public.pendaftaran'))
                <a
                    href="{{ route('public.pendaftaran') }}"
                    class="hidden border-2 border-ink bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800 brutal-hover sm:block"
                >{{ __('umum.tombol.daftar_sekarang') }}</a>
            @endif

            {{-- Tombol menu mobile --}}
            <button
                type="button"
                class="border-2 border-ink bg-paper p-2 text-ink lg:hidden"
                @click="buka = true"
                aria-controls="menu-mobile"
                :aria-expanded="buka"
                aria-label="{{ __('umum.menu.organisasi') }}"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="square" />
                </svg>
            </button>
        </div>
    </nav>

    {{-- Laci menu mobile --}}
    <div
        id="menu-mobile"
        x-show="buka"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 w-[86%] max-w-sm overflow-y-auto border-r-2 border-ink bg-paper text-ink lg:hidden"
        x-cloak
    >
        <div class="flex items-center justify-between border-b-2 border-ink bg-brand px-4 py-3 text-on-brand">
            <span class="font-display text-sm">{{ __('umum.menu.layanan') }}</span>
            <button
                type="button"
                class="border-2 border-ink bg-paper p-1 text-ink"
                @click="buka = false"
                aria-label="{{ __('umum.tombol.tutup') }}"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" stroke-linecap="square" />
                </svg>
            </button>
        </div>

        <ul class="divide-y-2 divide-ink/15">
            @foreach ($menu as $item)
                <li x-data="{ sub: false }">
                    <div class="flex items-stretch">
                        @if (Route::has($item['rute']))
                            <a href="{{ route($item['rute']) }}" class="flex-1 px-4 py-3 font-bold">{{ $item['label'] }}</a>
                        @else
                            <span class="flex-1 px-4 py-3 font-bold text-muted">{{ $item['label'] }}</span>
                        @endif

                        @if (count($item['anak']))
                            <button type="button" class="border-l-2 border-ink px-4" @click="sub = ! sub" :aria-expanded="sub" aria-label="{{ $item['label'] }}">
                                <svg class="h-4 w-4 transition-transform" :class="sub && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path d="M6 9l6 6 6-6" stroke-linecap="square" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    @if (count($item['anak']))
                        <ul x-show="sub" x-transition.opacity.duration.150ms class="bg-paper-alt">
                            @foreach ($item['anak'] as $anak)
                                <li>
                                    @if (Route::has($anak['rute']))
                                        <a href="{{ route($anak['rute'], $anak['params'] ?? []) }}" class="block px-6 py-2 text-sm font-semibold">{{ $anak['label'] }}</a>
                                    @else
                                        <span class="block px-6 py-2 text-sm font-semibold text-muted">{{ $anak['label'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="flex items-center gap-3 border-t-2 border-ink p-4">
            <x-public.locale-switch />
            <x-public.theme-toggle />
        </div>
    </div>

    {{-- Latar gelap saat laci terbuka --}}
    <div
        x-show="buka" x-transition.opacity.duration.150ms
        class="fixed inset-0 z-40 bg-ink/60 lg:hidden"
        @click="buka = false"
        x-cloak
        aria-hidden="true"
    ></div>
</header>
