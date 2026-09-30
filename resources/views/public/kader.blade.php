@extends('layouts.public')

@section('judul', $kader->nama_lengkap)
@section('og_gambar', asset('og/kader.png'))
@section('deskripsi', __('organisasi.kader.judul').' '.$kader->nama_lengkap)

@section('konten')
    <x-public.judul-halaman
        :judul="$kader->nama_lengkap"
        :deskripsi="$kader->program_studi ?: ($kader->unit?->nama ?? '')"
        :remah="[__('umum.menu.organisasi'), __('organisasi.kader.judul')]"
    />

    <div class="mx-auto max-w-5xl px-4 py-10 lg:px-6">
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Identitas --}}
            <aside class="brutal bg-paper p-5 lg:col-span-1">
                @if ($kader->foto_media_id && $foto = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($kader->foto_media_id))
                    <img src="{{ $foto->hasGeneratedConversion('sedang') ? $foto->getUrl('sedang') : $foto->getUrl() }}"
                         alt="{{ $kader->nama_lengkap }}" class="brutal-sm mb-4 aspect-square w-full object-cover">
                @endif

                <dl class="space-y-2 text-sm">
                    @if ($kader->unit)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('organisasi.kader.unit') }}</dt>
                            <dd>{{ $kader->unit->nama }}</dd>
                        </div>
                    @endif

                    @if ($kader->angkatan)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('umum.direktori.angkatan') }}</dt>
                            <dd>{{ $kader->angkatan }}</dd>
                        </div>
                    @endif

                    @if ($kader->program_studi)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('umum.direktori.prodi') }}</dt>
                            <dd>{{ $kader->program_studi }}</dd>
                        </div>
                    @endif

                    {{-- Data alumni dan keahlian hanya muncul bila pemiliknya mengizinkan. --}}
                    @if ($profil = $kader->profilAlumni)
                        @if ($profil->tahun_lulus)
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">{{ __('organisasi.kader.tahun_lulus') }}</dt>
                                <dd>{{ $profil->tahun_lulus }}</dd>
                            </div>
                        @endif

                        @if ($profil->bolehTampil('instansi') && $profil->instansi)
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">{{ __('organisasi.kader.instansi') }}</dt>
                                <dd>{{ $profil->instansi }}{{ $profil->jabatan ? ' — '.$profil->jabatan : '' }}</dd>
                            </div>
                        @endif

                        @if ($profil->bidang)
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">{{ __('organisasi.kader.bidang') }}</dt>
                                <dd>{{ $profil->bidang }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>

                @if (is_array($kader->keahlian) && count($kader->keahlian))
                    <p class="mt-4 text-xs font-bold uppercase text-muted">{{ __('organisasi.kader.keahlian') }}</p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($kader->keahlian as $keahlian)
                            <li class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold">{{ $keahlian }}</li>
                        @endforeach
                    </ul>
                @endif
            </aside>

            <div class="space-y-8 lg:col-span-2">
                {{-- Riwayat kepengurusan --}}
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">{{ __('organisasi.kader.riwayat') }}</h2>

                    @if ($riwayat->isEmpty())
                        <p class="mt-3 text-sm text-muted">{{ __('organisasi.kader.belum_ada') }}</p>
                    @else
                        <ul class="mt-3 divide-y-2 divide-ink/10">
                            @foreach ($riwayat as $baris)
                                <li class="flex flex-wrap items-baseline justify-between gap-2 py-2.5">
                                    <span class="font-bold">{{ $baris['jabatan'] }}</span>
                                    <span class="text-sm text-muted">
                                        {{ $baris['periode'] }}
                                        @if ($baris['unit'])
                                            · {{ $baris['unit'] }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- Prestasi: hanya yang sudah terverifikasi & diizinkan pemiliknya. --}}
                @if ($prestasi->isNotEmpty())
                    <section class="brutal bg-paper p-5">
                        <h2 class="font-display text-lg">{{ __('organisasi.kader.prestasi') }}</h2>
                        <ul class="mt-3 space-y-3">
                            @foreach ($prestasi as $p)
                                <li>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                                        {{ $p->labelTingkat() }} · {{ $p->labelPeringkat() }}
                                        @if ($p->kategori)
                                            · {{ $p->kategori->namaTeks() }}
                                        @endif
                                    </p>
                                    <p class="font-bold">{{ $p->judulTeks() }}</p>
                                    <p class="text-xs text-muted">
                                        {{ $p->penyelenggara }}@if ($p->penyelenggara) · @endif{{ $p->tanggal?->translatedFormat('d F Y') }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Karya --}}
                @if ($karya->isNotEmpty())
                    <section class="brutal bg-paper p-5">
                        <h2 class="font-display text-lg">{{ __('organisasi.kader.karya') }}</h2>
                        <ul class="mt-3 space-y-3">
                            @foreach ($karya as $tulisan)
                                <li>
                                    <a href="{{ route('public.publikasi.detail', [$tulisan->tipe, $tulisan->getTranslation('slug', 'id')]) }}"
                                       class="font-bold hover:underline">
                                        {{ $tulisan->getTranslation('judul', app()->getLocale(), false) ?: $tulisan->getTranslation('judul', 'id') }}
                                    </a>
                                    <p class="text-xs text-muted">{{ $tulisan->labelTipe() }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection
