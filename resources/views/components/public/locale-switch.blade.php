@php
    /**
     * Pengalih bahasa — mempertahankan halaman yang sedang dibuka.
     */
    $aktif = app()->getLocale();
    $daftar = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getSupportedLocales();
@endphp

<div class="flex items-center border-2 border-ink bg-paper" role="group" aria-label="{{ __('umum.bahasa.ganti') }}">
    @foreach ($daftar as $kode => $info)
        @php $iniAktif = $kode === $aktif; @endphp
        <a
            href="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL($kode) }}"
            hreflang="{{ $kode }}"
            @if ($iniAktif) aria-current="true" @endif
            class="px-2 py-1 text-xs font-bold uppercase {{ $iniAktif ? 'bg-accent-400 text-primary-800' : 'text-primary-800 hover:bg-accent-100' }}"
        >{{ strtoupper($kode) }}</a>
    @endforeach
</div>
