<?php

/*
 * Teks halaman publik modul Organisasi (Fase 4).
 * Dipisah dari umum.php supaya mudah ditelusuri per modul.
 */

return [
    'struktur' => [
        'judul' => 'Struktur Kepengurusan',
        'intro' => 'Susunan pengurus PMII Rayon Ali Ahmad Baktsir, disusun per tingkat jabatan. Pilih periode untuk melihat susunan tahun sebelumnya.',
        'periode' => 'Periode',
        'jumlah' => 'pengurus pada periode ini',
        'kosong' => 'Belum ada penugasan pengurus yang tercatat pada periode ini.',
        'tanpa_periode' => 'Belum ada periode kepengurusan yang dibuat. Pengurus dapat menambahkannya lewat panel.',
        'tingkat' => [
            1 => 'Pimpinan',
            2 => 'Pengurus Harian',
            3 => 'Kepala Biro & Lembaga',
        ],
    ],
    'lso' => [
        'judul' => 'Lembaga Semi Otonom',
        'intro' => 'Lima lembaga semi otonom yang menampung minat dan bakat kader di dalam rayon.',
        'judul_unit' => 'Profil Lembaga',
        'pengurus' => 'Pengurus',
        'anggota' => 'Kader',
        'galeri' => 'Dokumentasi',
        'agenda' => 'Agenda',
        'agenda_mendatang' => 'Agenda Akan Datang',
        'agenda_lampau' => 'Agenda yang Sudah Berjalan',
        'belum_ada' => 'Belum ada data.',
        'kembali' => 'Semua LSO',
    ],
    'galeri' => [
        'judul' => 'Galeri Kegiatan',
        'intro' => 'Dokumentasi kegiatan rayon, biro, dan lembaga semi otonom.',
        'semua_unit' => 'Semua unit',
        'foto' => 'foto',
        'kosong' => 'Belum ada album yang dibagikan untuk umum.',
        'album_lain' => 'Album Lainnya',
    ],
    'kader' => [
        'judul' => 'Profil Kader',
        'riwayat' => 'Riwayat Kepengurusan',
        'karya' => 'Karya Tulis',
        'prestasi' => 'Prestasi',
        'tentang' => 'Tentang',
        'bidang' => 'Bidang',
        'tahun_lulus' => 'Tahun Lulus',
        'instansi' => 'Instansi',
        'unit' => 'Unit',
        'keahlian' => 'Keahlian',
        'belum_ada' => 'Belum ada data.',
        'tertutup' => 'Profil ini hanya dapat dibuka oleh pemiliknya sendiri. Kader lain dapat memilih untuk membukanya dari halaman profil.',
    ],
    'direktori' => [
        'instansi' => 'Instansi',
        'fakultas' => 'Fakultas',
        'prodi' => 'Program Studi',
        'slug_pencarian' => 'mis. nama, prodi, atau NIM',
    ],
    'peta' => [
        'judul' => 'Peta Sebaran Alumni',
        'keterangan' => 'Titik di peta hanya muncul untuk alumni yang mengisi lintang & bujur di profilnya, dan hanya menampilkan data yang mereka izinkan.',
        'kosong' => 'Belum ada alumni yang mengisi koordinat lokasinya.',
        'jumlah' => 'alumni tampil di peta',
        'kunjungi' => 'Lihat profil',
    ],
];
