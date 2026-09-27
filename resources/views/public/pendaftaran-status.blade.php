@extends('layouts.public')

@section('judul', __('pendaftaran.cek.judul'))
@section('deskripsi', __('pendaftaran.cek.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('pendaftaran.cek.judul')"
        :deskripsi="__('pendaftaran.cek.intro')"
        :remah="[__('umum.menu.layanan'), __('pendaftaran.judul'), __('pendaftaran.cek.judul')]"
    />

    <div class="mx-auto max-w-3xl px-4 py-10 lg:px-6">
        <form method="GET" action="{{ route('public.pendaftaran.status') }}" class="brutal grid gap-4 bg-paper p-5 sm:grid-cols-3">
            <label class="block sm:col-span-1">
                <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.cek.kode') }} *</span>
                <input name="kode" type="text" value="{{ $kode }}" required
                       placeholder="MAPA-2026-0001" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
            </label>

            <label class="block sm:col-span-1">
                <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.cek.email') }} *</span>
                <input name="email" type="email" value="{{ $email }}" required
                       class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
            </label>

            <div class="flex items-end">
                <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    {{ __('pendaftaran.cek.periksa') }}
                </button>
            </div>
        </form>

        @if ($dicari && ! $pendaftaran)
            <p class="brutal mt-6 bg-paper-alt p-6 text-sm">
                {{ __('pendaftaran.cek.tidak_ditemukan') }}
            </p>
        @endif
        @if ($pendaftaran)
            <section class="brutal mt-6 bg-paper p-6">
                <h2 class="font-display text-lg">
                    {{ $pendaftaran->event?->getTranslation('judul', app()->getLocale(), false) ?: $pendaftaran->event?->getTranslation('judul', 'id') }}
                </h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2 border-b-2 border-ink/10 pb-2">
                        <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.cek.kode') }}</dt>
                        <dd class="font-display tracking-wider">{{ $pendaftaran->kode_pendaftaran }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 border-b-2 border-ink/10 pb-2">
                        <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.cek.nama') }}</dt>
                        <dd>{{ $pendaftaran->nama_lengkap }}</dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 border-b-2 border-ink/10 pb-2">
                        <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.cek.status') }}</dt>
                        <dd>
                            <span @class([
                                'border-2 border-ink px-2 py-0.5 text-xs font-bold uppercase',
                                'bg-success/30' => $pendaftaran->sudahLolos(),
                                'bg-accent-100' => ! $pendaftaran->sudahLolos() && $pendaftaran->status === \App\Models\EventRegistration::STATUS_MENUNGGU,
                                'bg-paper-alt' => ! $pendaftaran->sudahLolos() && $pendaftaran->status !== \App\Models\EventRegistration::STATUS_MENUNGGU,
                            ])>{{ $pendaftaran->labelStatus() }}</span>
                        </dd>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 border-b-2 border-ink/10 pb-2">
                        <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.cek.waktu') }}</dt>
                        <dd>{{ $pendaftaran->created_at?->translatedFormat('d F Y, H:i') }}</dd>
                    </div>

                    @if (filled($pendaftaran->catatan_panitia))
                        <div>
                            <dt class="font-bold uppercase text-muted">{{ __('pendaftaran.cek.catatan') }}</dt>
                            <dd class="mt-1">{{ $pendaftaran->catatan_panitia }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Kartu hanya berguna setelah statusnya terverifikasi.
                     Tautan membawa kode + email yang sama, jadi tidak dapat
                     dibuka orang lain yang hanya menebak kodenya. --}}
                @if ($pendaftaran->sudahLolos())
                    <a href="{{ route('public.pendaftaran.kartu', ['kode' => $pendaftaran->kode_pendaftaran, 'email' => $pendaftaran->email]) }}"
                       class="brutal-sm brutal-hover mt-5 inline-block bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        {{ __('pendaftaran.kartu.judul') }}
                    </a>
                @endif
            </section>
        @endif
    </div>
@endsection
