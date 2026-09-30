@extends('layouts.public')

@section('judul', __('pustaka.pustaka.judul'))
@section('og_gambar', asset('og/perpustakaan.png'))
@section('deskripsi', __('pustaka.pustaka.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('pustaka.pustaka.judul')"
        :deskripsi="__('pustaka.pustaka.intro')"
        :remah="[__('umum.menu.layanan'), __('pustaka.pustaka.judul')]"
    >
        <p class="mt-3 text-sm font-bold">
            {{ $jumlahTersedia }} {{ __('pustaka.pustaka.jumlah_tersedia') }}
        </p>
    </x-public.judul-halaman>

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <form method="GET" class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-4">
            <label class="block sm:col-span-2">
                <span class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.cari') }}</span>
                <input name="cari" type="search" value="{{ $saring['cari'] }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase text-muted">{{ __('pustaka.inventaris.kategori') }}</span>
                <select name="kategori" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('pustaka.pustaka.semua_kategori') }}</option>
                    @foreach ($daftarKategori as $kategori)
                        <option value="{{ $kategori }}" @selected($saring['kategori'] === $kategori)>{{ $kategori }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex flex-col justify-end gap-2">
                <label class="flex items-center gap-2 text-xs font-bold">
                    <input type="checkbox" name="tersedia" value="1" @checked($saring['tersedia'] === '1') class="h-4 w-4 border-2 border-ink">
                    {{ __('pustaka.pustaka.hanya_tersedia') }}
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-3 py-2 text-sm font-bold text-primary-800">
                        {{ __('umum.direktori.terapkan') }}
                    </button>
                    <a href="{{ route('public.perpustakaan') }}" class="brutal-sm bg-paper-alt px-3 py-2 text-sm font-bold">{{ __('umum.direktori.ulang') }}</a>
                </div>
            </div>
        </form>

        @if ($daftar->isEmpty())
            <p class="brutal mt-6 bg-paper-alt p-8 text-center text-sm text-muted">{{ __('pustaka.pustaka.kosong') }}</p>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($daftar as $buku)
                    @php $tersedia = $buku->eksemplarBebas(); @endphp

                    <li class="brutal flex flex-col bg-paper p-5">
                        <div class="flex items-start gap-3">
                            @if ($buku->cover_media_id && $cover = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($buku->cover_media_id))
                                <img src="{{ $cover->hasGeneratedConversion('kecil') ? $cover->getUrl('kecil') : $cover->getUrl() }}"
                                     alt="{{ $buku->judulTeks() }}" loading="lazy" class="brutal-sm h-24 w-16 shrink-0 object-cover">
                            @endif

                            <div class="min-w-0">
                                @if ($buku->kategori)
                                    <p class="text-xs font-bold uppercase text-muted">{{ $buku->kategori }}</p>
                                @endif

                                <h2 class="mt-1 font-display text-base leading-tight">
                                    <a href="{{ route('public.perpustakaan.detail', $buku->getTranslation('slug', 'id')) }}" class="hover:underline">
                                        {{ $buku->judulTeks() }}
                                    </a>
                                </h2>

                                @if ($buku->penulis)
                                    <p class="mt-1 text-xs text-muted">{{ $buku->penulis }}@if ($buku->tahun_terbit) · {{ $buku->tahun_terbit }}@endif</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-auto pt-3">
                            @if ($tersedia > 0)
                                <span class="border-2 border-ink bg-success/30 px-2 py-0.5 text-[11px] font-bold uppercase">
                                    {{ __('pustaka.pustaka.tersedia') }} · {{ $tersedia }} {{ __('pustaka.pustaka.eksemplar') }}
                                </span>
                            @else
                                <span class="border-2 border-ink bg-paper-alt px-2 py-0.5 text-[11px] font-bold uppercase">
                                    {{ __('pustaka.pustaka.tidak_tersedia') }}
                                </span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $daftar->links() }}</div>
        @endif
    </div>
@endsection
