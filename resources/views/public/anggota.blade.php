@extends('layouts.public')

@section('judul', __('umum.menu.anggota'))
@section('deskripsi', __('umum.direktori.anggota_deskripsi'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('umum.direktori.anggota_judul')"
        :deskripsi="__('umum.direktori.anggota_intro')"
        :remah="[__('umum.menu.organisasi'), __('umum.menu.anggota')]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        {{-- Saringan --}}
        <form method="GET" class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <label for="cari" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.cari') }}</label>
                <input id="cari" name="cari" type="search" value="{{ $saring['cari'] }}" placeholder="{{ __('organisasi.direktori.slug_pencarian') }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
            </div>

            <div>
                <label for="angkatan" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.angkatan') }}</label>
                <select id="angkatan" name="angkatan" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('umum.direktori.semua') }}</option>
                    @foreach ($daftarAngkatan as $tahun)
                        <option value="{{ $tahun }}" @selected((int) $saring['angkatan'] === $tahun)>{{ $tahun }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="unit" class="block text-xs font-bold uppercase text-muted">{{ __('umum.direktori.unit') }}</label>
                <select id="unit" name="unit" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('umum.direktori.semua') }}</option>
                    @foreach ($daftarUnit as $satuan)
                        <option value="{{ $satuan->id }}" @selected((int) $saring['unit'] === $satuan->id)>{{ $satuan->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="fakultas" class="block text-xs font-bold uppercase text-muted">{{ __('organisasi.direktori.fakultas') }}</label>
                <select id="fakultas" name="fakultas" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('umum.direktori.semua') }}</option>
                    @foreach ($daftarFakultas as $fakultas)
                        <option value="{{ $fakultas }}" @selected($saring['fakultas'] === $fakultas)>{{ $fakultas }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="prodi" class="block text-xs font-bold uppercase text-muted">{{ __('organisasi.direktori.prodi') }}</label>
                <select id="prodi" name="prodi" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option value="">{{ __('umum.direktori.semua') }}</option>
                    @foreach ($daftarProdi as $prodi)
                        <option value="{{ $prodi }}" @selected($saring['prodi'] === $prodi)>{{ $prodi }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    {{ __('umum.direktori.terapkan') }}
                </button>
                @if (collect($saring)->filter(fn ($nilai) => $nilai !== '' && $nilai !== null)->isNotEmpty())
                    <a href="{{ route('public.anggota') }}" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">{{ __('umum.direktori.ulang') }}</a>
                @endif
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
                @foreach ($daftar as $anggota)
                    <li class="brutal bg-paper p-5">
                        <h2 class="font-display text-lg leading-tight">
                            @if (! empty($anggota['slug']))
                                <a href="{{ route('public.prestasi.kader', $anggota['slug']) }}" class="hover:underline">{{ $anggota['nama'] }}</a>
                            @else
                                {{ $anggota['nama'] }}
                            @endif
                        </h2>

                        <p class="mt-1 text-sm text-muted">
                            {{ collect([$anggota['program_studi'], $anggota['fakultas']])->filter()->join(' · ') ?: __('umum.direktori.prodi_kosong') }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-1">
                            @if ($anggota['angkatan'])
                                <span class="border-2 border-ink bg-accent-100 px-1.5 py-0.5 text-[11px] font-bold">
                                    {{ __('umum.direktori.angkatan') }} {{ $anggota['angkatan'] }}
                                </span>
                            @endif
                            @if ($anggota['unit'])
                                <span class="border-2 border-ink bg-paper-alt px-1.5 py-0.5 text-[11px] font-bold">{{ $anggota['unit'] }}</span>
                            @endif
                        </div>

                        @if (! empty($anggota['keahlian']))
                            <p class="mt-3 text-xs text-muted">
                                <span class="font-bold uppercase">{{ __('umum.direktori.keahlian') }}:</span>
                                {{ implode(', ', $anggota['keahlian']) }}
                            </p>
                        @endif

                        @if (! empty($anggota['sosmed']))
                            <p class="mt-2 flex flex-wrap gap-2 text-xs">
                                @foreach ($anggota['sosmed'] as $platform => $tautan)
                                    @if (filled($tautan))
                                        <a href="{{ $tautan }}" target="_blank" rel="noopener noreferrer" class="font-bold underline">{{ $platform }}</a>
                                    @endif
                                @endforeach
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
    </div>
@endsection
