@props([
    'judul',
    'deskripsi' => null,
    'remah' => [],
])

{{-- Kepala halaman publik: remah navigasi + judul + deskripsi pengantar. --}}
<header class="border-b-2 border-ink bg-paper-alt">
    <div class="mx-auto max-w-5xl px-4 py-8 lg:px-6 lg:py-10">
        <nav class="text-xs font-bold uppercase tracking-wide text-muted" aria-label="{{ __('umum.menu.beranda') }}">
            <a href="{{ route('public.beranda') }}" class="underline">{{ __('umum.menu.beranda') }}</a>
            @foreach ($remah as $label)
                <span aria-hidden="true"> › </span>
                <span>{{ $label }}</span>
            @endforeach
        </nav>

        <h1 class="mt-4 font-display text-3xl leading-tight sm:text-4xl">{{ $judul }}</h1>

        @if ($deskripsi)
            <p class="mt-3 max-w-3xl text-base text-muted sm:text-lg">{{ $deskripsi }}</p>
        @endif

        {{ $slot }}
    </div>
</header>
