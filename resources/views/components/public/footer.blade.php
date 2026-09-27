@php
    // Identitas & tautan diambil dari Pengaturan Situs (dapat diubah pengurus
    // lewat panel), dengan nilai bawaan bila belum diisi.
    $situs = \App\Support\Pengaturan::semua();
    $sosmed = \App\Models\SocialLink::aktifTerCache();

    $navigasi = [
        ['label' => __('umum.menu.profil'), 'rute' => 'public.sejarah'],
        ['label' => __('umum.menu.organisasi'), 'rute' => 'public.struktur'],
        ['label' => __('umum.menu.publikasi'), 'rute' => 'public.publikasi'],
        ['label' => __('umum.menu.prestasi'), 'rute' => 'public.prestasi'],
    ];

    $layanan = [
        ['label' => __('umum.menu.kontak'), 'rute' => 'public.kontak'],
        ['label' => __('umum.menu.lokasi'), 'rute' => 'public.lokasi'],
        ['label' => __('umum.menu.media_sosial'), 'rute' => 'public.media-sosial'],
        ['label' => __('umum.menu.lso'), 'rute' => 'public.lso'],
        ['label' => __('umum.menu.perpustakaan'), 'rute' => 'public.perpustakaan'],
        ['label' => __('umum.menu.inventaris'), 'rute' => 'public.inventaris'],
        ['label' => __('umum.menu.pengumuman'), 'rute' => 'public.pengumuman'],
        ['label' => __('umum.menu.arsip'), 'rute' => 'public.arsip'],
        ['label' => __('umum.menu.aspirasi'), 'rute' => 'public.aspirasi'],
    ];
@endphp

<footer class="border-t-2 border-ink bg-brand-dark text-on-brand">
    <div class="mx-auto max-w-7xl px-4 py-10 lg:px-6">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
            {{-- Tentang --}}
            <div>
                <div class="flex items-center gap-3">
                    <img
                        src="{{ asset('brand/logo-pmii-raab.png') }}"
                        alt="Logo {{ __('umum.nama_organisasi') }}"
                        class="h-12 w-12 border-2 border-ink bg-on-brand object-contain p-0.5"
                        width="96" height="96" loading="lazy" decoding="async"
                    >
                    <span class="font-display text-base">{{ __('umum.nama_singkat') }}</span>
                </div>
                <p class="mt-4 text-sm leading-relaxed text-on-brand/85">{{ __('umum.footer.tentang_teks') }}</p>
            </div>

            {{-- Navigasi --}}
            <div>
                <h2 class="font-display text-sm uppercase tracking-wide">{{ __('umum.footer.navigasi') }}</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($navigasi as $item)
                        <li>
                            @if (Route::has($item['rute']))
                                <a href="{{ route($item['rute']) }}" class="hover:text-accent-400">{{ $item['label'] }}</a>
                            @else
                                <span class="text-on-brand/60">{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Layanan --}}
            <div>
                <h2 class="font-display text-sm uppercase tracking-wide">{{ __('umum.footer.layanan') }}</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($layanan as $item)
                        <li>
                            @if (Route::has($item['rute']))
                                <a href="{{ route($item['rute']) }}" class="hover:text-accent-400">{{ $item['label'] }}</a>
                            @else
                                <span class="text-on-brand/60">{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Kontak & sekretariat (placeholder sampai Fase 1 lanjutan) --}}
            <div>
                <h2 class="font-display text-sm uppercase tracking-wide">{{ __('umum.footer.kontak') }}</h2>
                <ul class="mt-4 space-y-3 text-sm text-on-brand/85">
                    <li class="flex gap-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z" stroke-linecap="square" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                        <span>{{ ($situs['alamat'] ?? '') !== '' ? $situs['alamat'] : __('umum.umum.alamat_placeholder') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 2" stroke-linecap="square" />
                        </svg>
                        <span>{{ ($situs['jam_operasional'] ?? '') !== '' ? $situs['jam_operasional'] : __('umum.umum.jam_placeholder') }}</span>
                    </li>
                    <li class="font-semibold">{{ $situs['email'] ?? '' }}</li>
                    <li class="text-on-brand/70">{{ $situs['telepon'] ?? '' }}</li>
                    <li class="font-semibold">{{ $situs['nama_kampus'] ?? __('umum.nama_kampus') }}</li>
                    <li class="text-on-brand/70">
                        {{ $situs['nama_komisariat'] ?? __('umum.nama_komisariat') }}
                        — {{ $situs['nama_cabang'] ?? __('umum.nama_cabang') }}
                    </li>
                </ul>
            </div>
        </div>

        {{-- Catatan data contoh: hilang sendiri setelah alamat diisi pengurus --}}
        @if (! str_contains((string) ($situs['alamat'] ?? ''), 'belum diisi'))
            {{-- alamat sudah diisi: tidak perlu pemberitahuan --}}
        @else
            <p class="mt-8 border-2 border-on-brand/40 px-3 py-2 text-xs text-on-brand/75">
                {{ $situs['catatan_placeholder'] ?? __('umum.umum.placeholder') }}
            </p>
        @endif

        <div class="mt-6 flex flex-col gap-3 border-t-2 border-on-brand/25 pt-6 text-xs text-on-brand/70 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} {{ $situs['nama_rayon'] ?? __('umum.nama_organisasi') }}. {{ __('umum.umum.hak_cipta') }}</p>

            <p class="flex flex-wrap items-center gap-2">
                <span>{{ __('umum.footer.ikuti') }}:</span>
                @forelse ($sosmed as $tautan)
                    @if ($tautan->url)
                        <a
                            href="{{ $tautan->url }}" target="_blank" rel="noopener noreferrer"
                            class="border-2 border-on-brand/50 px-2 py-0.5 font-bold hover:border-accent-400 hover:text-accent-400"
                        >{{ $tautan->label }}</a>
                    @else
                        <span class="border-2 border-on-brand/25 px-2 py-0.5 text-on-brand/55">
                            {{ $tautan->label }} — {{ __('umum.umum.segera') }}
                        </span>
                    @endif
                @empty
                    <span>{{ __('umum.umum.segera') }}</span>
                @endforelse
            </p>
        </div>
    </div>
</footer>
