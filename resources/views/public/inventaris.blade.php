@extends('layouts.public')

@section('judul', __('pustaka.inventaris.judul'))
@section('og_gambar', asset('og/inventaris.png'))
@section('deskripsi', __('pustaka.inventaris.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('pustaka.inventaris.judul')"
        :deskripsi="__('pustaka.inventaris.intro')"
        :remah="[__('umum.menu.layanan'), __('pustaka.inventaris.judul')]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <form method="GET" class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-4">
            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase text-muted">{{ __('pustaka.inventaris.cari') }}</span>
                <input name="cari" type="search" value="{{ $saring['cari'] }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase text-muted">{{ __('pustaka.inventaris.kategori') }}</span>
                <select name="kategori" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('pustaka.inventaris.semua') }}</option>
                    @foreach ($daftarKategori as $kategori)
                        <option value="{{ $kategori->slug }}" @selected($saring['kategori'] === $kategori->slug)>{{ $kategori->namaTeks() }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end gap-2">
                <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    {{ __('umum.direktori.terapkan') }}
                </button>
                @if ($saring['cari'] !== '' || $saring['kategori'] !== '')
                    <a href="{{ route('public.inventaris') }}" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold">{{ __('umum.direktori.ulang') }}</a>
                @endif
            </div>
        </form>

        @if ($daftar->isEmpty())
            <p class="brutal mt-6 bg-paper-alt p-8 text-center text-sm text-muted">
                {{ __('pustaka.inventaris.kosong') }}
            </p>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($daftar as $aset)
                    <li class="brutal flex flex-col bg-paper p-5">
                        @if ($aset->foto_media_id && $foto = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($aset->foto_media_id))
                            <img src="{{ $foto->hasGeneratedConversion('kecil') ? $foto->getUrl('kecil') : $foto->getUrl() }}"
                                 alt="{{ $aset->namaTeks() }}" loading="lazy" class="brutal-sm mb-3 aspect-[4/3] w-full object-cover">
                        @endif

                        @if ($aset->kategori)
                            <p class="text-xs font-bold uppercase text-muted">{{ $aset->kategori->namaTeks() }}</p>
                        @endif

                        <h2 class="mt-1 font-display text-lg leading-tight">{{ $aset->namaTeks() }}</h2>

                        <p class="mt-1 font-mono text-xs text-muted">{{ $aset->kode }}</p>

                        @if ($aset->getTranslation('keterangan', app()->getLocale(), false))
                            <p class="mt-2 text-sm text-muted">{{ $aset->getTranslation('keterangan', app()->getLocale(), false) }}</p>
                        @endif

                        <dl class="mt-auto space-y-1 pt-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted">{{ __('pustaka.inventaris.tersedia') }}</dt>
                                <dd class="font-bold">{{ $aset->jumlahTersedia() }} {{ $aset->satuan }}</dd>
                            </div>

                            @if ($aset->jumlahDipinjam() > 0)
                                <div class="flex justify-between gap-3 text-muted">
                                    <dt>{{ __('pustaka.inventaris.dipinjam') }}</dt>
                                    <dd>{{ $aset->jumlahDipinjam() }} {{ $aset->satuan }}</dd>
                                </div>
                            @endif

                            @if ($aset->lokasi)
                                <div class="flex justify-between gap-3 text-muted">
                                    <dt>{{ __('pustaka.inventaris.lokasi') }}</dt>
                                    <dd>{{ $aset->lokasi }}</dd>
                                </div>
                            @endif

                            <div class="flex justify-between gap-3 text-muted">
                                <dt>{{ __('pustaka.inventaris.kondisi') }}</dt>
                                <dd>{{ $aset->labelKondisi() }}</dd>
                            </div>
                        </dl>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-8 border-t-2 border-ink pt-4 text-xs text-muted">
            {{ __('pustaka.inventaris.catatan') }}
        </p>
    </div>
@endsection
