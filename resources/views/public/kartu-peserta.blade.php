<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('pendaftaran.kartu.judul') }} — {{ $peserta->kode_pendaftaran }}</title>

    @vite(['resources/css/app.css'])

    {{--
        Halaman kartu sengaja BERDIRI SENDIRI (tidak memakai layout publik):
        tidak ada menu, footer, atau tombol yang ikut tercetak. Yang tercetak
        hanya kartunya.
    --}}
    <style>
        @media print {
            .tanpa-cetak { display: none !important; }

            body { background: #fff !important; }

            .kartu {
                box-shadow: none !important;
                page-break-inside: avoid;
            }

            /* Mode gelap tidak dihormati saat mencetak. */
            html { color-scheme: light !important; }
        }
    </style>
</head>
<body class="bg-paper-alt p-4 sm:p-8">
    <div class="tanpa-cetak mx-auto mb-4 flex max-w-2xl flex-wrap gap-3">
        <button type="button" onclick="window.print()" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
            {{ __('pendaftaran.kartu.cetak') }}
        </button>

        <button type="button" onclick="window.close()" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">
            {{ __('pendaftaran.kartu.tutup') }}
        </button>
    </div>

    <div class="kartu brutal mx-auto max-w-2xl bg-paper p-6">
        <div class="flex items-start justify-between gap-4 border-b-4 border-ink pb-4">
            <div>
                <p class="font-display text-lg leading-tight">{{ $situs['nama_rayon'] ?? 'PMII Rayon Ali Ahmad Baktsir' }}</p>
                <p class="text-xs text-muted">{{ $situs['nama_komisariat'] ?? 'PMII Komisariat Raden Mas Said' }}</p>
            </div>

            <img src="{{ asset('brand/logo-pmii-raab.png') }}" alt="Logo" class="h-12 w-auto">
        </div>

        <p class="mt-4 text-center font-display text-xl uppercase">{{ __('pendaftaran.kartu.judul') }}</p>

        <p class="mt-1 text-center text-sm font-bold">
            {{ $peserta->event?->getTranslation('judul', 'id') }}
        </p>

        <dl class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.cek.kode') }}</dt>
                <dd class="font-display text-2xl tracking-widest">{{ $peserta->kode_pendaftaran }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.cek.nama') }}</dt>
                <dd class="font-display text-lg">{{ $peserta->nama_lengkap }}</dd>
            </div>

            @if ($peserta->instansi)
                <div>
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.instansi') }}</dt>
                    <dd>{{ $peserta->instansi }}</dd>
                </div>
            @endif

            @if ($peserta->program_studi)
                <div>
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.form.program_studi') }}</dt>
                    <dd>{{ $peserta->program_studi }}</dd>
                </div>
            @endif

            @if ($peserta->event?->mulai)
                <div>
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.info.jadwal') }}</dt>
                    <dd>{{ $peserta->event->mulai->translatedFormat('d F Y') }}</dd>
                </div>
            @endif

            @if ($peserta->event?->lokasi)
                <div>
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.info.lokasi') }}</dt>
                    <dd>{{ $peserta->event->lokasi }}</dd>
                </div>
            @endif

            <div>
                <dt class="text-xs font-bold uppercase text-muted">{{ __('pendaftaran.cek.status') }}</dt>
                <dd class="font-bold">{{ $peserta->labelStatus() }}</dd>
            </div>
        </dl>

        {{-- Jawaban kolom tambahan dicetak bila ada — sering dipakai panitia
             untuk hal praktis seperti ukuran kaos. --}}
        @if ($peserta->jawaban->isNotEmpty())
            <dl class="mt-6 grid gap-3 border-t-2 border-ink/15 pt-4 sm:grid-cols-2">
                @foreach ($peserta->jawaban as $jawaban)
                    @continue ($jawaban->kolom === null)
                    <div>
                        <dt class="text-xs font-bold uppercase text-muted">{{ $jawaban->kolom->labelTeks() }}</dt>
                        <dd>{{ $jawaban->nilai === '1' ? 'Ya' : ($jawaban->nilai === '0' ? 'Tidak' : $jawaban->nilai) }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <p class="mt-6 border-t-2 border-ink/15 pt-4 text-xs text-muted">
            {{ __('pendaftaran.kartu.catatan') }}
        </p>
    </div>
</body>
</html>
