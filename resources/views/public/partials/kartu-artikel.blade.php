@php
    /**
     * Satu kartu artikel pada daftar publikasi.
     * Variabel: $artikel, $sorotan (bool).
     */
    $judul = $artikel->judul;
    $ringkasan = $artikel->ringkasan;
    $slug = $artikel->getTranslation('slug', app()->getLocale(), false)
        ?: $artikel->getTranslation('slug', 'id', false);
    $tautan = route('public.publikasi.detail', ['tipe' => $artikel->tipe, 'slug' => $slug]);
@endphp

<li class="brutal bg-paper {{ $sorotan ? 'flex flex-col p-5' : 'p-5 sm:flex sm:gap-5' }}">
    @if ($sorotan)
        <p class="text-xs font-bold uppercase tracking-wide text-muted">
            {{ $artikel->kategori?->nama ?? $artikel->labelTipe() }}
        </p>

        <h3 class="mt-2 font-display text-lg leading-tight">
            <a href="{{ $tautan }}" class="hover:underline">{{ $judul }}</a>
        </h3>

        @if ($ringkasan)
            <p class="mt-2 line-clamp-3 text-sm text-muted">{{ $ringkasan }}</p>
        @endif

        <p class="mt-auto pt-4 text-xs text-muted">
            {{ $artikel->penulis?->name }} ·
            {{ $artikel->terbit_pada?->translatedFormat('d M Y') }}
        </p>
    @else
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-wide text-muted">
                {{ $artikel->kategori?->nama ?? $artikel->labelTipe() }}
                @if ($artikel->unggulan)
                    <span class="ml-1 border-2 border-ink bg-accent-400 px-1 text-[10px] text-primary-800">{{ __('umum.publikasi.unggulan') }}</span>
                @endif
            </p>

            <h3 class="mt-1 font-display text-lg leading-tight">
                <a href="{{ $tautan }}" class="hover:underline">{{ $judul }}</a>
            </h3>

            @if ($ringkasan)
                <p class="mt-2 line-clamp-2 text-sm text-muted">{{ $ringkasan }}</p>
            @endif

            <p class="mt-3 text-xs text-muted">
                {{ $artikel->penulis?->name }} ·
                {{ $artikel->terbit_pada?->translatedFormat('d M Y') }}
                @if ($artikel->waktu_baca_menit)
                    · {{ $artikel->waktu_baca_menit }} {{ __('umum.publikasi.menit') }}
                @endif
                · {{ $artikel->dilihat }}x {{ __('umum.publikasi.dibaca') }}
            </p>
        </div>
    @endif
</li>
