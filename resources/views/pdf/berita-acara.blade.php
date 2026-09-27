<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara {{ $artikel->nomor_dokumen }}</title>

    {{--
        Templat PDF berita acara.
        Sengaja memakai gaya sederhana tanpa warna latar dan tanpa gambar, karena
        mesin PDF (dompdf) bekerja paling andal pada tata letak teks yang lugas.
        Ukuran huruf dipilih agar satu halaman cukup untuk berita acara standar.
    --}}
    <style>
        @page { margin: 22mm 20mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.55;
            color: #111;
        }

        .kop { text-align: center; border-bottom: 3px double #111; padding-bottom: 10px; }
        .kop .nama { font-size: 13pt; font-weight: bold; }
        .kop .sub { font-size: 10pt; }
        .kop .kampus { font-size: 9pt; }

        h1 {
            font-size: 12.5pt;
            text-align: center;
            margin: 18px 0 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nomor { text-align: center; font-size: 10.5pt; margin-bottom: 18px; }

        .baris { margin: 0 0 8px; }
        .label { display: inline-block; width: 110px; font-weight: bold; }

        .bagian { margin-top: 14px; }
        .bagian > .judul {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 4px;
        }

        .isi { text-align: justify; }

        table.ttd { width: 100%; margin-top: 34px; }
        table.ttd td { vertical-align: top; font-size: 10.5pt; }
        table.ttd .kotak { height: 58px; }

        .catatan {
            margin-top: 26px;
            padding-top: 8px;
            border-top: 1px solid #999;
            font-size: 8.5pt;
            color: #555;
        }
    </style>
</head>
<body>
    @php
        $situs = \App\Support\Pengaturan::semua();
    @endphp

    <div class="kop">
        <div class="nama">{{ $situs['nama_rayon'] ?? 'PMII Rayon Ali Ahmad Baktsir' }}</div>
        <div class="sub">{{ $situs['nama_komisariat'] ?? 'PMII Komisariat Raden Mas Said' }}</div>
        <div class="sub">{{ $situs['nama_cabang'] ?? 'Cabang Sukoharjo' }}</div>
        <div class="kampus">{{ $situs['nama_kampus'] ?? 'UIN Raden Mas Said Surakarta' }}</div>
        @if (filled($situs['alamat'] ?? null) && ! str_contains((string) $situs['alamat'], 'belum diisi'))
            <div class="kampus">{{ $situs['alamat'] }}</div>
        @endif
    </div>

    <h1>Berita Acara</h1>
    <div class="nomor">Nomor: {{ $artikel->nomor_dokumen }}</div>

    <p class="baris">
        Pada hari ini, {{ $artikel->tanggal_agenda?->translatedFormat('l') }},
        tanggal {{ $artikel->tanggal_agenda?->translatedFormat('d F Y') }},
        telah dilaksanakan kegiatan dengan rincian sebagai berikut.
    </p>

    <div class="bagian">
        <div class="judul">A. Agenda</div>
        <div class="isi">{!! nl2br(e($artikel->agenda)) !!}</div>
    </div>

    <div class="bagian">
        <div class="judul">B. Keputusan</div>
        <div class="isi">{!! nl2br(e($artikel->keputusan)) !!}</div>
    </div>

    @if (filled((string) $artikel->getTranslation('konten', 'id', false)))
        <div class="bagian">
            <div class="judul">C. Keterangan</div>
            <div class="isi">{!! $artikel->getTranslation('konten', 'id') !!}</div>
        </div>
    @endif

    <p class="baris bagian">
        Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.
    </p>

    <table class="ttd">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; text-align: center;">
                <div>{{ $situs['nama_rayon'] ?? 'PMII Rayon Ali Ahmad Baktsir' }}</div>
                <div class="kotak"></div>
                <div style="font-weight: bold; text-decoration: underline;">{{ $artikel->penandatangan }}</div>
                <div>{{ $artikel->jabatan_penandatangan }}</div>
            </td>
        </tr>
    </table>

    <div class="catatan">
        Dokumen ini diunduh dari situs resmi {{ $situs['nama_rayon'] ?? 'PMII Rayon Ali Ahmad Baktsir' }}
        pada {{ now()->translatedFormat('d F Y, H:i') }} WIB.
    </div>
</body>
</html>
