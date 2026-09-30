@extends('layouts.public')

@section('judul', $unit->nama)
@section('og_gambar', asset('og/lso.png'))
@section('deskripsi', Str::limit(strip_tags((string) $unit->getTranslation('deskripsi', 'id', false)), 150))

@section('konten')
    <x-public.judul-halaman
        :judul="$unit->nama"
        :deskripsi="Str::limit(strip_tags((string) $unit->getTranslation('deskripsi', app()->getLocale(), false)), 220)"
        :remah="[__('umum.menu.organisasi'), __('organisasi.lso.judul'), $unit->singkatan ?: $unit->nama]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('public.lso') }}" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                ← {{ __('organisasi.lso.kembali') }}
            </a>
            @if ($periode)
                <span class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold">
                    {{ __('organisasi.struktur.periode') }} {{ $periode->nama }}
                </span>
            @endif
        </div>

        {{-- Pengurus --}}
        <section class="mt-8">
            <h2 class="font-display text-xl">{{ __('organisasi.lso.pengurus') }}</h2>

            @if ($pengurus->isEmpty())
                <p class="brutal mt-3 bg-paper-alt p-6 text-sm text-muted">{{ __('organisasi.lso.belum_ada') }}</p>
            @else
                <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pengurus as $orang)
                        <li class="brutal bg-paper p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-muted">{{ $orang->jabatan?->nama }}</p>

                            @if ($orang->member?->slug)
                                <a href="{{ route('public.prestasi.kader', $orang->member->slug) }}" class="mt-1 block font-display text-base hover:underline">
                                    {{ $orang->namaTampil() }}
                                </a>
                            @else
                                <p class="mt-1 font-display text-base">{{ $orang->namaTampil() }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Kader --}}
        @if ($anggota->isNotEmpty())
            <section class="mt-10">
                <h2 class="font-display text-xl">{{ __('organisasi.lso.anggota') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($anggota as $kader)
                        <li class="brutal-sm bg-paper-alt px-3 py-1.5 text-sm font-bold">
                            @if ($kader->slug)
                                <a href="{{ route('public.prestasi.kader', $kader->slug) }}" class="hover:underline">{{ $kader->nama_lengkap }}</a>
                            @else
                                {{ $kader->nama_lengkap }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Agenda --}}
        <section class="mt-10">
            <h2 class="font-display text-xl">{{ __('organisasi.lso.agenda_mendatang') }}</h2>

            @if ($agendaMendatang->isEmpty())
                <p class="brutal mt-3 bg-paper-alt p-6 text-sm text-muted">{{ __('organisasi.lso.belum_ada') }}</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($agendaMendatang as $agenda)
                        <li class="brutal overflow-hidden bg-paper p-4">
                            @if ($gambar = $gambarAgenda[$agenda->gambar_media_id] ?? null)
                                <img
                                    src="{{ $gambar }}"
                                    alt=""
                                    class="brutal-sm mb-3 h-40 w-full object-cover"
                                    loading="lazy" decoding="async"
                                >
                            @endif
                            <p class="text-xs font-bold uppercase text-accent-600">
                                {{ $agenda->mulai->translatedFormat('d F Y, H:i') }}
                            </p>
                            <p class="mt-1 font-display text-base">{{ $agenda->getTranslation('judul', app()->getLocale(), false) ?: $agenda->getTranslation('judul', 'id', false) }}</p>
                            @if ($agenda->lokasi)
                                <p class="mt-1 text-sm text-muted">{{ $agenda->lokasi }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Galeri --}}
        @if ($album->isNotEmpty())
            <section class="mt-10">
                <h2 class="font-display text-xl">{{ __('organisasi.lso.galeri') }}</h2>
                <div class="mt-3 space-y-6">
                    @foreach ($album as $satuAlbum)
                        <div class="brutal bg-paper p-4">
                            <h3 class="font-display text-base">{{ $satuAlbum->getTranslation('judul', app()->getLocale(), false) ?: $satuAlbum->getTranslation('judul', 'id', false) }}</h3>

                            <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach ($satuAlbum->item->take(8) as $foto)
                                    @if ($sumber = $foto->sumber())
                                        <li>
                                            <img src="{{ $sumber }}" alt="{{ $foto->keteranganIsi() }}" loading="lazy"
                                                 class="brutal-sm aspect-square w-full object-cover">
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
