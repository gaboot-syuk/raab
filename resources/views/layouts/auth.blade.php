<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('judul', __('otentikasi.masuk.judul')) — {{ __('umum.nama_singkat') }}</title>

    <script>
        (function () {
            try {
                var tema = localStorage.getItem('tema') || 'sistem';
                var gelap = tema === 'gelap' ||
                    (tema === 'sistem' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', gelap);
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-paper-alt text-ink antialiased">
    {{-- Kepala ringkas --}}
    <header class="border-b-2 border-ink bg-brand text-on-brand">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('public.beranda') }}" class="flex min-w-0 items-center gap-3">
                <img
                    src="{{ asset('brand/logo-pmii-raab.png') }}"
                    alt="Logo {{ __('umum.nama_organisasi') }}"
                    class="h-10 w-10 shrink-0 border-2 border-ink bg-on-brand object-contain p-0.5"
                    width="96" height="96" loading="eager" decoding="async"
                >
                <span class="min-w-0">
                    <span class="block truncate font-display text-sm leading-tight">{{ __('umum.nama_singkat') }}</span>
                    <span class="block truncate text-[11px] leading-tight text-on-brand/75">{{ __('umum.nama_komisariat') }}</span>
                </span>
            </a>

            <div class="flex items-center gap-2">
                <x-public.locale-switch />
                <x-public.theme-toggle />
            </div>
        </div>
    </header>

    <main class="flex flex-1 items-start justify-center px-4 py-8 sm:items-center sm:py-12">
        {{-- Lebar wadah dapat diatur per halaman: pendaftaran butuh ruang lebih luas. --}}
        <div class="w-full @yield('lebar', 'max-w-md')">
            @if (session('status'))
                <div class="brutal-sm mb-4 border-success bg-success/10 px-4 py-3 text-sm font-semibold">
                    {{ session('status') }}
                </div>
            @endif

            @yield('konten')
        </div>
    </main>

    <footer class="border-t-2 border-ink py-4">
        <div class="mx-auto max-w-3xl px-4 text-center text-xs text-muted">
            <p>&copy; {{ date('Y') }} {{ __('umum.nama_organisasi') }}</p>
            <p class="mt-1">
                <a href="{{ route('public.beranda') }}" class="font-bold underline">{{ __('otentikasi.tautan.beranda') }}</a>
            </p>
        </div>
    </footer>
</body>
</html>
