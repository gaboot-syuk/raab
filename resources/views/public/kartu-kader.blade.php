<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('kartu.kartu.judul') }} — {{ $anggota?->nama_lengkap ?? __('umum.nama_singkat') }}</title>

    @vite(['resources/css/app.css'])

    {{--
        Halaman kartu berdiri sendiri (tanpa layout publik): yang tercetak
        hanya kartunya, bukan menu dan footer situs.
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
            {{ __('kartu.kartu.cetak') }}
        </button>

        <a href="{{ url('/dasbor') }}" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
            {{ __('umum.menu.dasbor') }}
        </a>
    </div>

    @if (! $kartu)
        <div class="kartu brutal mx-auto max-w-2xl bg-paper p-6 text-center">
            @if ($anggota && $anggota->status === \App\Models\Member::STATUS_ALUMNI)
                {{--
                    Alumni memang TIDAK memakai kartu kader: kartunya dicabut
                    saat statusnya berubah menjadi alumni. Tanpa cabang ini,
                    halaman memakai pesan "kartumu belum diterbitkan, hubungi
                    sekretariat" — dan alumni akan menghubungi sekretariat untuk
                    sesuatu yang memang tidak akan pernah diterbitkan.
                --}}
                <p class="font-display text-xl">{{ __('kartu.kartu.alumni_tanpa_kartu') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('kartu.kartu.alumni_tanpa_kartu_teks') }}</p>
            @else
                <p class="font-display text-xl">{{ __('kartu.kartu.belum_ada') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('kartu.kartu.belum_ada_teks') }}</p>
            @endif
        </div>
    @else
        <div class="kartu brutal mx-auto max-w-2xl bg-paper p-6">
            <div class="flex items-start justify-between gap-4 border-b-4 border-ink pb-4">
                <div>
                    <p class="font-display text-lg leading-tight">{{ $situs['nama_rayon'] ?? __('umum.nama_organisasi') }}</p>
                    <p class="text-xs text-muted">{{ $situs['nama_komisariat'] ?? __('umum.nama_komisariat') }}</p>
                </div>

                <img src="{{ asset('brand/logo-pmii-raab.png') }}" alt="Logo" class="h-12 w-auto">
            </div>

            <p class="mt-4 text-center font-display text-xl uppercase">{{ __('kartu.kartu.judul') }}</p>

            {{--
                Keadaan kartu diumumkan di sini juga, bukan hanya di halaman
                verifikasi. Kader yang memegang kartu kedaluwarsa harus tahu
                sebelum ia mencoba memakainya di pintu acara.
            --}}
            @if ($alasan === \App\Models\MemberCard::ALAS_DICABUT)
                <p class="mt-2 border-2 border-ink bg-accent-400 px-3 py-2 text-center text-xs font-bold uppercase">
                    {{ __('kartu.kartu.dicabut') }}
                </p>
            @elseif ($alasan === \App\Models\MemberCard::ALAS_KEDALUWARSA)
                <p class="mt-2 border-2 border-ink bg-accent-400 px-3 py-2 text-center text-xs font-bold uppercase">
                    {{ __('kartu.kartu.kedaluwarsa') }}
                </p>
            @endif

            <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-start">
                <dl class="flex-1 space-y-3">
                    <div>
                        <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.nama') }}</dt>
                        <dd class="font-display text-lg">{{ $anggota->nama_lengkap }}</dd>
                    </div>

                    {{--
                        Baris nomor anggota disembunyikan bila kosong. Mencetak
                        "—" pada kartu hanya membingungkan pemegangnya; kartu
                        tanpa nomor anggota lebih baik tidak memamerkan baris
                        kosong.
                    --}}
                    @if ($anggota->nomor_anggota)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.nomor') }}</dt>
                            <dd class="font-mono text-sm">{{ $anggota->nomor_anggota }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.nomor_kartu') }}</dt>
                        <dd class="font-mono text-sm">{{ $kartu->nomor_kartu }}</dd>
                    </div>

                    @if ($anggota->unit)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.unit') }}</dt>
                            <dd class="text-sm">{{ $anggota->unit->nama }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.berlaku') }}</dt>
                        <dd class="text-sm">
                            {{ $kartu->berlaku_sampai?->translatedFormat('d F Y') ?? __('kartu.bidang.tanpa_batas') }}
                        </dd>
                    </div>
                </dl>

                <div class="sm:w-44 sm:shrink-0">
                    {{--
                        QR dibangun di sisi peramban. Tautannya TETAP ditulis
                        sebagai teks di bawah kode, sehingga kartu masih bisa
                        diverifikasi walau gambar QR gagal dimuat (mis. saat
                        perangkat sedang tanpa jaringan ke CDN).
                    --}}
                    <div id="qr" class="brutal-sm flex h-40 w-40 items-center justify-center bg-paper p-1"></div>

                    <p class="mt-2 text-[10px] leading-snug text-muted">{{ __('kartu.kartu.pindai') }}</p>

                    <p class="mt-2 text-[10px] font-bold uppercase text-muted">{{ __('kartu.kartu.tautan') }}</p>
                    <p class="break-all font-mono text-[10px] leading-snug">{{ $tautanVerifikasi }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($kartu)
        <script src="https://unpkg.com/qrcode-generator@1.4.4/qrcode.js" defer></script>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                var wadah = document.getElementById('qr');

                if (!wadah || typeof qrcode !== 'function') {
                    return;
                }

                try {
                    var qr = qrcode(0, 'M');
                    qr.addData(@json($tautanVerifikasi));
                    qr.make();
                    wadah.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0 });
                } catch (e) {
                    // Biarkan kosong — tautan teks di bawahnya tetap bisa dipakai.
                }
            });
        </script>
    @endif
</body>
</html>
