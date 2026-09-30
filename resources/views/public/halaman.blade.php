@extends('layouts.public')

@php
    /*
     * Setiap halaman statis punya gambar pratinjaunya sendiri.
     *
     * Pemetaannya ditulis EKSPLISIT karena kunci halaman memakai garis bawah
     * (`visi_misi`) sedangkan nama berkasnya memakai tanda hubung. Menurunkan
     * nama berkas dari kuncinya akan menghasilkan alamat yang tidak ada — dan
     * kegagalannya senyap: pratinjau tautannya hanya kosong tanpa galat.
     */
    $gambarPratinjau = match ($halaman->kunci) {
        'sejarah' => 'og/sejarah.png',
        'visi_misi' => 'og/visi-misi.png',
        'sambutan' => 'og/sambutan.png',
        default => 'og/bawaan.png',
    };
@endphp

@section('judul', $halaman->judul)
@section('og_gambar', asset($gambarPratinjau))
@section('deskripsi', $halaman->ringkasan ?? $halaman->judul)

@section('konten')
    @php
        $terjemahLengkap = $halaman->kelengkapanTerjemahan() === 100;
    @endphp

    <article class="mx-auto max-w-3xl px-4 py-10 lg:py-14">
        {{-- Remah navigasi --}}
        <nav class="text-xs font-bold uppercase tracking-wide text-muted" aria-label="Remah navigasi">
            <a href="{{ route('public.beranda') }}" class="underline">{{ __('umum.menu.beranda') }}</a>
            <span aria-hidden="true"> › </span>
            <span>{{ $halaman->judul }}</span>
        </nav>

        <header class="mt-5 border-b-2 border-ink pb-6">
            <h1 class="font-display text-3xl leading-tight sm:text-4xl">{{ $halaman->judul }}</h1>

            @if ($halaman->ringkasan)
                <p class="mt-3 text-lg text-muted">{{ $halaman->ringkasan }}</p>
            @endif

            <p class="mt-3 text-xs text-muted">
                {{ __('umum.umum.diperbarui') }}:
                {{ $halaman->updated_at?->translatedFormat('d F Y') }}
            </p>
        </header>

        @if (! $terjemahLengkap && app()->getLocale() !== 'id')
            {{-- Jujur kepada pembaca: versi Inggris belum lengkap --}}
            <p class="brutal-sm mt-6 border-accent-600 bg-accent-100 px-3 py-2 text-xs font-semibold">
                {{ __('umum.umum.belum_terjemah') }}
            </p>
        @endif

        <div class="mt-8 space-y-4 text-base leading-relaxed">
            {!! $halaman->konten !!}
        </div>

        <footer class="mt-10 border-t-2 border-ink pt-6">
            <a href="{{ route('public.beranda') }}" class="brutal-sm brutal-hover inline-block bg-paper px-4 py-2 text-sm font-bold">
                {{ __('otentikasi.tautan.beranda') }}
            </a>
        </footer>
    </article>
@endsection
