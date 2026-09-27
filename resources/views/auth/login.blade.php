@extends('layouts.auth')

@section('judul', __('otentikasi.masuk.judul'))

@section('konten')
    <div class="brutal-lg bg-paper p-6 sm:p-8">
        <span class="inline-block border-2 border-ink bg-accent-400 px-2 py-0.5 text-[11px] font-bold uppercase text-primary-800">
            {{ __('otentikasi.masuk.label_kecil') }}
        </span>

        <h1 class="mt-4 font-display text-2xl">{{ __('otentikasi.masuk.judul') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('otentikasi.masuk.teks') }}</p>

        @if ($errors->any())
            <div class="brutal-sm mt-5 border-danger bg-danger/10 px-4 py-3">
                <ul class="list-inside list-disc text-sm font-semibold">
                    @foreach ($errors->all() as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-xs font-bold uppercase tracking-wide">
                    {{ __('otentikasi.label.email') }}
                </label>
                <input
                    id="email" name="email" type="email" required autofocus autocomplete="username"
                    value="{{ old('email') }}"
                    @error('email') aria-invalid="true" @enderror
                    class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                >
            </div>

            <div x-data="{ tampil: false }">
                <label for="password" class="block text-xs font-bold uppercase tracking-wide">
                    {{ __('otentikasi.label.kata_sandi') }}
                </label>
                <div class="mt-1 flex">
                    <input
                        id="password" name="password" :type="tampil ? 'text' : 'password'"
                        required autocomplete="current-password"
                        class="w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                    >
                    <button
                        type="button" @click="tampil = ! tampil"
                        class="border-2 border-l-0 border-ink bg-paper-alt px-3 text-xs font-bold uppercase"
                        :aria-label="tampil ? '{{ __('otentikasi.label.sembunyikan') }}' : '{{ __('otentikasi.label.tampilkan') }}'"
                    >
                        <span x-text="tampil ? '{{ __('otentikasi.label.sembunyikan') }}' : '{{ __('otentikasi.label.tampilkan') }}'"></span>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="remember" class="h-4 w-4 border-2 border-ink">
                    {{ __('otentikasi.label.ingat_saya') }}
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-bold underline">
                        {{ __('otentikasi.masuk.lupa_sandi') }}
                    </a>
                @endif
            </div>

            <button type="submit" class="brutal brutal-hover w-full bg-primary-600 px-6 py-3 font-bold text-paper">
                {{ __('otentikasi.masuk.tombol') }}
            </button>
        </form>

        @if (Route::has('register'))
            <p class="mt-6 border-t-2 border-ink pt-4 text-center text-sm">
                {{ __('otentikasi.masuk.belum_punya_akun') }}
                <a href="{{ route('register') }}" class="font-bold underline">{{ __('otentikasi.tautan.daftar') }}</a>
            </p>
        @endif
    </div>
@endsection
