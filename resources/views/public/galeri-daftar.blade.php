@extends('layouts.public')

@section('judul', __('organisasi.galeri.judul'))
@section('deskripsi', __('organisasi.galeri.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('organisasi.galeri.judul')"
        :deskripsi="__('organisasi.galeri.intro')"
        :remah="[__('umum.menu.publikasi'), __('organisasi.galeri.judul')]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <form method="GET" class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="unit" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.unit') }}</label>
                <select id="unit" name="unit" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('organisasi.galeri.semua_unit') }}</option>
                    @foreach ($daftarUnit as $satuan)
                        <option value="{{ $satuan->slug }}" @selected($saringUnit === $satuan->slug)>
                            {{ $satuan->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    {{ __('umum.direktori.terapkan') }}
                </button>
            </div>
        </form>

        @if ($album->isEmpty())
            <p class="brutal mt-6 bg-paper-alt p-8 text-center text-sm text-muted">
                {{ __('organisasi.galeri.kosong') }}
            </p>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($album as $satuAlbum)
                    @php $sampul = $satuAlbum->item->first(); @endphp

                    <li class="brutal brutal-hover overflow-hidden bg-paper">
                        <a href="{{ route('public.galeri.detail', $satuAlbum->getTranslation('slug', 'id')) }}" class="block">
                            @if ($sampul && $sumber = $sampul->sumber())
                                <img src="{{ $sumber }}" alt="{{ $satuAlbum->getTranslation('judul', 'id') }}"
                                     loading="lazy" class="aspect-[4/3] w-full object-cover">
                            @else
                                <div class="grid aspect-[4/3] w-full place-items-center bg-paper-alt text-sm text-muted">
                                    {{ __('organisasi.galeri.kosong') }}
                                </div>
                            @endif

                            <div class="p-4">
                                @if ($satuAlbum->unit)
                                    <p class="text-xs font-bold uppercase text-muted">{{ $satuAlbum->unit->nama }}</p>
                                @endif

                                <h2 class="mt-1 font-display text-base">
                                    {{ $satuAlbum->getTranslation('judul', app()->getLocale(), false) ?: $satuAlbum->getTranslation('judul', 'id') }}
                                </h2>

                                <p class="mt-2 text-xs text-muted">
                                    @if ($satuAlbum->tanggal) {{ $satuAlbum->tanggal->translatedFormat('d F Y') }} · @endif
                                    {{ $satuAlbum->item_count }} {{ __('organisasi.galeri.foto') }}
                                </p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $album->links() }}</div>
        @endif
    </div>
@endsection
