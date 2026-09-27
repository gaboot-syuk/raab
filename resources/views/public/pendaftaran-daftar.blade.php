@extends('layouts.public')

@section('judul', __('pendaftaran.judul'))
@section('deskripsi', __('pendaftaran.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('pendaftaran.judul')"
        :deskripsi="__('pendaftaran.intro')"
        :remah="[__('umum.menu.layanan'), __('pendaftaran.judul')]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('public.pendaftaran.status') }}" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                {{ __('pendaftaran.cek.judul') }}
            </a>
        </div>

        @php
            $bagian = [
                ['kunci' => 'dibuka', 'daftar' => $dibuka],
                ['kunci' => 'mendatang', 'daftar' => $mendatang],
                ['kunci' => 'arsip', 'daftar' => $arsip],
            ];
        @endphp

        @foreach ($bagian as $blok)
            @continue ($blok['daftar']->isEmpty())

            <section class="mt-8">
                <h2 class="font-display text-xl">{{ __('pendaftaran.bagian.'.$blok['kunci']) }}</h2>

                <ul class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($blok['daftar'] as $kegiatan)
                        @php
                            $keadaan = $kegiatan->keadaanPendaftaran();
                            $sisa = $keadaan['sisa'];
                        @endphp

                        <li class="brutal brutal-hover flex flex-col bg-paper">
                            <a href="{{ route('public.pendaftaran.detail', $kegiatan->getTranslation('slug', 'id')) }}" class="flex flex-1 flex-col p-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="brutal-sm bg-accent-400 px-2 py-0.5 text-[11px] font-bold uppercase text-primary-800">
                                        {{ $kegiatan->labelJenis() }}
                                    </span>

                                    @if ($keadaan['dibuka'])
                                        <span class="border-2 border-ink bg-success/30 px-1.5 py-0.5 text-[11px] font-bold uppercase">
                                            {{ __('pendaftaran.keadaan.dibuka') }}
                                        </span>
                                    @elseif ($sisa === 0)
                                        <span class="border-2 border-ink bg-paper-alt px-1.5 py-0.5 text-[11px] font-bold uppercase">
                                            {{ __('pendaftaran.keadaan.penuh') }}
                                        </span>
                                    @endif
                                </div>

                                <h3 class="mt-2 font-display text-lg leading-tight">
                                    {{ $kegiatan->getTranslation('judul', app()->getLocale(), false) ?: $kegiatan->getTranslation('judul', 'id') }}
                                </h3>

                                @if ($kegiatan->mulai)
                                    <p class="mt-2 text-xs font-bold text-muted">
                                        {{ $kegiatan->mulai->translatedFormat('d F Y') }}
                                        @if ($kegiatan->lokasi)
                                            · {{ $kegiatan->lokasi }}
                                        @endif
                                    </p>
                                @endif

                                <p class="mt-3 line-clamp-3 text-sm text-muted">
                                    {{ Str::limit(strip_tags((string) $kegiatan->getTranslation('deskripsi', app()->getLocale(), false)), 140) }}
                                </p>

                                <p class="mt-auto pt-3 text-xs font-bold text-muted">
                                    {{ $keadaan['terisi'] }} {{ __('pendaftaran.kuota.terisi') }}
                                    @if ($sisa !== null)
                                        · <span class="{{ $sisa === 0 ? 'text-accent-600' : '' }}">{{ $sisa }} {{ __('pendaftaran.kuota.sisa') }}</span>
                                    @else
                                        · {{ __('pendaftaran.kuota.tak_terbatas') }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if ($dibuka->isEmpty() && $mendatang->isEmpty() && $arsip->isEmpty())
            <p class="brutal mt-6 bg-paper-alt p-8 text-center text-sm text-muted">
                {{ __('pendaftaran.kosong.daftar') }}
            </p>
        @endif
    </div>
@endsection
