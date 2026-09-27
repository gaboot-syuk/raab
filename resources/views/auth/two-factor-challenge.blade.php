@extends('layouts.auth')

@section('judul', __('otentikasi.dua_faktor.judul'))

@section('konten')
    <div class="brutal-lg bg-paper p-6 sm:p-8" x-data="{ pemulihan: false }">
        <span class="inline-block border-2 border-ink bg-accent-400 px-2 py-0.5 text-[11px] font-bold uppercase text-primary-800">
            {{ __('otentikasi.dua_faktor.label_kecil') }}
        </span>

        <h1 class="mt-4 font-display text-2xl">{{ __('otentikasi.dua_faktor.judul') }}</h1>

        <p class="mt-2 text-sm text-muted" x-show="! pemulihan">{{ __('otentikasi.dua_faktor.teks') }}</p>
        <p class="mt-2 text-sm text-muted" x-show="pemulihan" x-cloak>{{ __('otentikasi.dua_faktor.teks_pemulihan') }}</p>

        @if ($errors->any())
            <div class="brutal-sm mt-5 border-danger bg-danger/10 px-4 py-3">
                <ul class="list-inside list-disc text-sm font-semibold">
                    @foreach ($errors->all() as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 space-y-5">
            @csrf

            <div x-show="! pemulihan">
                <label for="code" class="block text-xs font-bold uppercase tracking-wide">
                    {{ __('otentikasi.label.kode') }}
                </label>
                <input
                    id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm tracking-[0.3em]"
                >
            </div>

            <div x-show="pemulihan" x-cloak>
                <label for="recovery_code" class="block text-xs font-bold uppercase tracking-wide">
                    {{ __('otentikasi.label.kode_pemulihan') }}
                </label>
                <input
                    id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                    class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                >
            </div>

            <button type="submit" class="brutal brutal-hover w-full bg-primary-600 px-6 py-3 font-bold text-paper">
                {{ __('otentikasi.dua_faktor.tombol') }}
            </button>

            <button
                type="button" @click="pemulihan = ! pemulihan"
                class="w-full border-2 border-ink bg-paper-alt px-6 py-2 text-sm font-bold"
            >
                <span x-show="! pemulihan">{{ __('otentikasi.dua_faktor.pakai_pemulihan') }}</span>
                <span x-show="pemulihan" x-cloak>{{ __('otentikasi.dua_faktor.pakai_kode') }}</span>
            </button>
        </form>
    </div>
@endsection
