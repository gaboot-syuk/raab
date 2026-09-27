@extends('layouts.public')

@php
    $judul = $event->getTranslation('judul', app()->getLocale(), false) ?: $event->getTranslation('judul', 'id');
    $deskripsi = $event->getTranslation('deskripsi', app()->getLocale(), false) ?: $event->getTranslation('deskripsi', 'id', false);
    $syarat = $event->getTranslation('syarat', app()->getLocale(), false) ?: $event->getTranslation('syarat', 'id', false);

    // Hitung mundur mengarah ke peristiwa berikutnya: pembukaan bila belum
    // dibuka, penutupan bila sudah. Bila tidak ada tanggal, tidak ditampilkan.
    $target = null;
    if ($keadaan['dibuka'] && $event->pendaftaran_ditutup) {
        $target = $event->pendaftaran_ditutup;
    } elseif (! $keadaan['dibuka'] && $event->pendaftaran_dibuka?->isFuture()) {
        $target = $event->pendaftaran_dibuka;
    }
@endphp

@section('judul', $judul)
@section('deskripsi', Str::limit(strip_tags((string) $deskripsi), 150))

@section('konten')
    <x-public.judul-halaman
        :judul="$judul"
        :deskripsi="$event->labelJenis()"
        :remah="[__('umum.menu.layanan'), __('pendaftaran.judul'), $judul]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <div class="grid gap-8 lg:grid-cols-3">
            {{-- Kolom kiri: informasi kegiatan --}}
            <div class="space-y-6 lg:col-span-2">
                @if ($event->poster_media_id && $poster = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($event->poster_media_id))
                    <img src="{{ $poster->hasGeneratedConversion('sedang') ? $poster->getUrl('sedang') : $poster->getUrl() }}"
                         alt="{{ $judul }}" class="brutal w-full object-cover">
                @endif

                @if (filled($deskripsi))
                    <div class="brutal prose-public bg-paper p-6">
                        {!! $deskripsi !!}
                    </div>
                @endif

                @if (filled($syarat))
                    <section class="brutal bg-paper p-6">
                        <h2 class="font-display text-lg">{{ __('pendaftaran.info.syarat') }}</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach (preg_split('/\r\n|\r|\n/', trim(strip_tags((string) $syarat))) as $butir)
                                @continue (trim($butir) === '')
                                <li class="flex gap-2">
                                    <span aria-hidden="true">▪</span>
                                    <span>{{ $butir }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($kegiatanLain->isNotEmpty())
                    <section class="brutal bg-paper p-6">
                        <h2 class="font-display text-lg">{{ __('pendaftaran.info.kegiatan_lain') }}</h2>
                        <ul class="mt-3 space-y-2">
                            @foreach ($kegiatanLain as $lain)
                                <li>
                                    <a href="{{ route('public.pendaftaran.detail', $lain->getTranslation('slug', 'id')) }}" class="text-sm font-bold hover:underline">
                                        {{ $lain->getTranslation('judul', 'id') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            {{-- Kolom kanan: ringkasan + hitung mundur --}}
            <aside class="space-y-4 lg:col-span-1">
                <div class="brutal bg-paper p-5">
                    <dl class="space-y-3 text-sm">
                        @if ($event->mulai)
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.info.jadwal') }}</dt>
                                <dd>
                                    {{ $event->mulai->translatedFormat('d F Y') }}
                                    @if ($event->selesai && ! $event->selesai->equalTo($event->mulai))
                                        – {{ $event->selesai->translatedFormat('d F Y') }}
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if ($event->lokasi)
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.info.lokasi') }}</dt>
                                <dd>{{ $event->lokasi }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.info.biaya') }}</dt>
                            <dd>{{ $event->biaya > 0 ? 'Rp '.number_format($event->biaya, 0, ',', '.') : __('pendaftaran.info.gratis') }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.kuota.terisi') }}</dt>
                            <dd>
                                {{ $keadaan['terisi'] }}
                                @if ($keadaan['kuota'] !== null)
                                    / {{ $keadaan['kuota'] }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Hitung mundur. Nilai awal dirender di server supaya tetap
                     terbaca bila JavaScript belum jalan. --}}
                @if ($target)
                    <div
                        class="brutal bg-accent-400 p-5 text-primary-800"
                        x-data="{
                            target: {{ $target->getTimestamp() * 1000 }},
                            sisa: {{ max(0, $target->getTimestamp() - now()->getTimestamp()) }},
                            get hari() { return Math.floor(this.sisa / 86400); },
                            get jam() { return Math.floor((this.sisa % 86400) / 3600); },
                            get menit() { return Math.floor((this.sisa % 3600) / 60); },
                            get detik() { return this.sisa % 60; },
                            init() {
                                const tik = () => { this.sisa = Math.max(0, Math.floor((this.target - Date.now()) / 1000)); };
                                tik();
                                setInterval(tik, 1000);
                            }
                        }"
                    >
                        <p class="text-xs font-bold uppercase" x-text="sisa > 0 ? '{{ $keadaan['dibuka'] ? __('pendaftaran.hitung.ditutup_dalam') : __('pendaftaran.hitung.dibuka_dalam') }}' : '{{ __('pendaftaran.hitung.berakhir') }}'">
                            {{ $keadaan['dibuka'] ? __('pendaftaran.hitung.ditutup_dalam') : __('pendaftaran.hitung.dibuka_dalam') }}
                        </p>

                        <div class="mt-2 flex gap-2" x-show="sisa > 0">
                            <div class="flex-1 border-2 border-primary-800 bg-on-brand p-2 text-center">
                                <p class="font-display text-xl" x-text="hari">0</p>
                                <p class="text-[10px] uppercase">{{ __('pendaftaran.hitung.hari') }}</p>
                            </div>
                            <div class="flex-1 border-2 border-primary-800 bg-on-brand p-2 text-center">
                                <p class="font-display text-xl" x-text="jam">0</p>
                                <p class="text-[10px] uppercase">{{ __('pendaftaran.hitung.jam') }}</p>
                            </div>
                            <div class="flex-1 border-2 border-primary-800 bg-on-brand p-2 text-center">
                                <p class="font-display text-xl" x-text="menit">0</p>
                                <p class="text-[10px] uppercase">{{ __('pendaftaran.hitung.menit') }}</p>
                            </div>
                            <div class="flex-1 border-2 border-primary-800 bg-on-brand p-2 text-center">
                                <p class="font-display text-xl" x-text="detik">0</p>
                                <p class="text-[10px] uppercase">{{ __('pendaftaran.hitung.detik') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @unless ($keadaan['dibuka'])
                    <div class="brutal bg-paper-alt p-5">
                        <p class="font-display text-base">{{ __('pendaftaran.keadaan.ditutup') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $keadaan['alasan'] }}</p>
                    </div>
                @endunless
            </aside>
        </div>

        {{-- ======================= Formulir Pendaftaran ======================= --}}
        <section id="formulir" class="mt-12">
            @if (session('galat'))
                <p class="brutal mb-4 bg-paper-alt p-4 text-sm font-bold text-accent-600">{{ session('galat') }}</p>
            @endif

            @if (! $keadaan['dibuka'])
                <div class="brutal bg-paper-alt p-8 text-center">
                    <p class="font-display text-lg">{{ __('pendaftaran.keadaan.ditutup') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ $keadaan['alasan'] }}</p>

                    <a href="{{ route('public.pendaftaran.status') }}" class="brutal-sm brutal-hover mt-4 inline-block bg-paper px-4 py-2 text-sm font-bold">
                        {{ __('pendaftaran.cek.judul') }}
                    </a>
                </div>
            @else
                <div class="brutal bg-paper p-6 sm:p-8">
                    <h2 class="font-display text-xl">{{ __('pendaftaran.form.judul') }}</h2>

                    @if ($errors->any())
                        <div class="brutal-sm mt-4 bg-accent-100 p-4 text-sm">
                            <p class="font-bold">{{ __('umum.direktori.ditemukan') }} {{ $errors->count() }} isian yang perlu diperbaiki:</p>
                            <ul class="mt-2 list-inside list-disc">
                                @foreach ($errors->all() as $galat)
                                    <li>{{ $galat }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('public.pendaftaran.kirim', $event->id) }}" class="mt-6 space-y-6">
                        @csrf

                        {{-- Honeypot: tersembunyi dari manusia, sering diisi bot. --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="tautan_web">Tautan web</label>
                            <input id="tautan_web" name="tautan_web" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        <fieldset>
                            <legend class="font-display text-base">{{ __('pendaftaran.form.nama_lengkap') }}</legend>

                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <label class="block sm:col-span-2">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.nama_lengkap') }} *</span>
                                    <input name="nama_lengkap" type="text" value="{{ old('nama_lengkap') }}" required maxlength="160"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                    @error('nama_lengkap') <span class="mt-1 block text-xs font-bold text-accent-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.email') }} *</span>
                                    <input name="email" type="email" value="{{ old('email') }}" required maxlength="190"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                    <span class="mt-1 block text-xs text-muted">{{ __('pendaftaran.form.email_bantuan') }}</span>
                                    @error('email') <span class="mt-1 block text-xs font-bold text-accent-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.telepon') }}</span>
                                    <input name="telepon" type="tel" value="{{ old('telepon') }}" maxlength="40"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                    @error('telepon') <span class="mt-1 block text-xs font-bold text-accent-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.jenis_kelamin') }}</span>
                                    <select name="jenis_kelamin" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                        <option value="">—</option>
                                        <option value="laki_laki" @selected(old('jenis_kelamin') === 'laki_laki')>{{ __('pendaftaran.form.laki_laki') }}</option>
                                        <option value="perempuan" @selected(old('jenis_kelamin') === 'perempuan')>{{ __('pendaftaran.form.perempuan') }}</option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.tempat_lahir') }}</span>
                                    <input name="tempat_lahir" type="text" value="{{ old('tempat_lahir') }}" maxlength="120"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.tanggal_lahir') }}</span>
                                    <input name="tanggal_lahir" type="date" value="{{ old('tanggal_lahir') }}" max="{{ now()->subDay()->format('Y-m-d') }}"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                    @error('tanggal_lahir') <span class="mt-1 block text-xs font-bold text-accent-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.nim') }}</span>
                                    <input name="nim" type="text" value="{{ old('nim') }}" maxlength="40"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                </label>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="font-display text-base">{{ __('pendaftaran.form.instansi') }}</legend>

                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.instansi') }}</span>
                                    <input name="instansi" type="text" value="{{ old('instansi') }}" maxlength="190"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.fakultas') }}</span>
                                    <input name="fakultas" type="text" value="{{ old('fakultas') }}" maxlength="160"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.program_studi') }}</span>
                                    <input name="program_studi" type="text" value="{{ old('program_studi') }}" maxlength="160"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.angkatan') }}</span>
                                    <input name="angkatan" type="number" value="{{ old('angkatan') }}" min="1990" max="{{ now()->addYear()->format('Y') }}"
                                           class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">
                                </label>

                                <label class="block sm:col-span-2">
                                    <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.alamat') }}</span>
                                    <textarea name="alamat" rows="2" maxlength="1000" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">{{ old('alamat') }}</textarea>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Kolom tambahan buatan panitia --}}
                        @if ($kolom->isNotEmpty())
                            <fieldset>
                                <legend class="font-display text-base">Informasi Tambahan</legend>

                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    @foreach ($kolom as $satuKolom)
                                        @php
                                            $nama = 'jawaban['.$satuKolom->id.']';
                                            $lama = old($nama);
                                            $label = $satuKolom->labelTeks(app()->getLocale());
                                            $wajib = $satuKolom->wajib;
                                            $kelas = 'brutal-sm mt-1 w-full bg-paper-alt px-3 py-2';
                                            $lebar = in_array($satuKolom->tipe, [\App\Models\EventField::TIPE_AREA], true) ? 'sm:col-span-2' : '';
                                        @endphp

                                        <label class="block {{ $lebar }}">
                                            <span class="text-xs font-bold uppercase text-muted">
                                                {{ $label }}
                                                @if ($wajib) * @else <span class="font-normal normal-case">({{ __('pendaftaran.form.opsional') }})</span> @endif
                                            </span>

                                            @switch($satuKolom->tipe)
                                                @case(\App\Models\EventField::TIPE_AREA)
                                                    <textarea name="{{ $nama }}" rows="3" @if($wajib) required @endif class="{{ $kelas }}">{{ $lama }}</textarea>
                                                    @break

                                                @case(\App\Models\EventField::TIPE_ANGKA)
                                                    <input name="{{ $nama }}" type="number" step="any" value="{{ $lama }}" @if($wajib) required @endif class="{{ $kelas }}">
                                                    @break

                                                @case(\App\Models\EventField::TIPE_TANGGAL)
                                                    <input name="{{ $nama }}" type="date" value="{{ $lama }}" @if($wajib) required @endif class="{{ $kelas }}">
                                                    @break

                                                @case(\App\Models\EventField::TIPE_PILIHAN)
                                                    <select name="{{ $nama }}" @if($wajib) required @endif class="{{ $kelas }}">
                                                        <option value="">—</option>
                                                        @foreach ($satuKolom->pilihanUntuk(app()->getLocale()) as $pilihan)
                                                            <option value="{{ $pilihan }}" @selected((string) $lama === (string) $pilihan)>{{ $pilihan }}</option>
                                                        @endforeach
                                                    </select>
                                                    @break

                                                @case(\App\Models\EventField::TIPE_CENTANG)
                                                    <span class="mt-1 flex items-center gap-2 text-sm">
                                                        <input name="{{ $nama }}" type="checkbox" value="1" @checked($lama) class="h-4 w-4 border-2 border-ink">
                                                        {{ $label }}
                                                    </span>
                                                    @break

                                                @default
                                                    <input name="{{ $nama }}" type="text" value="{{ $lama }}" maxlength="255" @if($wajib) required @endif class="{{ $kelas }}">
                                            @endswitch

                                            @error($nama) <span class="mt-1 block text-xs font-bold text-accent-600">{{ $message }}</span> @enderror
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.catatan') }}</span>
                            <textarea name="catatan_peserta" rows="2" maxlength="1000" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2">{{ old('catatan_peserta') }}</textarea>
                        </label>

                        <label class="flex items-start gap-2 text-sm">
                            <input name="setuju" type="checkbox" value="1" @checked(old('setuju')) required class="mt-0.5 h-4 w-4 border-2 border-ink">
                            <span>{{ __('pendaftaran.form.setuju') }}</span>
                        </label>
                        @error('setuju') <span class="block text-xs font-bold text-accent-600">{{ $message }}</span> @enderror

                        <x-public.captcha />

                        <div class="flex flex-wrap items-center gap-4">
                            <button type="submit" class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800">
                                {{ __('pendaftaran.form.kirim') }}
                            </button>

                            <a href="{{ route('public.pendaftaran.status') }}" class="text-sm font-bold underline">
                                {{ __('pendaftaran.form.sudah_daftar') }}
                            </a>
                        </div>
                    </form>
                </div>
            @endif
        </section>
    </div>
@endsection
