@extends('layouts.public')

@section('judul', __('umum.menu.prestasi'))
@section('deskripsi', __('kartu.prestasi.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('umum.menu.prestasi')"
        :deskripsi="__('kartu.prestasi.intro')"
        :remah="[__('umum.menu.prestasi')]"
    />

    <div class="mx-auto max-w-5xl px-4 py-10 lg:px-6">
        {{--
            Angka di kepala dihitung dari data yang sama dengan daftar di
            bawahnya, supaya kepala dan isi tidak pernah bertentangan.
        --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="brutal bg-paper p-5">
                <p class="text-xs font-bold uppercase text-muted">{{ __('kartu.prestasi.jumlah') }}</p>
                <p class="mt-1 font-display text-3xl">{{ $ringkasan['prestasi'] }}</p>
            </div>
            <div class="brutal bg-paper p-5">
                <p class="text-xs font-bold uppercase text-muted">{{ __('kartu.prestasi.kader') }}</p>
                <p class="mt-1 font-display text-3xl">{{ $ringkasan['kader'] }}</p>
            </div>
            <div class="brutal bg-paper p-5">
                <p class="text-xs font-bold uppercase text-muted">{{ __('kartu.prestasi.unggulan') }}</p>
                <p class="mt-1 font-display text-3xl">{{ collect($prestasi)->where('unggulan', true)->count() }}</p>
            </div>
        </div>

        <!-- Sebaran tingkat: dari SELURUH prestasi tayang, bukan hasil saringan. -->
        <ul class="mt-4 flex flex-wrap gap-2 text-xs">
            @foreach ($papanTingkat as $label => $jumlah)
                <li class="brutal-sm bg-paper-alt px-3 py-1.5">
                    {{ $label }}: <span class="font-bold">{{ $jumlah }}</span>
                </li>
            @endforeach
        </ul>

        <!-- ===== Saringan ===== -->
        <form method="GET" action="{{ url('/prestasi') }}" class="mt-6 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-xs font-bold uppercase text-muted">{{ __('kartu.prestasi.tingkat') }}</span>
                <select name="tingkat" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('kartu.prestasi.semua_tingkat') }}</option>
                    @foreach ($pilihanTingkat as $kunci => $label)
                        <option value="{{ $kunci }}" @selected($saringan['tingkat'] === $kunci)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-bold uppercase text-muted">{{ __('kartu.prestasi.kategori') }}</span>
                <select name="kategori" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('kartu.prestasi.semua_kategori') }}</option>
                    @foreach ($kategori as $k)
                        <option value="{{ $k['id'] }}" @selected($saringan['kategori'] === $k['id'])>{{ $k['nama'] }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                {{ __('kartu.prestasi.saring') }}
            </button>
        </form>

        @if (empty($prestasi))
            <div class="brutal mt-6 bg-paper p-6">
                <p class="font-display text-lg">{{ __('kartu.prestasi.kosong') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('kartu.prestasi.kosong_teks') }}</p>
            </div>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($prestasi as $p)
                    <li class="brutal bg-paper p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                                    {{ $p['label_tingkat'] }} · {{ $p['label_peringkat'] }}
                                    @if ($p['kategori'])
                                        · {{ $p['kategori'] }}
                                    @endif
                                </p>

                                <h2 class="mt-1 font-display text-lg leading-tight">{{ $p['judul'] }}</h2>

                                <p class="mt-1 text-sm">
                                    @if ($p['tautan_kader'])
                                        <a href="{{ $p['tautan_kader'] }}" class="font-bold underline">{{ $p['anggota'] }}</a>
                                    @else
                                        {{-- Profil kader hanya ditautkan bila pemiliknya membukanya. --}}
                                        <span class="font-bold">{{ $p['anggota'] }}</span>
                                    @endif

                                    @if ($p['unit'])
                                        <span class="text-muted"> · {{ $p['unit'] }}</span>
                                    @endif
                                </p>

                                <p class="mt-1 text-xs text-muted">
                                    {{ $p['penyelenggara'] ?? '' }}
                                    @if ($p['penyelenggara'])
                                        ·
                                    @endif
                                    {{ $p['tanggal'] }}
                                </p>

                                @if ($p['deskripsi'])
                                    <p class="mt-2 text-sm leading-relaxed">{{ $p['deskripsi'] }}</p>
                                @endif
                            </div>

                            @if ($p['unggulan'])
                                <span class="border-2 border-ink bg-accent-400 px-2 py-1 text-[10px] font-bold uppercase">Unggulan</span>
                            @endif
                        </div>

                        @if ($p['sertifikat_url'])
                            <a href="{{ $p['sertifikat_url'] }}" target="_blank" rel="noopener" class="brutal-sm brutal-hover mt-3 inline-block bg-paper-alt px-3 py-1.5 text-xs font-bold">
                                {{ __('kartu.prestasi.lihat_sertifikat') }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-6 text-xs leading-relaxed text-muted">{{ __('kartu.prestasi.catatan') }}</p>
    </div>
@endsection
