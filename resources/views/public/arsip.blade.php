@extends('layouts.public')

@section('judul', __('arsip.judul'))
@section('deskripsi', __('arsip.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('arsip.judul')"
        :deskripsi="__('arsip.intro')"
        :remah="[__('umum.menu.arsip')]"
    />

    <div class="mx-auto max-w-4xl px-4 py-10 lg:px-6">
        @if ($daftar->isEmpty())
            <div class="brutal bg-paper p-6">
                <p class="font-display text-lg">{{ __('arsip.kosong') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('arsip.kosong_teks') }}</p>
            </div>
        @else
            <p class="mb-3 text-xs text-muted">
                {{ __('arsip.internal.hakmu') }}:
                @foreach (\App\Support\Audiens::dimiliki(auth()->user()) as $a)
                    <span class="ml-1 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">{{ \App\Support\Audiens::PILIHAN[$a] ?? $a }}</span>
                @endforeach
            </p>

            <ul class="space-y-3">
                @foreach ($daftar as $d)
                    <li class="brutal bg-paper p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                            {{ $d->labelKategori() }}
                            @if ($d->nomor)
                                · {{ $d->nomor }}
                            @endif
                            @if ($d->tanggal_dokumen)
                                · {{ $d->tanggal_dokumen->translatedFormat('d F Y') }}
                            @endif
                        </p>

                        <h2 class="mt-1 font-display text-lg leading-tight">{{ $d->judulTeks() }}</h2>

                        @if ($d->keteranganTeks())
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ $d->keteranganTeks() }}</p>
                        @endif

                        <p class="mt-2 text-xs text-muted">
                            {{ __('arsip.kolom.berkas') }}:
                            @if ($d->punyaBerkas())
                                <span class="font-mono">{{ $d->namaBerkas() ?: $d->tautan_luar }}</span>
                                @if ($d->ukuranTeks())
                                    · {{ $d->ukuranTeks() }}
                                @endif
                            @else
                                {{ __('arsip.tanpa_berkas') }}
                            @endif
                        </p>

                        @if ($d->punyaBerkas())
                            <a
                                href="{{ route('arsip.unduh', $d) }}"
                                class="brutal-sm brutal-hover mt-3 inline-block bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                            >{{ __('arsip.unduh') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
