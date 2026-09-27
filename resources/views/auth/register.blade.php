@extends('layouts.auth')

@section('judul', __('otentikasi.daftar.judul'))
@section('lebar', 'max-w-3xl')

@section('konten')
    @php
        $unit = \App\Models\OrganisationUnit::query()->aktif()->orderBy('jenis')->orderBy('urutan')->get();
        $jalurAwal = old('jalur', 'kader');
        $kolom = 'brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm';
    @endphp

    <div class="brutal bg-paper p-5 sm:p-6" x-data="{ jalur: '{{ $jalurAwal }}' }">
        <h1 class="font-display text-2xl">{{ __('otentikasi.daftar.judul') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('otentikasi.daftar.pengantar') }}</p>

        @if ($errors->any())
            <div role="alert" class="brutal-sm mt-5 border-ink bg-accent-100 px-3 py-2 text-sm">
                <p class="font-bold">{{ __('umum.umum.periksa_kembali') }}</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-7">
            @csrf

            {{-- Jalur pendaftaran --}}
            <fieldset class="border-2 border-ink p-4">
                <legend class="px-1 text-sm font-bold">{{ __('otentikasi.daftar.pilih_jalur') }}</legend>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['kader' => __('otentikasi.daftar.jalur_kader_teks'), 'alumni' => __('otentikasi.daftar.jalur_alumni_teks')] as $nilai => $keterangan)
                        <label
                            class="flex cursor-pointer items-start gap-3 border-2 border-ink p-3"
                            :class="jalur === '{{ $nilai }}' ? 'bg-accent-400' : 'bg-paper-alt'"
                        >
                            <input type="radio" name="jalur" value="{{ $nilai }}" x-model="jalur" class="mt-0.5 h-4 w-4 border-2 border-ink" @checked($jalurAwal === $nilai)>
                            <span>
                                <span class="block font-bold">{{ __('otentikasi.daftar.jalur_'.$nilai) }}</span>
                                <span class="mt-1 block text-xs text-muted">{{ $keterangan }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Akun --}}
            <fieldset class="space-y-4">
                <legend class="font-display text-lg">{{ __('otentikasi.daftar.bagian_akun') }}</legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-sm font-bold">{{ __('otentikasi.daftar.nama') }} *</label>
                        <input id="name" name="name" type="text" required maxlength="160" value="{{ old('name') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-bold">{{ __('otentikasi.daftar.email') }} *</label>
                        <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="telepon" class="block text-sm font-bold">{{ __('otentikasi.daftar.telepon') }} *</label>
                        <input id="telepon" name="telepon" type="text" required maxlength="40" value="{{ old('telepon') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-bold">{{ __('otentikasi.daftar.sandi') }} *</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-bold">{{ __('otentikasi.daftar.sandi_ulang') }} *</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $kolom }}">
                    </div>
                    <p class="text-xs text-muted sm:col-span-2">{{ __('otentikasi.daftar.sandi_bantuan') }}</p>
                </div>
            </fieldset>

            {{-- Pribadi --}}
            <fieldset class="space-y-4 border-t-2 border-ink pt-6">
                <legend class="font-display text-lg">{{ __('otentikasi.daftar.bagian_pribadi') }}</legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="jenis_kelamin" class="block text-sm font-bold">{{ __('otentikasi.daftar.jenis_kelamin') }} *</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" required class="{{ $kolom }}">
                            <option value="">— {{ __('otentikasi.daftar.pilih') }} —</option>
                            <option value="laki_laki" @selected(old('jenis_kelamin') === 'laki_laki')>{{ __('otentikasi.daftar.laki_laki') }}</option>
                            <option value="perempuan" @selected(old('jenis_kelamin') === 'perempuan')>{{ __('otentikasi.daftar.perempuan') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="tanggal_lahir" class="block text-sm font-bold">{{ __('otentikasi.daftar.tanggal_lahir') }} *</label>
                        <input id="tanggal_lahir" name="tanggal_lahir" type="date" required value="{{ old('tanggal_lahir') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="tempat_lahir" class="block text-sm font-bold">{{ __('otentikasi.daftar.tempat_lahir') }} *</label>
                        <input id="tempat_lahir" name="tempat_lahir" type="text" required maxlength="120" value="{{ old('tempat_lahir') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="alamat" class="block text-sm font-bold">{{ __('otentikasi.daftar.alamat') }} *</label>
                        <input id="alamat" name="alamat" type="text" required maxlength="500" value="{{ old('alamat') }}" class="{{ $kolom }}">
                    </div>
                </div>
            </fieldset>

            {{-- Kampus --}}
            <fieldset class="space-y-4 border-t-2 border-ink pt-6">
                <legend class="font-display text-lg">
                    {{ __('otentikasi.daftar.bagian_kampus') }}
                    <span class="text-sm font-normal text-muted" x-show="jalur === 'alumni'" x-cloak>({{ __('otentikasi.daftar.opsional') }})</span>
                </legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="nim" class="block text-sm font-bold">{{ __('otentikasi.daftar.nim') }}</label>
                        <input id="nim" name="nim" type="text" maxlength="40" value="{{ old('nim') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="angkatan" class="block text-sm font-bold">{{ __('otentikasi.daftar.angkatan') }}</label>
                        <input id="angkatan" name="angkatan" type="number" min="2000" max="{{ date('Y') }}" value="{{ old('angkatan') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="fakultas" class="block text-sm font-bold">{{ __('otentikasi.daftar.fakultas') }}</label>
                        <input id="fakultas" name="fakultas" type="text" maxlength="160" value="{{ old('fakultas') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="program_studi" class="block text-sm font-bold">{{ __('otentikasi.daftar.prodi') }}</label>
                        <input id="program_studi" name="program_studi" type="text" maxlength="160" value="{{ old('program_studi') }}" class="{{ $kolom }}">
                    </div>
                </div>
            </fieldset>

            {{-- Alumni --}}
            <fieldset class="space-y-4 border-t-2 border-ink pt-6" x-show="jalur === 'alumni'" x-cloak>
                <legend class="font-display text-lg">{{ __('otentikasi.daftar.bagian_alumni') }}</legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tahun_lulus" class="block text-sm font-bold">{{ __('otentikasi.daftar.tahun_lulus') }}</label>
                        <input id="tahun_lulus" name="tahun_lulus" type="number" min="2000" max="{{ date('Y') }}" value="{{ old('tahun_lulus') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="instansi" class="block text-sm font-bold">{{ __('otentikasi.daftar.instansi') }}</label>
                        <input id="instansi" name="instansi" type="text" maxlength="190" value="{{ old('instansi') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="jabatan" class="block text-sm font-bold">{{ __('otentikasi.daftar.jabatan') }}</label>
                        <input id="jabatan" name="jabatan" type="text" maxlength="160" value="{{ old('jabatan') }}" class="{{ $kolom }}">
                    </div>
                    <div>
                        <label for="bidang" class="block text-sm font-bold">{{ __('otentikasi.daftar.bidang') }}</label>
                        <input id="bidang" name="bidang" type="text" maxlength="160" value="{{ old('bidang') }}" class="{{ $kolom }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="kota_domisili" class="block text-sm font-bold">{{ __('otentikasi.daftar.domisili') }}</label>
                        <input id="kota_domisili" name="kota_domisili" type="text" maxlength="120" value="{{ old('kota_domisili') }}" class="{{ $kolom }}">
                    </div>
                </div>
            </fieldset>

            {{-- Minat --}}
            <fieldset class="space-y-4 border-t-2 border-ink pt-6">
                <legend class="font-display text-lg">
                    {{ __('otentikasi.daftar.bagian_minat') }}
                    <span class="text-sm font-normal text-muted">({{ __('otentikasi.daftar.opsional') }})</span>
                </legend>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="unit_id" class="block text-sm font-bold">{{ __('otentikasi.daftar.unit') }}</label>
                        <select id="unit_id" name="unit_id" class="{{ $kolom }}">
                            <option value="">— {{ __('otentikasi.daftar.pilih') }} —</option>
                            @foreach ($unit->groupBy('jenis') as $daftar)
                                <optgroup label="{{ $daftar->first()->labelJenis() }}">
                                    @foreach ($daftar as $satuan)
                                        <option value="{{ $satuan->id }}" @selected((int) old('unit_id') === $satuan->id)>{{ $satuan->nama }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="keahlian" class="block text-sm font-bold">{{ __('otentikasi.daftar.keahlian') }}</label>
                        <input id="keahlian" name="keahlian" type="text" maxlength="500" placeholder="{{ __('otentikasi.daftar.keahlian_contoh') }}" value="{{ old('keahlian') }}" class="{{ $kolom }}">
                    </div>
                </div>
            </fieldset>

            <label class="flex items-start gap-3 border-t-2 border-ink pt-6 text-sm">
                <input type="checkbox" name="setuju" value="1" required @checked(old('setuju')) class="mt-0.5 h-5 w-5 border-2 border-ink">
                <span>{{ __('otentikasi.daftar.setuju') }}</span>
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <x-public.tombol-jamur type="submit">{{ __('otentikasi.daftar.tombol') }}</x-public.tombol-jamur>
                <a href="{{ route('login') }}" class="text-sm font-bold underline">{{ __('otentikasi.daftar.sudah_punya') }}</a>
            </div>
        </form>
    </div>
@endsection
