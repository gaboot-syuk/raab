@extends('layouts.public')

@section('judul', __('organisasi.struktur.judul'))
@section('og_gambar', asset('og/struktur.png'))
@section('deskripsi', __('organisasi.struktur.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('organisasi.struktur.judul')"
        :deskripsi="__('organisasi.struktur.intro')"
        :remah="[__('umum.menu.organisasi'), __('organisasi.struktur.judul')]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        @if ($daftarPeriode->isEmpty())
            <p class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                {{ __('organisasi.struktur.tanpa_periode') }}
            </p>
        @else
            {{-- Pemilih periode: tautan biasa agar dapat di-bookmark & dibagikan. --}}
            <nav class="brutal overflow-x-auto bg-paper p-3" aria-label="{{ __('organisasi.struktur.periode') }}">
                <ul class="flex gap-2">
                    @foreach ($daftarPeriode as $pilihan)
                        <li>
                            <a
                                href="{{ route('public.struktur', ['periode' => $pilihan->id]) }}"
                                @class([
                                    'brutal-sm inline-block whitespace-nowrap px-3 py-1.5 text-sm font-bold',
                                    'bg-accent-400 text-primary-800' => $periode && $periode->id === $pilihan->id,
                                    'brutal-hover bg-paper-alt' => ! $periode || $periode->id !== $pilihan->id,
                                ])
                            >
                                {{ $pilihan->nama }}
                                @if ($pilihan->aktif)
                                    <span class="ml-1 text-[10px] uppercase">●</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <p class="mt-4 text-sm text-muted">
                {{ __('organisasi.struktur.periode') }} <span class="font-bold text-ink">{{ $periode?->nama }}</span>
                · <span class="font-bold text-ink">{{ $jumlahPengurus }}</span> {{ __('organisasi.struktur.jumlah') }}
            </p>

            @if ($tingkat->isEmpty())
                <p class="brutal mt-6 bg-paper-alt p-8 text-center text-sm text-muted">
                    {{ __('organisasi.struktur.kosong') }}
                </p>
            @else
                {{--
                    Bagan disusun VERTIKAL: satu tingkat = satu baris kartu.
                    Di layar HP kartu menumpuk satu kolom; di layar lebar
                    sejajar. Tidak memakai garis penghubung SVG supaya tetap
                    terbaca dan tetap ringan di HP.
                --}}
                <div class="mt-6 space-y-8">
                    @foreach ($tingkat as $baris)
                        <section>
                            <div class="flex items-center gap-3">
                                <h2 class="font-display text-lg">
                                    {{ __('organisasi.struktur.tingkat.'.$baris['level']) }}
                                </h2>
                                <span class="brutal-sm bg-paper-alt px-2 py-0.5 text-xs font-bold">
                                    {{ count($baris['pengurus']) }}
                                </span>
                            </div>

                            <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($baris['pengurus'] as $orang)
                                    <li class="brutal bg-paper p-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-muted">
                                            {{ $orang['jabatan'] }}
                                        </p>

                                        @if ($orang['slug'])
                                            <a href="{{ route('public.prestasi.kader', $orang['slug']) }}" class="mt-1 block font-display text-base hover:underline">
                                                {{ $orang['nama'] }}
                                            </a>
                                        @else
                                            <p class="mt-1 font-display text-base">{{ $orang['nama'] }}</p>
                                        @endif

                                        @if ($orang['unit'])
                                            <p class="mt-1 text-xs text-muted">{{ $orang['unit'] }}</p>
                                        @endif

                                        @if ($orang['keterangan'])
                                            <p class="mt-2 text-xs italic text-muted">{{ $orang['keterangan'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endsection
