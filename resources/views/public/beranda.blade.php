@extends('layouts.public')

@section('judul', __('umum.menu.beranda'))
@section('deskripsi', __('umum.beranda.hero_teks'))

@section('konten')
    {{-- ============ HERO ============ --}}
    @if ($slider->isNotEmpty())
        {{-- Slider dikelola pengurus lewat Panel → Slider Beranda --}}
        <section
            class="relative border-b-2 border-ink bg-brand-dark text-on-brand"
            x-data="{
                aktif: 0,
                jumlah: {{ $slider->count() }},
                jam: null,
                mulai() {
                    if (this.jumlah < 2) return;
                    this.jam = setInterval(() => this.berikutnya(), 8000);
                },
                berikutnya() {
                    this.aktif = (this.aktif + 1) % this.jumlah;
                },
                ke(i) {
                    this.aktif = i;
                },
            }"
            x-init="mulai()"
            @mouseenter="clearInterval(jam)"
            @mouseleave="mulai()"
        >
            <div class="relative min-h-[26rem] lg:min-h-[32rem]">
                @foreach ($slider as $i => $banner)
                    <div
                        class="absolute inset-0 transition-opacity duration-700"
                        x-show="aktif === {{ $i }}"
                        x-transition:enter="ease-out duration-700"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-cloak
                    >
                        @if ($banner['gambar'])
                            <img
                                src="{{ $banner['gambar'] }}"
                                alt=""
                                aria-hidden="true"
                                class="h-full w-full object-cover"
                                loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                decoding="async"
                            >
                            {{-- Lapisan gelap agar teks tetap terbaca di atas foto apa pun --}}
                            <div class="absolute inset-0 bg-brand-dark/75"></div>
                        @endif

                        <div class="absolute inset-0 grid place-items-center px-4 py-14 lg:px-6">
                            <div class="mx-auto max-w-3xl text-center">
                                <h1 class="font-display text-3xl leading-[1.1] sm:text-5xl lg:text-6xl">
                                    {{ $banner['judul'] }}
                                </h1>

                                @if ($banner['subjudul'])
                                    <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-on-brand/90 sm:text-lg">
                                        {{ $banner['subjudul'] }}
                                    </p>
                                @endif

                                @if ($banner['label_tombol'] && $banner['tautan_tombol'])
                                    <a
                                        href="{{ $banner['tautan_tombol'] }}"
                                        class="brutal brutal-hover mt-8 inline-block bg-accent-400 px-6 py-3 font-bold text-primary-800"
                                    >{{ $banner['label_tombol'] }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Penunjuk slide --}}
            @if ($slider->count() > 1)
                <div class="absolute bottom-5 left-1/2 z-10 flex -translate-x-1/2 gap-2">
                    @foreach ($slider as $i => $banner)
                        <button
                            type="button"
                            class="h-3 w-8 border-2 border-on-brand"
                            :class="aktif === {{ $i }} ? 'bg-accent-400' : 'bg-transparent'"
                            @click="ke({{ $i }})"
                            aria-label="Tampilkan slide {{ $i + 1 }}"
                        ></button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        {{-- Tampilan bawaan sebelum pengurus membuat slider --}}
        <section class="border-b-2 border-ink bg-paper">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 lg:grid-cols-2 lg:items-center lg:px-6 lg:py-20">
                <div>
                    <span class="inline-block border-2 border-ink bg-accent-400 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary-800">
                        {{ \App\Support\Pengaturan::teks('nama_kampus', __('umum.nama_kampus')) }}
                    </span>

                    <h1 class="mt-5 font-display text-4xl leading-[1.05] sm:text-5xl lg:text-6xl">
                        {{ __('umum.beranda.hero_judul') }}
                    </h1>

                    <p class="mt-5 max-w-xl text-lg leading-relaxed text-ink/85">
                        {{ __('umum.beranda.hero_teks') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @if (Route::has('public.pendaftaran'))
                            <a href="{{ route('public.pendaftaran') }}" class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800">
                                {{ __('umum.tombol.daftar_sekarang') }}
                            </a>
                        @endif

                        @if (Route::has('public.sejarah'))
                            <a href="{{ route('public.sejarah') }}" class="brutal brutal-hover bg-paper px-6 py-3 font-bold">
                                {{ __('umum.tombol.selengkapnya') }}
                            </a>
                        @endif
                    </div>

                    <p class="mt-6 text-sm text-muted">{{ __('umum.beranda.hero_ajakan') }}</p>
                </div>

                <div class="relative">
                    <div class="brutal-lg -rotate-2 bg-primary-600 p-6">
                        <img
                            src="{{ asset('brand/logo-pmii-raab.png') }}"
                            alt="Logo {{ __('umum.nama_organisasi') }}"
                            class="mx-auto h-56 w-56 object-contain lg:h-72 lg:w-72"
                            width="512" height="512" loading="eager" decoding="async"
                        >
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ============ PITA BERJALAN ============ --}}
    @php
        $pita = \App\Support\Pengaturan::teks('pita_teks', __('umum.beranda.pendaftaran_judul'));
    @endphp

    <div class="overflow-hidden border-b-2 border-ink bg-primary-600 text-paper">
        <div class="pita-berjalan flex w-max">
            @for ($i = 0; $i < 2; $i++)
                <div class="flex shrink-0 items-center gap-8 px-6 py-2 text-sm font-bold uppercase tracking-wide" aria-hidden="{{ $i === 1 ? 'true' : 'false' }}">
                    <span>{{ $pita }}</span>
                    <span aria-hidden="true">★</span>
                    <span>{{ __('umum.nama_organisasi') }}</span>
                    <span aria-hidden="true">★</span>
                    <span>{{ __('umum.menu.lso') }}</span>
                    <span aria-hidden="true">★</span>
                    <span>{{ __('umum.nama_komisariat') }} — {{ __('umum.nama_cabang') }}</span>
                    <span aria-hidden="true">★</span>
                </div>
            @endfor
        </div>
    </div>

    {{-- ============ STATISTIK ============ --}}
    <section class="border-b-2 border-ink">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-4 px-4 py-10 lg:grid-cols-4 lg:px-6">
            @foreach ($statistik as $angka)
                <div class="brutal brutal-hover bg-paper-alt p-5 text-center">
                    <p class="font-display text-3xl lg:text-4xl">{{ $angka['nilai'] }}</p>
                    <p class="mt-2 border-t-2 border-ink pt-2 text-xs font-bold uppercase tracking-wide text-muted">{{ $angka['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============ SAMBUTAN ============ --}}
    <section class="border-b-2 border-ink">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 lg:grid-cols-3 lg:px-6">
            <div class="lg:col-span-2">
                <h2 class="font-display text-2xl sm:text-3xl">{{ __('umum.beranda.sambutan_judul') }}</h2>
                <div class="mt-5 space-y-4 text-base leading-relaxed text-ink/85">
                    <p>{{ __('umum.umum.placeholder') }}</p>
                    <p>{{ __('umum.footer.tentang_teks') }}</p>
                </div>
            </div>
            <div class="brutal bg-accent-100 p-5">
                <div class="brutal-sm mb-4 aspect-square bg-paper-alt" aria-hidden="true"></div>
                <p class="font-bold">{{ __('umum.umum.segera') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('umum.umum.placeholder') }}</p>
            </div>
        </div>
    </section>

    {{-- ============ SAREKAT LSO ============ --}}
    <section class="border-b-2 border-ink bg-paper-alt">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="font-display text-2xl sm:text-3xl">{{ __('umum.beranda.lso_judul') }}</h2>
                    <p class="mt-2 max-w-2xl text-ink/80">{{ __('umum.beranda.lso_teks') }}</p>
                </div>
                @if (Route::has('public.lso'))
                    <a href="{{ route('public.lso') }}" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                        {{ __('umum.tombol.lihat_semua') }}
                    </a>
                @endif
            </div>

            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($lso as $unit)
                    <li class="brutal brutal-hover bg-paper p-4">
                        <div class="brutal-sm mb-3 flex h-12 w-12 items-center justify-center bg-primary-600 font-display text-sm text-paper">
                            {{ strtoupper(substr($unit['nama'], 0, 2)) }}
                        </div>
                        <h3 class="font-display text-base">{{ $unit['nama'] }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $unit['bidang'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ============ BERITA TERBARU ============ --}}
    <section class="border-b-2 border-ink">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="font-display text-2xl sm:text-3xl">{{ __('umum.beranda.berita_judul') }}</h2>

                @if (Route::has('public.publikasi'))
                    <a href="{{ route('public.publikasi') }}" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                        {{ __('umum.tombol.lihat_semua') }}
                    </a>
                @endif
            </div>

            @if ($berita->isEmpty())
                {{-- Jujur kepada pengunjung: belum ada berita, bukan menampilkan kartu palsu. --}}
                <p class="brutal mt-8 bg-paper-alt px-5 py-8 text-center text-sm text-muted">
                    {{ __('umum.beranda.berita_kosong') }}
                </p>
            @else
                <div class="mt-8 grid gap-5 lg:grid-cols-3">
                    @foreach ($berita as $artikel)
                        @php
                            $slugBerita = $artikel->getTranslation('slug', app()->getLocale(), false)
                                ?: $artikel->getTranslation('slug', 'id', false);
                        @endphp

                        <article class="brutal brutal-hover flex flex-col bg-paper">
                            @if ($artikel->cover_media_id)
                                @php $sampul = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($artikel->cover_media_id); @endphp
                                @if ($sampul)
                                    <div class="border-b-2 border-ink">
                                        <img
                                            src="{{ $sampul->hasGeneratedConversion('kecil') ? $sampul->getUrl('kecil') : $sampul->getUrl() }}"
                                            alt=""
                                            aria-hidden="true"
                                            loading="lazy"
                                            decoding="async"
                                            class="aspect-video w-full object-cover"
                                        >
                                    </div>
                                @endif
                            @endif

                            <div class="flex flex-1 flex-col p-5">
                                <span class="inline-block self-start border-2 border-ink bg-accent-100 px-2 py-0.5 text-[11px] font-bold uppercase">
                                    {{ $artikel->kategori?->nama ?? $artikel->labelTipe() }}
                                </span>

                                <h3 class="mt-3 font-bold leading-snug">
                                    <a
                                        href="{{ route('public.publikasi.detail', ['tipe' => $artikel->tipe, 'slug' => $slugBerita]) }}"
                                        class="hover:underline"
                                    >{{ $artikel->judul }}</a>
                                </h3>

                                @if ($artikel->ringkasan)
                                    <p class="mt-2 line-clamp-3 text-sm text-muted">{{ $artikel->ringkasan }}</p>
                                @endif

                                <p class="mt-auto pt-4 text-xs text-muted">
                                    {{ $artikel->terbit_pada?->translatedFormat('d M Y') }}
                                    @if ($artikel->waktu_baca_menit)
                                        · {{ $artikel->waktu_baca_menit }} {{ __('umum.publikasi.menit') }}
                                    @endif
                                </p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ============ CTA PENDAFTARAN ============ --}}
    <section class="border-b-2 border-ink bg-accent-400 text-primary-800">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-6">
            <h2 class="font-display text-2xl sm:text-3xl">{{ __('umum.beranda.pendaftaran_judul') }}</h2>
            <p class="mt-3 max-w-2xl font-semibold">{{ __('umum.beranda.pendaftaran_teks') }}</p>

            <div class="mt-6 flex flex-wrap gap-3">
                @if (Route::has('public.pendaftaran.jenis'))
                    <a href="{{ route('public.pendaftaran.jenis', ['jenis' => 'mapaba']) }}" class="brutal brutal-hover bg-paper px-6 py-3 font-bold">
                        {{ __('umum.submenu.mapaba') }}
                    </a>
                    <a href="{{ route('public.pendaftaran.jenis', ['jenis' => 'pkd']) }}" class="brutal brutal-hover bg-primary-600 px-6 py-3 font-bold text-paper">
                        {{ __('umum.submenu.pkd') }}
                    </a>
                @endif
            </div>
        </div>
    </section>
@endsection
