@php
    /**
     * Tombol tema: Terang · Gelap, ditambah tombol Pasang aplikasi.
     *
     * Dulu ada tombol KETIGA di sini: "Ikut sistem" (ikon monitor). Tempatnya
     * sekarang dipakai `<x-public.tombol-pasang />`, jadi pilihan itu tidak
     * lagi bisa ditekan.
     *
     * Perilaku "ikut sistem" TIDAK hilang bagi pengunjung baru: selama belum
     * pernah memilih, temanya memang mengikuti pengaturan perangkat (lihat
     * kedua skrip: anti-kedip di kerangka halaman dan `terapkan()` di bawah).
     * Yang hilang hanyalah kemampuan KEMBALI ke mode itu setelah memilih —
     * itu harga dari satu tempat di header, dan disengaja.
     *
     * Karena itu tombol di sini menandai TEMA YANG SEDANG BERLAKU (`gelap`),
     * bukan pilihan yang tersimpan (`tema`). Kalau ditandai dari pilihan
     * tersimpan, pengunjung yang masih mengikuti sistem tidak akan melihat
     * satu tombol pun menyala — dan tombol tema yang tampak tak berfungsi
     * adalah keluhan yang sudah pernah terjadi di proyek ini.
     *
     * Logika ada di Alpine.data('pengalihTema') pada resources/js/app.js.
     */
@endphp

<div x-data="pengalihTema" x-init="inisialisasi()" class="flex items-center border-2 border-ink bg-paper" role="group" aria-label="{{ __('umum.tema.ganti') }}">
    <button
        type="button"
        @click="pilih('terang')"
        :class="!gelap ? 'bg-accent-400 text-primary-800' : 'text-ink hover:bg-accent-100'"
        class="px-2 py-1"
        :aria-pressed="!gelap"
        title="{{ __('umum.tema.terang') }}"
    >
        <span class="sr-only">{{ __('umum.tema.terang') }}</span>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4" stroke-linecap="square" />
        </svg>
    </button>

    <button
        type="button"
        @click="pilih('gelap')"
        :class="gelap ? 'bg-accent-400 text-primary-800' : 'text-ink hover:bg-accent-100'"
        class="border-l-2 border-ink px-2 py-1"
        :aria-pressed="gelap"
        title="{{ __('umum.tema.gelap') }}"
    >
        <span class="sr-only">{{ __('umum.tema.gelap') }}</span>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z" stroke-linecap="square" />
        </svg>
    </button>

    <x-public.tombol-pasang />
</div>
