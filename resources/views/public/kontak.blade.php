@extends('layouts.public')

@section('judul', __('umum.kontak.judul'))
@section('deskripsi', __('umum.kontak.deskripsi'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('umum.kontak.judul')"
        :deskripsi="__('umum.kontak.intro')"
        :remah="[__('umum.menu.layanan'), __('umum.menu.kontak')]"
    />

    <div class="mx-auto max-w-5xl px-4 py-10 lg:px-6">
        {{-- Pesan berhasil dikirim --}}
        @if (session('sukses'))
            <div role="status" class="brutal mb-8 border-ink bg-accent-400 px-4 py-3 font-bold text-primary-800">
                {{ session('sukses') }}
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[1fr_1.4fr]">
            {{-- ===== Informasi sekretariat ===== --}}
            <aside class="brutal h-fit bg-brand-dark p-5 text-on-brand">
                <h2 class="font-display text-lg">{{ __('umum.kontak.info_judul') }}</h2>

                <ul class="mt-5 space-y-4 text-sm">
                    <li>
                        <p class="text-xs font-bold uppercase tracking-wide text-on-brand/60">{{ __('umum.lokasi.alamat') }}</p>
                        <p class="mt-1 leading-relaxed">
                            {{ ($situs['alamat'] ?? '') !== '' ? $situs['alamat'] : __('umum.umum.alamat_placeholder') }}
                        </p>
                    </li>
                    <li>
                        <p class="text-xs font-bold uppercase tracking-wide text-on-brand/60">{{ __('umum.lokasi.jam') }}</p>
                        <p class="mt-1">
                            {{ ($situs['jam_operasional'] ?? '') !== '' ? $situs['jam_operasional'] : __('umum.umum.jam_placeholder') }}
                        </p>
                    </li>
                    <li>
                        <p class="text-xs font-bold uppercase tracking-wide text-on-brand/60">Email</p>
                        <a href="mailto:{{ $situs['email'] ?? '' }}" class="mt-1 block font-bold underline">
                            {{ $situs['email'] ?? '' }}
                        </a>
                    </li>
                    <li>
                        <p class="text-xs font-bold uppercase tracking-wide text-on-brand/60">Telepon / WhatsApp</p>
                        @php
                            // Nomor placeholder (mengandung "xx") sengaja tidak dijadikan tautan
                            // agar pengunjung tidak terhubung ke nomor yang salah.
                            $nomor = (string) ($situs['whatsapp'] ?? $situs['telepon'] ?? '');
                            $nomorSah = $nomor !== '' && ! str_contains($nomor, 'xx');
                        @endphp
                        @if ($nomorSah)
                            <a
                                href="https://wa.me/{{ preg_replace('/\D/', '', preg_replace('/^0/', '62', $nomor)) }}"
                                target="_blank" rel="noopener noreferrer"
                                class="mt-1 block font-bold underline"
                            >{{ $nomor }}</a>
                        @else
                            <p class="mt-1">{{ $nomor !== '' ? $nomor : __('umum.umum.segera') }}</p>
                        @endif
                    </li>
                </ul>

                <p class="mt-6 border-t-2 border-on-brand/25 pt-4 text-xs text-on-brand/70">
                    {{ __('umum.kontak.balasan') }}
                </p>

                @if ($sosmed->isNotEmpty())
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($sosmed as $tautan)
                            @if ($tautan->url)
                                <a
                                    href="{{ $tautan->url }}" target="_blank" rel="noopener noreferrer"
                                    class="border-2 border-on-brand/50 px-2 py-1 text-xs font-bold hover:border-accent-400 hover:text-accent-400"
                                >{{ $tautan->label }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </aside>

            {{-- ===== Formulir kontak ===== --}}
            <section class="brutal bg-paper p-5 lg:p-6">
                <h2 class="font-display text-lg">{{ __('umum.kontak.form_judul') }}</h2>
                <p class="mt-1 text-sm text-muted">{{ __('umum.kontak.balasan') }}</p>

                {{-- Kesalahan validasi --}}
                @if ($errors->any())
                    <div role="alert" class="brutal-sm mt-5 border-ink bg-accent-100 px-3 py-2 text-sm font-semibold">
                        <p class="font-bold">{{ __('umum.umum.periksa_kembali') }}</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($errors->all() as $galat)
                                <li>{{ $galat }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('public.kontak.kirim') }}" class="mt-5 space-y-4">                    @csrf

                    {{-- Honeypot: disembunyikan dari manusia, hanya bot yang mengisi --}}
                    <div class="absolute left-[-9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="nama" class="block text-sm font-bold">{{ __('umum.kontak.nama') }} <span aria-hidden="true">*</span></label>
                            <input
                                type="text" id="nama" name="nama" required maxlength="120"
                                value="{{ old('nama') }}"
                                class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                            >
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-bold">{{ __('umum.kontak.email') }} <span aria-hidden="true">*</span></label>
                            <input
                                type="email" id="email" name="email" required maxlength="190"
                                value="{{ old('email') }}"
                                class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                            >
                        </div>
                        <div>
                            <label for="telepon" class="block text-sm font-bold">{{ __('umum.kontak.telepon') }}</label>
                            <input
                                type="text" id="telepon" name="telepon" maxlength="40"
                                value="{{ old('telepon') }}"
                                class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                            >
                            <p class="mt-1 text-xs text-muted">{{ __('umum.kontak.telepon_bantuan') }}</p>
                        </div>
                        <div>
                            <label for="asal" class="block text-sm font-bold">{{ __('umum.kontak.asal') }}</label>
                            <input
                                type="text" id="asal" name="asal" maxlength="190"
                                value="{{ old('asal') }}"
                                class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="jenis" class="block text-sm font-bold">{{ __('umum.kontak.jenis') }} <span aria-hidden="true">*</span></label>
                        <select
                            id="jenis" name="jenis" required
                            class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                        >
                            @foreach ($jenis as $nilai => $label)
                                <option value="{{ $nilai }}" @selected(old('jenis', 'umum') === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="subjek" class="block text-sm font-bold">{{ __('umum.kontak.subjek') }} <span aria-hidden="true">*</span></label>
                        <input
                            type="text" id="subjek" name="subjek" required maxlength="190"
                            value="{{ old('subjek') }}"
                            class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                        >
                    </div>

                    <div>
                        <label for="pesan" class="block text-sm font-bold">{{ __('umum.kontak.pesan') }} <span aria-hidden="true">*</span></label>
                        <textarea
                            id="pesan" name="pesan" required rows="7" maxlength="5000"
                            class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                        >{{ old('pesan') }}</textarea>
                        <p class="mt-1 text-xs text-muted">{{ __('umum.kontak.pesan_bantuan') }}</p>
                    </div>

                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="setuju" value="1" required @checked(old('setuju')) class="mt-1 h-5 w-5 border-2 border-ink">
                        <span>{{ __('umum.kontak.setuju') }}</span>
                    </label>

                    <x-public.captcha />

                    <x-public.tombol-jamur type="submit">
                        {{ __('umum.tombol.kirim') }}
                    </x-public.tombol-jamur>
                </form>
            </section>
        </div>
    </div>
@endsection
