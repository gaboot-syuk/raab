@extends('layouts.public')

@php $judulAlbum = $album->getTranslation('judul', app()->getLocale(), false) ?: $album->getTranslation('judul', 'id'); @endphp

@section('judul', $judulAlbum)
@section('og_gambar', asset('og/galeri.png'))
@section('deskripsi', Str::limit(strip_tags((string) $album->getTranslation('deskripsi', 'id', false)), 150))

@section('konten')
    <x-public.judul-halaman
        :judul="$judulAlbum"
        :deskripsi="Str::limit(strip_tags((string) $album->getTranslation('deskripsi', app()->getLocale(), false)), 220)"
        :remah="[__('umum.menu.publikasi'), __('organisasi.galeri.judul'), $judulAlbum]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('public.galeri') }}" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                ← {{ __('organisasi.galeri.judul') }}
            </a>

            @if ($album->unit)
                <a href="{{ route('public.lso.detail', $album->unit->slug) }}" class="brutal-sm brutal-hover bg-paper-alt px-4 py-2 text-sm font-bold">
                    {{ $album->unit->nama }}
                </a>
            @endif

            @if ($album->tanggal)
                <span class="text-sm text-muted">{{ $album->tanggal->translatedFormat('d F Y') }}</span>
            @endif

            @if ($album->lokasi)
                <span class="text-sm text-muted">· {{ $album->lokasi }}</span>
            @endif
        </div>

        @if ($album->item->isEmpty())
            <p class="brutal mt-6 bg-paper-alt p-8 text-center text-sm text-muted">{{ __('organisasi.galeri.kosong') }}</p>
        @else
            <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($album->item as $foto)
                    @if ($sumber = $foto->sumber())
                        <li class="brutal bg-paper p-2">
                            <img src="{{ $sumber }}" alt="{{ $foto->keteranganIsi() }}" loading="lazy"
                                 class="brutal-sm aspect-[4/3] w-full object-cover">
                            @if ($foto->keteranganIsi() !== '')
                                <p class="mt-2 px-1 pb-1 text-xs text-muted">{{ $foto->keteranganIsi() }}</p>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        @if ($albumLain->isNotEmpty())
            <section class="mt-12">
                <h2 class="font-display text-xl">{{ __('organisasi.galeri.album_lain') }}</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($albumLain as $lain)
                        <li class="brutal-sm brutal-hover bg-paper-alt p-3">
                            <a href="{{ route('public.galeri.detail', $lain->getTranslation('slug', 'id')) }}" class="text-sm font-bold hover:underline">
                                {{ $lain->getTranslation('judul', 'id') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
