@props([
    'type' => 'button',
    'href' => null,
    'warna' => 'accent',
])

@php
    // Tombol utama bergaya neo-brutalism. Dipakai berulang di halaman publik
    // supaya bentuk & perilaku hover-nya konsisten.
    $kelas = 'brutal brutal-hover inline-block px-5 py-2.5 text-sm font-bold '
        .($warna === 'accent' ? 'bg-accent-400 text-primary-800' : 'bg-paper text-ink');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $kelas]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $kelas]) }}>{{ $slot }}</button>
@endif
