<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'PMII RAAB') }}</title>

    {{-- Anti-kedip: pasang kelas tema sebelum halaman dirender --}}
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

    @vite(['resources/css/app.css', 'resources/js/inertia.ts'])
    @inertiaHead
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    @inertia
</body>
</html>
