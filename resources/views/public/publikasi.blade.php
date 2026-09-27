@extends('layouts.public')

@php
    $judulHalaman = $tipe
        ? \App\Models\Article::TIPE[$tipe]
        : __('umum.menu.publikasi');
@endphp

@section('judul', $judulHalaman)
@section('deskripsi', __('umum.publikasi.deskripsi'))

@section('konten')
    <x-public.judul-halaman
        :judul="$judulHalaman"
        :deskripsi="$tipe ? null : __('umum.publikasi.intro')"
        :remah="$tipe ? [__('umum.menu.publikasi'), \App\Models\Article::TIPE[$tipe]] : [__('umum.menu.publikasi')]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        {{-- Pengalih tipe --}}
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('umum.publikasi.tipe') }}">
            <a
                href="{{ route('public.publikasi') }}"
                class="border-2 border-ink px-3 py-1.5 text-sm font-bold"
                :class=""
                @class(['bg-accent-400 text-primary-800' => $tipe === null, 'bg-paper' => $tipe !== null])
            >{{ __('umum.publikasi.semua') }}</a>

            @foreach (\App\Models\Article::TIPE_PUBLIK as $nilai)
                <a
                    href="{{ route('public.publikasi.tipe', $nilai) }}"
                    class="border-2 border-ink px-3 py-1.5 text-sm font-bold"
                    @class(['bg-accent-400 text-primary-800' => $tipe === $nilai, 'bg-paper' => $tipe !== $nilai])
                >{{ \App\Models\Article::TIPE[$nilai] }}</a>
            @endforeach
        </nav>

        {{-- Artikel unggulan (hanya di halaman utama publikasi) --}}
        @if ($unggulan->isNotEmpty())
            <section class="mt-8">
                <h2 class="font-display text-xl">{{ __('umum.publikasi.unggulan') }}</h2>
                <ul class="mt-4 grid gap-4 lg:grid-cols-3">
                    @foreach ($unggulan as $sorotan)
                        @include('public.partials.kartu-artikel', ['artikel' => $sorotan, 'sorotan' => true])
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_16rem]">
            {{-- Daftar artikel --}}
            <div>
                <h2 class="font-display text-xl">
                    {{ $tipe ? \App\Models\Article::TIPE[$tipe] : __('umum.publikasi.semua_artikel') }}
                </h2>

                @if ($daftar->isEmpty())
                    <p class="brutal mt-4 bg-paper-alt p-8 text-center text-sm text-muted">
                        {{ __('umum.publikasi.kosong') }}
                    </p>
                @else
                    <ul class="mt-4 space-y-4">
                        @foreach ($daftar as $artikel)
                            @include('public.partials.kartu-artikel', ['artikel' => $artikel, 'sorotan' => false])
                        @endforeach
                    </ul>

                    <div class="mt-8">{{ $daftar->links() }}</div>
                @endif
            </div>

            {{-- Samping: kategori & tag --}}
            <aside class="space-y-6">
                <section class="brutal bg-paper p-4">
                    <h2 class="font-display text-base">{{ __('umum.publikasi.kategori') }}</h2>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($kategori as $satu)
                            <li>
                                <a
                                    href="{{ route('public.publikasi', ['kategori' => $satu->id]) }}"
                                    class="flex items-center justify-between gap-2 hover:underline"
                                >
                                    <span>{{ $satu->nama }}</span>
                                    <span class="text-xs text-muted">
                                        {{-- Angka yang sudah dihitung controller. Memanggil
                                             $satu->jumlahTerbit() di sini akan menembak satu query
                                             per kategori — persis N+1 yang dihindari. --}}
                                        {{ $satu->jumlah_terbit ?? $satu->jumlahTerbit() }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($tag->isNotEmpty())
                    <section class="brutal bg-paper p-4">
                        <h2 class="font-display text-base">{{ __('umum.publikasi.tag') }}</h2>
                        <ul class="mt-3 flex flex-wrap gap-1">
                            @foreach ($tag as $satu)
                                <li>
                                    <a
                                        href="{{ route('public.publikasi', ['tag' => $satu->slug]) }}"
                                        class="inline-block border-2 border-ink bg-paper-alt px-2 py-0.5 text-xs font-bold hover:bg-accent-100"
                                    >#{{ $satu->nama }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
