@extends('layouts.public')

@section('judul', __('pendaftaran.sukses.judul'))
@section('deskripsi', __('pendaftaran.sukses.simpan'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('pendaftaran.sukses.judul')"
        :deskripsi="__('pendaftaran.sukses.langkah')"
        :remah="[__('umum.menu.layanan'), __('pendaftaran.judul'), __('pendaftaran.sukses.judul')]"
    />

    <div class="mx-auto max-w-3xl px-4 py-12 lg:px-6">
        <div class="brutal bg-paper p-6 sm:p-8">
            <h2 class="font-display text-lg">
                {{ $pendaftaran->event?->getTranslation('judul', app()->getLocale(), false) ?: $pendaftaran->event?->getTranslation('judul', 'id') }}
            </h2>

            <p class="mt-1 text-sm text-muted">{{ __('pendaftaran.cek.nama') }}: <span class="font-bold text-ink">{{ $pendaftaran->nama_lengkap }}</span></p>

            {{-- Kode pendaftaran ditampilkan besar: inilah satu-satunya hal yang
                 perlu dicatat pendaftar, dan sering dibacakan lewat telepon. --}}
            <div class="mt-6 border-4 border-ink bg-accent-100 p-5 text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-muted">{{ __('pendaftaran.sukses.kode') }}</p>
                <p class="mt-1 font-display text-2xl tracking-wider sm:text-3xl">{{ $pendaftaran->kode_pendaftaran }}</p>
            </div>

            <p class="mt-4 text-sm text-muted">{{ __('pendaftaran.sukses.simpan') }}</p>

            <dl class="mt-6 space-y-2 border-t-2 border-ink/15 pt-4 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.cek.status') }}</dt>
                    <dd>{{ $pendaftaran->labelStatus() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.cek.waktu') }}</dt>
                    <dd>{{ $pendaftaran->created_at?->translatedFormat('d F Y, H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.form.email') }}</dt>
                    <dd>{{ $pendaftaran->email }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <a
                href="{{ route('public.pendaftaran.status', ['kode' => $pendaftaran->kode_pendaftaran, 'email' => $pendaftaran->email]) }}"
                class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800"
            >{{ __('pendaftaran.sukses.cek_status') }}</a>

            <a href="{{ route('public.pendaftaran') }}" class="brutal bg-paper px-6 py-3 font-bold">
                {{ __('pendaftaran.judul') }}
            </a>
        </div>
    </div>
@endsection
