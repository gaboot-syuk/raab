@extends('layouts.auth')

@section('judul', __('otentikasi.verifikasi.judul'))

@section('konten')
    <div class="brutal-lg bg-paper p-6 sm:p-8">
        <span class="inline-block border-2 border-ink bg-accent-400 px-2 py-0.5 text-[11px] font-bold uppercase text-primary-800">
            {{ __('otentikasi.verifikasi.label_kecil') }}
        </span>

        <h1 class="mt-4 font-display text-2xl">{{ __('otentikasi.verifikasi.judul') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('otentikasi.verifikasi.teks') }}</p>

        <p class="brutal-sm mt-4 bg-paper-alt px-3 py-2 text-xs font-semibold">
            {{ auth()->user()->email }}
        </p>

        <div class="mt-6 space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="brutal brutal-hover w-full bg-accent-400 px-6 py-3 font-bold text-primary-800">
                    {{ __('otentikasi.verifikasi.tombol') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full border-2 border-ink bg-paper px-6 py-3 text-sm font-bold">
                    {{ __('otentikasi.verifikasi.keluar') }}
                </button>
            </form>
        </div>
    </div>
@endsection
