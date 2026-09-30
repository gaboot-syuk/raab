@extends('layouts.public')

@section('judul', __('umum.sosmed.judul'))
@section('og_gambar', asset('og/media-sosial.png'))
@section('deskripsi', __('umum.sosmed.deskripsi'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('umum.sosmed.judul')"
        :deskripsi="__('umum.sosmed.intro')"
        :remah="[__('umum.menu.layanan'), __('umum.menu.media_sosial')]"
    />

    <div class="mx-auto max-w-5xl px-4 py-10 lg:px-6">
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($sosmed as $tautan)
                @php $aktif = filled($tautan->url); @endphp
                <li class="brutal flex flex-col justify-between bg-paper p-5">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center border-2 border-ink bg-brand text-on-brand">
                                <x-public.ikon-sosmed :platform="$tautan->ikon ?? $tautan->platform" />
                            </span>
                            <p class="font-display text-base">{{ $tautan->label }}</p>
                        </div>

                        @if (! $aktif)
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-muted">
                                {{ __('umum.sosmed.belum_aktif') }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-4">
                        @if ($aktif)
                            <a
                                href="{{ $tautan->url }}" target="_blank" rel="noopener noreferrer"
                                class="brutal-sm brutal-hover inline-block bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                            >{{ __('umum.sosmed.kunjungi') }} →</a>
                        @else
                            <span class="brutal-sm inline-block bg-paper-alt px-4 py-2 text-sm font-semibold text-muted">
                                {{ __('umum.umum.segera') }}
                            </span>
                        @endif
                    </div>
                </li>
            @empty
                <li class="brutal bg-paper-alt p-5 text-sm text-muted sm:col-span-2 lg:col-span-3">
                    {{ __('umum.umum.placeholder') }}
                </li>
            @endforelse
        </ul>

        {{-- Sekretariat sebagai jalur kontak alternatif --}}
        <section class="brutal mt-8 bg-brand-dark p-5 text-on-brand">
            <h2 class="font-display text-lg">{{ __('umum.kontak.info_judul') }}</h2>
            <p class="mt-2 text-sm text-on-brand/85">
                {{ ($situs['alamat'] ?? '') !== '' ? $situs['alamat'] : __('umum.umum.alamat_placeholder') }}
            </p>
            <p class="mt-1 text-sm font-bold">{{ $situs['email'] ?? '' }}</p>

            @if (Route::has('public.kontak'))
                <a
                    href="{{ route('public.kontak') }}"
                    class="mt-4 inline-block border-2 border-on-brand/60 px-4 py-2 text-sm font-bold hover:border-accent-400 hover:text-accent-400"
                >{{ __('umum.menu.kontak') }}</a>
            @endif
        </section>
    </div>
@endsection
