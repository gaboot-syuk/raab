<?php

/*
 * Halaman aspirasi publik.
 */
return [
    'judul' => 'Aspirasi',
    'intro' => 'Sampaikan aspirasimu kepada pengurus rayon, dan lihat bagaimana aspirasi lain ditanggapi.',
    'galat_ringkas' => 'Mohon periksa kembali isian berikut:',

    'papan' => [
        'judul' => 'Papan Aspirasi',
        'teks' => 'Hanya aspirasi yang sudah ditanggapi yang tampil di sini. Identitas pengirim tidak pernah ditampilkan, dan isi suratnya sudah dibersihkan dari nomor telepon, email, serta NIK.',
        'total' => 'Sudah Ditanggapi',
        'selesai' => 'Selesai',
        'diproses' => 'Sedang Diproses',
        'kosong' => 'Belum ada aspirasi yang ditanggapi.',
        'kosong_teks' => 'Papan ini terisi begitu pengurus menanggapi aspirasi yang masuk.',
        'tanggapan' => 'Tanggapan Pengurus',
        'anonim' => 'Identitas pengirim hanya dibaca pengurus.',
        'kategori' => 'Kategori',
        'semua_kategori' => 'Semua kategori',
        'saring' => 'Saring',
    ],

    'form' => [
        'judul' => 'Sampaikan Aspirasi',
        'teks' => 'Identitas wajib diisi supaya pengurus dapat menindaklanjuti dan menghubungimu kembali. Identitasmu TIDAK akan ditampilkan di papan publik.',
        'nama' => 'Nama *',
        'email' => 'Email *',
        'telepon' => 'Nomor telepon',
        'kategori' => 'Kategori *',
        'judul_aspirasi' => 'Judul *',
        'isi' => 'Isi aspirasi *',
        'isi_bantuan' => 'Ceritakan keadaannya dengan cukup jelas: apa, di mana, dan apa yang kamu harapkan. Hindari menuliskan nomor telepon atau data pribadi orang lain di badan surat — bagian itu akan dibersihkan sebelum tayang.',
        'min_isi' => 'Minimal :jumlah huruf.',
        'tampil_publik' => 'Izinkan aspirasi ini tampil di papan publik setelah ditanggapi',
        'kirim' => 'Kirim Aspirasi',
        'terkirim' => 'Aspirasi Terkirim',
        'token_judul' => 'Simpan nomor tiket dan token berikut',
        'token_teks' => 'Keduanya dibutuhkan untuk melacak tindak lanjut. Token hanya ditampilkan sekali di halaman ini dan tidak dikirim ke surelmu, jadi salinlah sekarang.',
        'nomor_tiket' => 'Nomor tiket',
        'token' => 'Token pelacakan',
    ],

    'lacak' => [
        'judul' => 'Lacak Aspirasi',
        'teks' => 'Masukkan nomor tiket dan token yang kamu terima saat mengirim aspirasi.',
        'nomor_tiket' => 'Nomor tiket *',
        'token' => 'Token pelacakan *',
        'tombol' => 'Lacak',
        'judul_hasil' => 'Hasil Pelacakan',
        'status' => 'Status',
        'dikirim' => 'Dikirim',
        'ditanggapi' => 'Ditanggapi',
        'belum_ditanggapi' => 'Belum ditanggapi pengurus.',
        'isi_kamu' => 'Isi aspirasimu',
    ],
];
