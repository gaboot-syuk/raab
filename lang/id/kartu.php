<?php

/*
 * Halaman verifikasi kartu kader & kartu digital kader.
 *
 * Ditulis terpisah dari `umum.php` karena hanya dipakai dua halaman ini.
 */
return [
    'judul' => 'Verifikasi Kartu Kader',
    'intro' => 'Halaman ini memastikan sebuah kartu kader benar diterbitkan rayon dan masih berlaku.',
    'remah' => 'Verifikasi Kartu',

    'sah' => [
        'judul' => 'Kartu Sah',
        'teks' => 'Kartu ini benar diterbitkan oleh rayon dan masih berlaku.',
    ],

    'tidak_sah' => [
        'judul' => 'Kartu Tidak Sah',
        'teks' => 'Kartu ini tidak dapat diverifikasi.',
    ],

    'alasan' => [
        'dicabut' => 'Kartu ini sudah dicabut. Pencabutan terjadi ketika status keanggotaan berubah, misalnya menjadi alumni atau nonaktif.',
        'kedaluwarsa' => 'Masa berlaku kartu ini sudah habis. Pemiliknya kemungkinan masih kader aktif dan perlu meminta penerbitan ulang di sekretariat.',
        'tidak_ditemukan' => 'Tidak ada kartu dengan kode verifikasi itu. Periksa kembali tautan atau kode QR yang dipindai.',
    ],

    'bidang' => [
        'nama' => 'Nama',
        'nomor' => 'Nomor Anggota',
        'nomor_kartu' => 'Nomor Kartu',
        'unit' => 'Biro & LSO',
        'jalur' => 'Jalur',
        'berlaku' => 'Berlaku Sampai',
        'diterbitkan' => 'Diterbitkan',
        'tanpa_batas' => 'Tanpa batas waktu',
    ],

    'privasi' => 'Halaman ini hanya menampilkan data yang sudah tercetak di kartu. Hubungi sekretariat rayon untuk keterangan lebih lanjut.',
    'kembali' => 'Buka Beranda',

    'kartu' => [
        'judul' => 'Kartu Kader Digital',
        'cetak' => 'Cetak / Simpan PDF',
        'tutup' => 'Tutup',
        'tautan' => 'Tautan verifikasi',
        'pindai' => 'Pindai kode QR untuk memeriksa keaslian kartu ini.',
        'belum_ada' => 'Kartu kadermu belum diterbitkan.',
        'belum_ada_teks' => 'Kartu diterbitkan otomatis begitu keanggotaanmu diverifikasi. Hubungi sekretariat rayon bila kamu merasa sudah terverifikasi.',
        'alumni_tanpa_kartu' => 'Kartu kader tidak berlaku untuk alumni.',
        'alumni_tanpa_kartu_teks' => 'Kartu kader dipakai untuk presensi kegiatan dan peminjaman, dan keduanya hanya untuk kader aktif. Status alumnimu tetap tercatat, dan profil alumni bisa kamu atur sendiri.',
        'dicabut' => 'Kartu ini sudah dicabut dan tidak dapat dipakai untuk presensi.',
        'kedaluwarsa' => 'Masa berlaku kartu ini sudah habis. Minta penerbitan ulang di sekretariat.',
    ],

    'prestasi' => [
        'intro' => 'Prestasi kader rayon yang sudah diperiksa dan diverifikasi pengurus.',
        'jumlah' => 'Prestasi Terverifikasi',
        'kader' => 'Kader Berprestasi',
        'unggulan' => 'Unggulan Rayon',
        'tingkat' => 'Tingkat',
        'semua_tingkat' => 'Semua tingkat',
        'kategori' => 'Kategori',
        'semua_kategori' => 'Semua kategori',
        'saring' => 'Saring',
        'kosong' => 'Belum ada prestasi yang tayang.',
        'kosong_teks' => 'Prestasi muncul di sini setelah diperiksa pengurus dan pemiliknya mengizinkan penayangan.',
        'lihat_sertifikat' => 'Lihat sertifikat',
        'catatan' => 'Hanya prestasi yang sudah diverifikasi pengurus dan diizinkan pemiliknya yang tampil di halaman ini. Prestasi yang masih menunggu diperiksa tidak ditayangkan.',
    ],
];
