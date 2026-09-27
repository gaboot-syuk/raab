@extends('layouts.auth')

@section('judul', __('otentikasi.reset.judul'))

@section('konten')
    <div class="brutal-lg bg-paper p-6 sm:p-8">
        <span class="inline-block border-2 border-ink bg-accent-400 px-2 py-0.5 text-[11px] font-bold uppercase text-primary-800">
            {{ __('otentikasi.reset.label_kecil') }}
        </span>

        <h1 class="mt-4 font-display text-2xl">{{ __('otentikasi.reset.judul') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('otentikasi.reset.teks') }}</p>

        @if ($errors->any())
            <div class="brutal-sm mt-5 border-danger bg-danger/10 px-4 py-3">
                <ul class="list-inside list-disc text-sm font-semibold">
                    @foreach ($errors->all() as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-xs font-bold uppercase tracking-wide">
                    {{ __('otentikasi.label.email') }}
                </label>
                <input
                    id="email" name="email" type="email" required autocomplete="username"
                    value="{{ old('email', $request->email) }}"
                    class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                >
            </div>

            <div>
                <label for="password" class="block text-xs font-bold uppercase tracking-wide">
                    {{ __('otentikasi.label.kata_sandi_baru') }}
                </label>
                <input
                    id="password" name="password" type="password" required autocomplete="new-password"
                    class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                >
                <p class="mt-1 text-xs text-muted">{{ __('otentikasi.label.bantuan_kata_sandi') }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wide">
                    {{ __('otentikasi.label.konfirmasi_kata_sandi') }}
                </label>
                <input
                    id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                >
            </div>

            <button type="submit" class="brutal brutal-hover w-full bg-primary-600 px-6 py-3 font-bold text-paper">
                {{ __('otentikasi.reset.tombol') }}
            </button>
        </form>
    </div>
@endsection
