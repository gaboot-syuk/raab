@php
    /**
     * Navigasi bawah — khusus layar kecil (mobile-first).
     * Hanya menampilkan tanpa tautan bila rutenya belum tersedia.
     */
    $item = [
        ['label' => __('umum.menu.beranda'), 'rute' => 'public.beranda', 'ikon' => 'M3 10.5 12 3l9 7.5V21H3z'],
        ['label' => __('umum.menu.publikasi'), 'rute' => 'public.publikasi', 'ikon' => 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5'],
        ['label' => __('umum.menu.pendaftaran'), 'rute' => 'public.pendaftaran.mapaba', 'ikon' => 'M12 5v14M5 12h14'],
        ['label' => __('umum.menu.lso'), 'rute' => 'public.lso', 'ikon' => 'M4 20V9l8-5 8 5v11H4zM9 20v-6h6v6'],
        ['label' => __('umum.tombol.masuk'), 'rute' => 'login', 'ikon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0'],
    ];
@endphp

<nav
    class="fixed inset-x-0 bottom-0 z-30 border-t-2 border-ink bg-paper lg:hidden"
    aria-label="{{ __('umum.menu.layanan') }}"
>
    <ul class="grid grid-cols-5">
        @foreach ($item as $menu)
            <li>
                @if (Route::has($menu['rute']))
                    @php $aktif = request()->routeIs($menu['rute']); @endphp
                    <a
                        href="{{ route($menu['rute']) }}"
                        class="flex flex-col items-center gap-1 px-1 py-2 text-[11px] font-bold {{ $aktif ? 'bg-accent-400 text-primary-800' : 'text-primary-800' }}"
                        @if ($aktif) aria-current="page" @endif
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                            <path d="{{ $menu['ikon'] }}" stroke-linecap="square" />
                        </svg>
                        <span class="truncate">{{ $menu['label'] }}</span>
                    </a>
                @else
                    <span class="flex flex-col items-center gap-1 px-1 py-2 text-[11px] font-bold text-muted">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                            <path d="{{ $menu['ikon'] }}" stroke-linecap="square" />
                        </svg>
                        <span class="truncate">{{ $menu['label'] }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
