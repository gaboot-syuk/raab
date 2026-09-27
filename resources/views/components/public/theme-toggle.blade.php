@php
    /**
     * Tombol tema: Terang → Gelap → Ikut sistem.
     * Logika ada di Alpine.data('pengalihTema') pada resources/js/app.js
     */
@endphp

<div x-data="pengalihTema" x-init="inisialisasi()" class="flex items-center border-2 border-ink bg-paper" role="group" aria-label="{{ __('umum.tema.ganti') }}">
    <button
        type="button"
        @click="pilih('terang')"
        :class="tema === 'terang' && 'bg-accent-400 text-primary-800'"
        class="px-2 py-1 text-primary-800 hover:bg-accent-100"
        :aria-pressed="tema === 'terang'"
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
        :class="tema === 'gelap' && 'bg-accent-400 text-primary-800'"
        class="border-l-2 border-ink px-2 py-1 text-primary-800 hover:bg-accent-100"
        :aria-pressed="tema === 'gelap'"
        title="{{ __('umum.tema.gelap') }}"
    >
        <span class="sr-only">{{ __('umum.tema.gelap') }}</span>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z" stroke-linecap="square" />
        </svg>
    </button>

    <button
        type="button"
        @click="pilih('sistem')"
        :class="tema === 'sistem' && 'bg-accent-400 text-primary-800'"
        class="border-l-2 border-ink px-2 py-1 text-primary-800 hover:bg-accent-100"
        :aria-pressed="tema === 'sistem'"
        title="{{ __('umum.tema.sistem') }}"
    >
        <span class="sr-only">{{ __('umum.tema.sistem') }}</span>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <rect x="3" y="4" width="18" height="12" rx="1" />
            <path d="M8 20h8M12 16v4" stroke-linecap="square" />
        </svg>
    </button>
</div>
