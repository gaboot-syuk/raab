@extends('layouts.public')

@section('judul', __('umum.menu.alumni'))
@section('deskripsi', __('umum.direktori.alumni_deskripsi'))

{{-- Leaflet dimuat hanya di halaman ini — tidak perlu memberatkan halaman lain. --}}
@push('kepala')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" referrerpolicy="no-referrer">
@endpush

@section('konten')
    <x-public.judul-halaman
        :judul="__('umum.direktori.alumni_judul')"
        :deskripsi="__('umum.direktori.alumni_intro')"
        :remah="[__('umum.menu.organisasi'), __('umum.submenu.alumni')]"
    >
        @if ($jumlahMentor > 0)
            <p class="mt-3 text-sm font-bold">
                {{ __('umum.direktori.jumlah_mentor') }}: {{ $jumlahMentor }}
            </p>
        @endif
    </x-public.judul-halaman>

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <form method="GET" class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <label for="cari" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.cari') }}</label>
                <input id="cari" name="cari" type="search" value="{{ $saring['cari'] }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
            </div>

            <div>
                <label for="tahun_lulus" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.tahun_lulus') }}</label>
                <select id="tahun_lulus" name="tahun_lulus" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('umum.direktori.semua') }}</option>
                    @foreach ($daftarTahunLulus as $tahun)
                        <option value="{{ $tahun }}" @selected((int) $saring['tahun_lulus'] === $tahun)>{{ $tahun }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="bidang" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.bidang') }}</label>
                <select id="bidang" name="bidang" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('umum.direktori.semua') }}</option>
                    @foreach ($daftarBidang as $bidang)
                        <option value="{{ $bidang }}" @selected($saring['bidang'] === $bidang)>{{ $bidang }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="domisili" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.domisili') }}</label>
                <input id="domisili" name="domisili" type="text" value="{{ $saring['domisili'] }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
            </div>

            <div>
                <label for="instansi" class="block text-xs font-bold uppercase text-muted">{{ __('organisasi.direktori.instansi') }}</label>
                <input id="instansi" name="instansi" type="search" value="{{ $saring['instansi'] }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
            </div>

            <div class="flex flex-col justify-end gap-2">
                <label class="flex items-center gap-2 text-xs font-bold">
                    <input type="checkbox" name="mentor" value="1" @checked($saring['mentor'] === '1') class="h-4 w-4 border-2 border-ink">
                    {{ __('umum.direktori.hanya_mentor') }}
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-3 py-2 text-sm font-bold text-primary-800">
                        {{ __('umum.direktori.terapkan') }}
                    </button>
                    <a href="{{ route('public.alumni') }}" class="brutal-sm bg-paper px-3 py-2 text-sm font-bold">{{ __('umum.direktori.ulang') }}</a>
                </div>
            </div>
        </form>

        <p class="mt-4 text-sm text-muted">
            {{ __('umum.direktori.ditemukan') }}: <span class="font-bold text-ink">{{ $daftar->total() }}</span>
        </p>

        @if ($daftar->isEmpty())
            <p class="brutal mt-6 bg-paper-alt p-8 text-center text-sm text-muted">
                {{ __('umum.direktori.kosong') }}
            </p>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($daftar as $alumni)
                    <li class="brutal flex flex-col bg-paper p-5">
                        <h2 class="font-display text-lg leading-tight">
                            @if (! empty($alumni['slug']))
                                <a href="{{ route('public.prestasi.kader', $alumni['slug']) }}" class="hover:underline">{{ $alumni['nama'] }}</a>
                            @else
                                {{ $alumni['nama'] }}
                            @endif
                        </h2>

                        @if ($alumni['jabatan'] || $alumni['instansi'])
                            <p class="mt-1 text-sm">
                                {{ collect([$alumni['jabatan'], $alumni['instansi']])->filter()->join(' · ') }}
                            </p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-1">
                            @if ($alumni['tahun_lulus'])
                                <span class="border-2 border-ink bg-accent-100 px-1.5 py-0.5 text-[11px] font-bold">
                                    {{ __('umum.direktori.lulus') }} {{ $alumni['tahun_lulus'] }}
                                </span>
                            @endif
                            @if ($alumni['angkatan'])
                                <span class="border-2 border-ink bg-paper-alt px-1.5 py-0.5 text-[11px] font-bold">
                                    {{ __('umum.direktori.angkatan') }} {{ $alumni['angkatan'] }}
                                </span>
                            @endif
                            @if ($alumni['bersedia_mentor'])
                                <span class="border-2 border-ink bg-success/25 px-1.5 py-0.5 text-[11px] font-bold uppercase">
                                    {{ __('umum.direktori.mentor') }}
                                </span>
                            @endif
                        </div>

                        <dl class="mt-3 space-y-1 text-xs text-muted">
                            @if ($alumni['domisili'])
                                <div class="flex gap-2">
                                    <dt class="font-bold uppercase">{{ __('umum.direktori.domisili') }}:</dt>
                                    <dd>{{ $alumni['domisili'] }}</dd>
                                </div>
                            @endif
                            @if ($alumni['bidang'])
                                <div class="flex gap-2">
                                    <dt class="font-bold uppercase">{{ __('umum.direktori.bidang') }}:</dt>
                                    <dd>{{ $alumni['bidang'] }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($alumni['topik_mentor'])
                            <p class="mt-3 text-xs">
                                <span class="font-bold uppercase">{{ __('umum.direktori.topik_mentor') }}:</span>
                                {{ $alumni['topik_mentor'] }}
                            </p>
                        @endif

                        {{-- Kontak hanya tampil bila alumni mengizinkannya --}}
                        @if ($alumni['telepon'] || $alumni['email'])
                            <p class="mt-auto border-t-2 border-ink/15 pt-3 text-xs">
                                @if ($alumni['telepon'])
                                    <a href="tel:{{ $alumni['telepon'] }}" class="font-bold underline">{{ $alumni['telepon'] }}</a>
                                @endif
                                @if ($alumni['email'])
                                    <a href="mailto:{{ $alumni['email'] }}" class="ml-2 font-bold underline">{{ $alumni['email'] }}</a>
                                @endif
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">{{ $daftar->links() }}</div>
        @endif

        <p class="mt-8 border-t-2 border-ink pt-4 text-xs text-muted">
            {{ __('umum.direktori.catatan_privasi') }}
        </p>

        {{--
            Peta sebaran alumni.

            Hanya alumni yang MENGISI koordinatnya sendiri yang muncul — dan
            hanya kolom yang mereka izinkan. Data dikirim sebagai JSON lalu
            digambar oleh Leaflet di peramban; tidak ada permintaan ke server
            pihak ketiga selain ubin peta OpenStreetMap.
        --}}
        <section class="mt-10 border-t-2 border-ink pt-8">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-xl">{{ __('organisasi.peta.judul') }}</h2>

                @if ($peta->isNotEmpty())
                    <p class="text-sm text-muted">
                        <span class="font-bold text-ink">{{ $peta->count() }}</span>
                        {{ __('organisasi.peta.jumlah') }}
                    </p>
                @endif
            </div>

            <p class="mt-1 text-sm text-muted">{{ __('organisasi.peta.keterangan') }}</p>

            <div
                id="peta-alumni"
                class="brutal mt-4 w-full bg-paper-alt"
                style="height: 24rem"
                data-titik="{{ $peta->toJson() }}"
                role="img"
                aria-label="{{ __('organisasi.peta.judul') }}"
            >
                <noscript>
                    <p class="p-6 text-sm text-muted">{{ __('organisasi.peta.kosong') }}</p>
                </noscript>
            </div>
        </section>
    </div>
@endsection

@push('skrip')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        (function () {
            var wadah = document.getElementById('peta-alumni');
            if (!wadah) { return; }

            var titik = [];
            try {
                titik = JSON.parse(wadah.dataset.titik || '[]');
            } catch (e) {
                titik = [];
            }

            if (typeof L === 'undefined' || titik.length === 0) {
                wadah.innerHTML = '<p class="p-6 text-sm text-muted">{{ __('organisasi.peta.kosong') }}</p>';
                return;
            }

            var peta = L.map(wadah, { scrollWheelZoom: false });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(peta);

            // Isi popup dibangun sebagai simpul DOM, BUKAN rangkaian teks HTML,
            // supaya nama atau instansi yang diketik alumni tidak dapat
            // disisipkan sebagai markup di halaman ini.
            function isiPopup(t) {
                var kotak = document.createElement('div');

                var nama = document.createElement('strong');
                nama.textContent = t.nama || '';
                kotak.appendChild(nama);

                [t.instansi, t.kota, t.tahun_lulus ? 'Lulus ' + t.tahun_lulus : null].forEach(function (baris) {
                    if (!baris) { return; }
                    var p = document.createElement('div');
                    p.textContent = baris;
                    p.style.fontSize = '12px';
                    kotak.appendChild(p);
                });

                if (t.slug) {
                    var tautan = document.createElement('a');
                    tautan.href = '/prestasi/kader/' + t.slug;
                    tautan.textContent = '{{ __('organisasi.peta.kunjungi') }} →';
                    tautan.style.fontSize = '12px';
                    tautan.style.fontWeight = 'bold';
                    kotak.appendChild(tautan);
                }

                return kotak;
            }

            var batas = [];

            titik.forEach(function (t) {
                if (typeof t.lat !== 'number' || typeof t.lng !== 'number') { return; }
                batas.push([t.lat, t.lng]);
                L.marker([t.lat, t.lng]).addTo(peta).bindPopup(isiPopup(t));
            });

            if (batas.length === 1) {
                peta.setView(batas[0], 11);
            } else if (batas.length > 1) {
                peta.fitBounds(batas, { padding: [30, 30], maxZoom: 11 });
            }
        })();
    </script>
@endpush
