@extends('layouts.public')

@section('judul', __('organisasi.lso.judul'))
@section('og_gambar', asset('og/lso.png'))
@section('deskripsi', __('organisasi.lso.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('organisasi.lso.judul')"
        :deskripsi="__('organisasi.lso.intro')"
        :remah="[__('umum.menu.organisasi'), __('organisasi.lso.judul')]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        @if ($daftar->isEmpty())
            <p class="brutal bg-paper-alt p-8 text-center text-sm text-muted">{{ __('organisasi.lso.belum_ada') }}</p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($daftar as $unit)
                    {{--
                        Unit tanpa slug tetap ditampilkan, tetapi TIDAK bertaut.

                        Ini pernah mematikan seluruh halaman ini di produksi:
                        route('public.lso.detail', null) tidak menghasilkan
                        tautan aneh, melainkan melempar UrlGenerationException —
                        dan satu baris data yang belum lengkap menjatuhkan
                        seluruh daftar. Data slug-nya ikut dibereskan oleh
                        migrasi backfill, tetapi halaman publik tidak boleh
                        bergantung pada itu saja.
                    --}}
                    @php
                        $tautanUnit = filled($unit->slug) ? route('public.lso.detail', $unit->slug) : null;
                    @endphp

                    <li class="brutal brutal-hover bg-paper">
                        @if ($tautanUnit)
                            <a href="{{ $tautanUnit }}" class="block p-5">
                        @else
                            <div class="block p-5">
                        @endif
                            @if ($unit->singkatan)
                                <span class="brutal-sm inline-block bg-accent-400 px-2 py-0.5 text-xs font-bold text-primary-800">
                                    {{ $unit->singkatan }}
                                </span>
                            @endif

                            <h2 class="mt-2 font-display text-lg">{{ $unit->nama }}</h2>

                            @if ($deskripsi = $unit->getTranslation('deskripsi', app()->getLocale(), false) ?: $unit->getTranslation('deskripsi', 'id', false))
                                <p class="mt-2 line-clamp-3 text-sm text-muted">{{ Str::limit(strip_tags($deskripsi), 140) }}</p>
                            @endif

                            <p class="mt-3 text-xs font-bold text-muted">
                                {{ $unit->anggota_count }} {{ __('organisasi.lso.anggota') }}
                                · {{ $unit->galeri_count }} {{ __('organisasi.lso.galeri') }}
                            </p>
                        @if ($tautanUnit)
                            </a>
                        @else
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
