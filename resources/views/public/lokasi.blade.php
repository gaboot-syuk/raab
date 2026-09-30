@extends('layouts.public')

@section('judul', __('umum.lokasi.judul'))
@section('og_gambar', asset('og/lokasi.png'))
@section('deskripsi', __('umum.lokasi.deskripsi'))

@section('konten')
    @php
        $alamat = (string) ($situs['alamat'] ?? '');
        $alamatTerisi = $alamat !== '' && ! str_contains($alamat, 'belum diisi');
        $peta = trim((string) ($situs['peta_embed'] ?? ''));
        $tautanPeta = 'https://www.google.com/maps/search/?api=1&query='.urlencode($alamatTerisi ? $alamat : ($situs['nama_kampus'] ?? ''));
    @endphp

    <x-public.judul-halaman
        :judul="__('umum.lokasi.judul')"
        :deskripsi="__('umum.lokasi.intro')"
        :remah="[__('umum.menu.layanan'), __('umum.menu.lokasi')]"
    />

    <div class="mx-auto max-w-5xl px-4 py-10 lg:px-6">
        <div class="grid gap-6 md:grid-cols-3">
            <div class="brutal bg-paper p-5">
                <h2 class="font-display text-base">{{ __('umum.lokasi.alamat') }}</h2>
                <p class="mt-2 text-sm leading-relaxed">
                    @if ($alamatTerisi)
                        {{ $alamat }}
                    @else
                        {{ __('umum.umum.alamat_placeholder') }}
                    @endif
                </p>
            </div>

            <div class="brutal bg-paper p-5">
                <h2 class="font-display text-base">{{ __('umum.lokasi.jam') }}</h2>
                <p class="mt-2 text-sm">
                    {{ ($situs['jam_operasional'] ?? '') !== '' ? $situs['jam_operasional'] : __('umum.umum.jam_placeholder') }}
                </p>
            </div>

            <div class="brutal bg-paper p-5">
                <h2 class="font-display text-base">{{ __('umum.lokasi.kampus') }}</h2>
                <p class="mt-2 text-sm">{{ $situs['nama_kampus'] ?? __('umum.nama_kampus') }}</p>
                <p class="mt-1 text-xs text-muted">
                    {{ $situs['nama_komisariat'] ?? __('umum.nama_komisariat') }}
                    — {{ $situs['nama_cabang'] ?? __('umum.nama_cabang') }}
                </p>
            </div>
        </div>

        <section class="mt-8">
            <h2 class="font-display text-xl">{{ __('umum.lokasi.peta') }}</h2>

            @if ($peta !== '')
                {{-- Kode sematan berasal dari pengurus sendiri melalui Pengaturan Situs. --}}
                <div class="brutal mt-3 overflow-hidden bg-paper-alt [&_iframe]:h-[420px] [&_iframe]:w-full [&_iframe]:border-0">
                    {!! $peta !!}
                </div>
            @else
                <div class="brutal mt-3 bg-paper-alt p-6 text-center">
                    <p class="text-sm text-muted">{{ __('umum.lokasi.peta_kosong') }}</p>
                    <x-public.tombol-jamur :href="$tautanPeta" target="_blank" rel="noopener noreferrer" class="mt-4">
                        {{ __('umum.lokasi.buka_maps') }}
                    </x-public.tombol-jamur>
                </div>
            @endif

            @if ($peta !== '')
                <div class="mt-4">
                    <x-public.tombol-jamur :href="$tautanPeta" target="_blank" rel="noopener noreferrer">
                        {{ __('umum.lokasi.petunjuk') }}
                    </x-public.tombol-jamur>
                </div>
            @endif
        </section>
    </div>
@endsection
