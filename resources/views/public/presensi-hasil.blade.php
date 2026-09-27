<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $berhasil ? 'Kehadiran Tercatat' : 'Presensi Gagal' }} — PMII RAAB</title>

    @vite(['resources/css/app.css'])
</head>
<body class="bg-paper-alt p-4 sm:p-8">
    <div class="brutal mx-auto max-w-lg bg-paper p-6">
        {{--
            Satu jawaban, di atas, sebesar mungkin. Halaman ini dibuka sambil
            berdiri di pintu masuk dengan ponsel di tangan.
        --}}
        <div class="border-4 border-ink {{ $berhasil ? 'bg-accent-100' : 'bg-accent-400' }} p-5 text-center">
            <p class="font-display text-3xl uppercase">{{ $berhasil ? 'Tercatat' : 'Tidak Tercatat' }}</p>
        </div>

        <p class="mt-4 text-center text-sm leading-relaxed">{{ $pesan }}</p>

        @if ($kegiatan)
            <div class="mt-6 border-t-4 border-ink pt-4">
                <p class="font-mono text-xs uppercase tracking-widest text-muted">{{ $kegiatan->kode }}</p>
                <p class="mt-1 font-display text-lg leading-tight">{{ $kegiatan->judulTeks() }}</p>
                <p class="mt-1 text-sm text-muted">
                    {{ $kegiatan->mulai?->translatedFormat('d F Y, H:i') }} WIB
                    @if ($kegiatan->lokasi)
                        · {{ $kegiatan->lokasi }}
                    @endif
                </p>

                @if ($labelStatus)
                    <p class="mt-3 text-sm">
                        Status kehadiranmu: <strong>{{ $labelStatus }}</strong>
                    </p>
                @endif
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ url('/kegiatan') }}" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                Lihat Kegiatan Saya
            </a>

            <a href="{{ url('/dasbor') }}" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                Dasbor
            </a>
        </div>

        <p class="mt-4 text-xs leading-relaxed text-muted">
            Kehadiran yang sudah tercatat tidak dapat dihapus sendiri. Bila ada kekeliruan,
            hubungi panitia kegiatan atau Sekretaris rayon.
        </p>
    </div>
</body>
</html>
