@extends('layouts.public')

@section('judul', __('organisasi.lso.judul'))
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
                    <li class="brutal brutal-hover bg-paper">
                        <a href="{{ route('public.lso.detail', $unit->slug) }}" class="block p-5">
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
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
