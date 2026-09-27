@extends('layouts.public')

@section('judul', __('aspirasi.judul'))
@section('deskripsi', __('aspirasi.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('aspirasi.judul')"
        :deskripsi="__('aspirasi.intro')"
        :remah="[__('umum.menu.aspirasi')]"
    />

    <div class="mx-auto max-w-5xl px-4 py-10 lg:px-6">
        @if (session('sukses'))
            <div class="brutal mb-6 bg-accent-100 p-4" role="status">
                <p class="text-sm font-bold">{{ session('sukses') }}</p>

                @if ($punyaTokenBaru)
                    {{--
                        Token ditampilkan SEKALI di sini dan tidak ditulis di URL:
                        tautan berisi token akan tersimpan di riwayat peramban
                        dan log server, dan siapa pun yang memegangnya bisa
                        membaca isi surat orang lain.
                    --}}
                    <p class="mt-3 font-display text-base">{{ __('aspirasi.form.token_judul') }}</p>
                    <p class="text-xs leading-relaxed">{{ __('aspirasi.form.token_teks') }}</p>

                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div class="brutal-sm bg-paper p-3">
                            <dt class="text-[10px] font-bold uppercase text-muted">{{ __('aspirasi.form.nomor_tiket') }}</dt>
                            <dd class="font-mono text-sm">{{ $nomorTiketBaru }}</dd>
                        </div>
                        <div class="brutal-sm bg-paper p-3">
                            <dt class="text-[10px] font-bold uppercase text-muted">{{ __('aspirasi.form.token') }}</dt>
                            <dd class="break-all font-mono text-[11px]">{{ $punyaTokenBaru }}</dd>
                        </div>
                    </dl>
                @endif
            </div>
        @endif

        @if (session('galat'))
            <div class="brutal mb-6 bg-accent-400 p-4 text-sm font-bold" role="alert">{{ session('galat') }}</div>
        @endif

        @if ($errors->any())
            <div class="brutal mb-6 bg-accent-400 p-4" role="alert">
                <p class="text-sm font-bold">{{ __('aspirasi.galat_ringkas') }}</p>
                <ul class="mt-2 list-inside list-disc text-xs">
                    @foreach ($errors->all() as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- ===== Papan aspirasi ===== -->
        <section>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl">{{ __('aspirasi.papan.judul') }}</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-relaxed text-muted">{{ __('aspirasi.papan.teks') }}</p>
                </div>

                <ul class="flex flex-wrap gap-2 text-xs">
                    <li class="brutal-sm bg-paper-alt px-3 py-1.5">
                        {{ __('aspirasi.papan.total') }}: <span class="font-bold">{{ $ringkasan['total'] }}</span>
                    </li>
                    <li class="brutal-sm bg-paper-alt px-3 py-1.5">
                        {{ __('aspirasi.papan.selesai') }}: <span class="font-bold">{{ $ringkasan['selesai'] }}</span>
                    </li>
                    <li class="brutal-sm bg-paper-alt px-3 py-1.5">
                        {{ __('aspirasi.papan.diproses') }}: <span class="font-bold">{{ $ringkasan['diproses'] }}</span>
                    </li>
                </ul>
            </div>

            <form method="GET" action="{{ url('/aspirasi') }}" class="mt-4 flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.papan.kategori') }}</span>
                    <select name="kategori" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                        <option value="">{{ __('aspirasi.papan.semua_kategori') }}</option>
                        @foreach ($pilihanKategori as $kunci => $label)
                            <option value="{{ $kunci }}" @selected($saringan === $kunci)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    {{ __('aspirasi.papan.saring') }}
                </button>
            </form>

            @if (empty($papan))
                <div class="brutal mt-4 bg-paper p-6">
                    <p class="font-display text-lg">{{ __('aspirasi.papan.kosong') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('aspirasi.papan.kosong_teks') }}</p>
                </div>
            @else
                <ul class="mt-4 space-y-4">
                    @foreach ($papan as $item)
                        <li class="brutal bg-paper p-5">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                                {{ $item['kategori'] }} · {{ $item['tanggal'] }}
                            </p>

                            <h3 class="mt-1 font-display text-lg leading-tight">{{ $item['judul'] }}</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ $item['isi'] }}</p>

                            <div class="mt-3 border-t-2 border-ink/10 pt-3">
                                <p class="text-[10px] font-bold uppercase text-muted">{{ __('aspirasi.papan.tanggapan') }}</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ $item['tanggapan'] }}</p>
                                <p class="mt-2 text-xs">
                                    <span class="border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase">{{ $item['label_status'] }}</span>
                                    <span class="ml-2 text-muted">{{ $item['ditanggapi_pada'] }}</span>
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <!-- ===== Formulir ===== -->
        <section class="mt-10" id="kirim">
            <h2 class="font-display text-xl">{{ __('aspirasi.form.judul') }}</h2>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-muted">{{ __('aspirasi.form.teks') }}</p>

            <form method="POST" action="{{ url('/aspirasi') }}" class="brutal mt-4 bg-paper p-5">
                @csrf

                {{-- Honeypot: tidak pernah diisi manusia, disembunyikan dari pembaca layar. --}}
                <div class="hidden" aria-hidden="true">
                    <label>Situs web
                        <input type="text" name="situs_web" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.form.nama') }}</span>
                        <input type="text" name="nama_pengirim" value="{{ old('nama_pengirim', auth()->user()?->name) }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.form.email') }}</span>
                        <input type="email" name="email_pengirim" value="{{ old('email_pengirim', auth()->user()?->email) }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.form.telepon') }}</span>
                        <input type="text" name="telepon_pengirim" value="{{ old('telepon_pengirim') }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.form.kategori') }}</span>
                        <select name="kategori" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            @foreach ($pilihanKategori as $kunci => $label)
                                <option value="{{ $kunci }}" @selected(old('kategori') === $kunci)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.form.judul_aspirasi') }}</span>
                        <input type="text" name="judul" value="{{ old('judul') }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.form.isi') }}</span>
                        <textarea name="isi" rows="6" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">{{ old('isi') }}</textarea>
                        <span class="text-[11px] leading-relaxed text-muted">{{ __('aspirasi.form.isi_bantuan') }} {{ __('aspirasi.form.min_isi', ['jumlah' => $minIsi]) }}</span>
                    </label>

                    <label class="flex items-center gap-2 sm:col-span-2">
                        <input type="checkbox" name="tampil_publik" value="1" class="h-4 w-4 border-2 border-ink" @checked($errors->any() ? old('tampil_publik') : true)>
                        <span class="text-sm">{{ __('aspirasi.form.tampil_publik') }}</span>
                    </label>
                </div>

                <x-public.captcha />

                <button type="submit" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    {{ __('aspirasi.form.kirim') }}
                </button>
            </form>
        </section>

        <!-- ===== Pelacakan ===== -->
        <section class="mt-10" id="lacak">
            <h2 class="font-display text-xl">{{ __('aspirasi.lacak.judul') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ __('aspirasi.lacak.teks') }}</p>

            @if ($pesanLacak)
                <div class="brutal mt-3 bg-accent-400 p-4 text-sm font-bold" role="alert">{{ $pesanLacak }}</div>
            @endif

            @if ($hasilLacak)
                <div class="brutal mt-3 bg-paper p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                        {{ $hasilLacak['nomor_tiket'] }} · {{ $hasilLacak['kategori'] }}
                    </p>
                    <h3 class="mt-1 font-display text-lg leading-tight">{{ $hasilLacak['judul'] }}</h3>

                    <p class="mt-2 text-xs">
                        <span class="border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase">{{ $hasilLacak['status'] }}</span>
                        <span class="ml-2 text-muted">{{ __('aspirasi.lacak.dikirim') }}: {{ $hasilLacak['dibuat'] }}</span>
                        @if ($hasilLacak['ditanggapi_pada'])
                            <span class="ml-2 text-muted">{{ __('aspirasi.lacak.ditanggapi') }}: {{ $hasilLacak['ditanggapi_pada'] }}</span>
                        @endif
                    </p>

                    <p class="mt-3 text-[10px] font-bold uppercase text-muted">{{ __('aspirasi.lacak.isi_kamu') }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ $hasilLacak['isi'] }}</p>

                    <p class="mt-3 text-[10px] font-bold uppercase text-muted">{{ __('aspirasi.papan.tanggapan') }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">
                        {{ $hasilLacak['tanggapan'] ?: __('aspirasi.lacak.belum_ditanggapi') }}
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ url('/aspirasi/lacak') }}" class="brutal mt-3 bg-paper p-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.lacak.nomor_tiket') }}</span>
                        <input type="text" name="nomor_tiket" value="{{ old('nomor_tiket') }}" placeholder="ASP-2026-0001" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">{{ __('aspirasi.lacak.token') }}</span>
                        <input type="text" name="token_lacak" value="{{ old('token_lacak') }}" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>
                </div>

                <button type="submit" class="brutal-sm brutal-hover mt-4 bg-paper-alt px-4 py-2 text-sm font-bold">
                    {{ __('aspirasi.lacak.tombol') }}
                </button>
            </form>
        </section>
    </div>
@endsection
