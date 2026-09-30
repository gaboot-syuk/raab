@props(['mandiri' => false])

@php
    /**
     * Tombol "Pasang aplikasi" — pintasan ke layar utama.
     *
     * TIDAK ADA SERVICE WORKER DI BALIK TOMBOL INI.
     *
     * Pemasangan ke layar utama tidak membutuhkannya, dan memang tidak
     * diinginkan di sini: situs ini tidak dirancang untuk dibaca tanpa
     * jaringan. Karena itu tidak ada satu byte pun yang disimpan di perangkat
     * pengunjung — tidak ada isi yang bisa basi, dan tidak ada data yang
     * tertinggal di ponsel yang dipakai bergantian.
     *
     * DUA JALAN, karena peramban tidak seragam:
     *
     *  - Android/Chrome/Edge memicu `beforeinstallprompt` dan mengizinkan
     *    pemasangan dari halaman. Tombol ini memanggil tawaran itu.
     *  - iOS TIDAK punya API pemasangan sama sekali. Satu-satunya jalan adalah
     *    menu Bagikan di Safari, jadi tombol ini menampilkan panduan
     *    langkah demi langkah.
     *
     * DUA RUPA, karena tempatnya berbeda:
     *
     *  - Di layar lebar ia duduk di dalam grup tema dan cukup memakai garis
     *    pemisah (`mandiri = false`).
     *  - Di bilah atas ponsel ia berdiri sendiri, jadi butuh bingkai penuh
     *    seperti tombol di sebelahnya (`mandiri = true`).
     */
    $kelasTombol = $mandiri
        ? 'border-2 border-ink bg-paper px-2 py-1 text-xs font-bold uppercase text-ink hover:bg-accent-100'
        : 'border-l-2 border-ink px-2 py-1 text-ink hover:bg-accent-100';
@endphp

<div x-data="pasangAplikasi" x-init="inisialisasi()">
    <button
        type="button"
        x-show="tampil"
        x-cloak
        @click="pasang()"
        class="{{ $kelasTombol }}"
        title="{{ __('umum.pasang.label') }}"
    >
        <span class="sr-only">{{ __('umum.pasang.label') }}</span>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path d="M12 3v12" stroke-linecap="square" />
            <path d="M7 10l5 5 5-5" stroke-linecap="square" />
            <path d="M4 20h16" stroke-linecap="square" />
        </svg>
    </button>

    {{-- Panduan khusus iOS --}}
    <div
        x-show="panduan"
        x-cloak
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4"
        @click.self="panduan = false"
    >
        <div class="brutal w-full max-w-md bg-paper p-5 text-left" role="dialog" aria-modal="true">
            <h2 class="font-display text-lg">{{ __('umum.pasang.judul') }}</h2>

            <p class="mt-2 text-sm text-muted">{{ __('umum.pasang.pengantar') }}</p>

            <ol class="mt-4 space-y-2 text-sm">
                @foreach (__('umum.pasang.langkah') as $nomor => $teks)
                    <li class="flex gap-3">
                        <span class="brutal-sm shrink-0 bg-accent-400 px-2 text-xs font-bold text-primary-800">{{ $nomor + 1 }}</span>
                        <span>{{ $teks }}</span>
                    </li>
                @endforeach
            </ol>

            <p class="mt-4 border-t-2 border-ink pt-3 text-xs text-muted">{{ __('umum.pasang.catatan_ios') }}</p>

            <button
                type="button"
                class="brutal-sm brutal-hover mt-4 bg-primary-600 px-4 py-2 text-sm font-bold text-paper"
                @click="panduan = false"
            >{{ __('umum.pasang.tutup') }}</button>
        </div>
    </div>
</div>
