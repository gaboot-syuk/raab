@props(['platform' => 'lainnya'])

@php
    // Ikon garis sederhana per platform. Memakai stroke="currentColor"
    // supaya warna mengikuti induknya (aman di mode terang maupun gelap).
    $jalur = match (strtolower((string) $platform)) {
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.2" cy="6.8" r="1.2" fill="currentColor" stroke="none" />',
        'facebook' => '<path d="M14 8.5h2.5V5.2H14c-2.2 0-3.5 1.4-3.5 3.6v1.6H8.2v3.2h2.3V21h3.2v-7.4h2.4l.5-3.2h-2.9V9.2c0-.5.3-.7.8-.7z" />',
        'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="4" /><path d="M10.5 9.5l5 2.5-5 2.5z" fill="currentColor" stroke="none" />',
        'tiktok' => '<path d="M14.5 3.5v9.8a3.2 3.2 0 1 1-3.2-3.2c.3 0 .6 0 .9.1" /><path d="M14.5 6.2c.8 1.4 2.2 2.3 3.9 2.4" />',
        'whatsapp' => '<path d="M20.5 12a8.5 8.5 0 0 1-12.6 7.5L3.5 20.5l1.1-4.2A8.5 8.5 0 1 1 20.5 12z" /><path d="M9.2 9.4c.3 1.9 1.6 3.2 3.5 3.5" />',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3.5 6.5 12 13l8.5-6.5" />',
        default => '<circle cx="12" cy="12" r="9" /><path d="M3.2 12h17.6M12 3.2c2.4 2.4 2.4 15.2 0 17.6M12 3.2c-2.4 2.4-2.4 15.2 0 17.6" />',
    };
@endphp

<svg
    {{ $attributes->merge(['class' => 'h-5 w-5']) }}
    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
    stroke-linecap="square" stroke-linejoin="round"
    aria-hidden="true" focusable="false"
>
    {!! $jalur !!}
</svg>
