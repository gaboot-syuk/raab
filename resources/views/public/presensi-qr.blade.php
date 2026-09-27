<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Kode QR Presensi — {{ $kegiatan->kode }}</title>

    @vite(['resources/css/app.css'])

    {{--
        Halaman ini ditampilkan di proyektor atau dicetak untuk ditempel di
        pintu masuk, jadi menu dan footer situs sengaja tidak dipakai.
    --}}
    <style>
        @media print {
            .tanpa-cetak { display: none !important; }

            body { background: #fff !important; }

            html { color-scheme: light !important; }
        }
    </style>
</head>
<body class="bg-paper-alt p-4 sm:p-8">
    <div class="tanpa-cetak mx-auto mb-4 flex max-w-3xl flex-wrap gap-3">
        <button type="button" onclick="window.print()" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
            Cetak
        </button>

        <button type="button" onclick="window.close()" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">
            Tutup
        </button>
    </div>

    <div class="brutal mx-auto max-w-3xl bg-paper p-6 text-center">
        <p class="font-mono text-xs uppercase tracking-widest text-muted">{{ $kegiatan->kode }} · {{ $kegiatan->labelJenis() }}</p>
        <h1 class="mt-2 font-display text-2xl leading-tight">{{ $kegiatan->judulTeks() }}</h1>
        <p class="mt-1 text-sm text-muted">
            {{ $kegiatan->mulai?->translatedFormat('d F Y, H:i') }} WIB
            @if ($kegiatan->lokasi)
                · {{ $kegiatan->lokasi }}
            @endif
        </p>

        @if (! $tautan)
            {{-- Jujur ke panitia: kode tidak ada karena presensinya belum
                 dibuka atau sudah ditutup, bukan karena halaman ini rusak. --}}
            <div class="mt-8 border-4 border-ink bg-accent-400 p-6">
                <p class="font-display text-xl uppercase">Belum Ada Kode Aktif</p>
                <p class="mt-2 text-sm">
                    Kode QR hanya tersedia saat presensi sedang <strong>dibuka</strong>.
                    Buka presensinya lebih dulu dari halaman Kegiatan.
                </p>
            </div>
        @else
            <div id="qr" class="mx-auto mt-6 flex h-72 w-72 items-center justify-center border-4 border-ink bg-paper p-2"></div>

            <p class="mt-4 font-display text-lg">Pindai kode ini untuk mencatat kehadiranmu</p>
            <p class="mt-1 text-xs text-muted">
                Satu kode hanya mencatat satu kehadiran per orang. Kalau tautan ini tersebar ke luar,
                putar kode baru dari halaman Kegiatan — kode lama langsung tidak berlaku.
            </p>

            <p class="mt-4 text-[10px] font-bold uppercase text-muted">Tautan cadangan</p>
            <p class="break-all font-mono text-[10px]">{{ $tautan }}</p>

            @if ($kegiatan->qr_berlaku_sampai)
                <p class="mt-3 text-xs text-muted">
                    Berlaku sampai {{ $kegiatan->qr_berlaku_sampai->translatedFormat('d F Y, H:i') }} WIB.
                </p>
            @endif
        @endif
    </div>

    @if ($tautan)
        <script src="https://unpkg.com/qrcode-generator@1.4.4/qrcode.js" defer></script>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                var wadah = document.getElementById('qr');

                if (!wadah || typeof qrcode !== 'function') {
                    return;
                }

                try {
                    var qr = qrcode(0, 'M');
                    qr.addData(@json($tautan));
                    qr.make();
                    wadah.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 0 });
                } catch (e) {
                    // Biarkan kosong — tautan teks di bawahnya tetap bisa dipakai.
                }
            });
        </script>
    @endif
</body>
</html>
