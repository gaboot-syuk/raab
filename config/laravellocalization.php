<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Lokalisasi URL
|--------------------------------------------------------------------------
| Indonesia = bahasa bawaan (tanpa prefiks pada URL)
| Inggris   = prefiks /en
|
| Contoh:
|   /publikasi/berita          → versi Indonesia
|   /en/publikasi/berita       → versi Inggris
*/

return [

    /*
    | Bahasa yang didukung situs.
    | Urutan mengikuti nama asli masing-masing bahasa.
    */
    'supportedLocales' => [
        'id' => ['name' => 'Indonesian', 'script' => 'Latn', 'native' => 'Bahasa Indonesia', 'regional' => 'id_ID'],
        'en' => ['name' => 'English', 'script' => 'Latn', 'native' => 'English', 'regional' => 'en_GB'],
    ],

    /*
    | Deteksi bahasa dari header Accept-Language.
    | Sengaja DIMATIKAN: situs organisasi sebaiknya selalu terbuka dalam
    | Bahasa Indonesia (bahasa utama), lalu pengunjung memilih sendiri
    | lewat tombol ID/EN. Pengalihan otomatis menyulitkan saat tautan dibagikan
    | dan kurang baik untuk mesin pencari.
    */
    'useAcceptLanguageHeader' => false,

    /*
    | Sembunyikan bahasa bawaan (Indonesia) pada URL.
    */
    'hideDefaultLocaleInURL' => true,

    /*
    | Urutan tampil di pengalih bahasa.
    */
    'localesOrder' => ['id', 'en'],

    /*
    | Pemetaan kode bahasa alternatif (mis. 'in' → 'id').
    */
    'localesMapping' => [
        'in' => 'id',
    ],

    /*
    | URL yang tidak diproses oleh middleware lokalisasi.
    */
    'urlsIgnored' => [
        '/skipped',
        'up',
        'storage/*',
    ],

    /*
    | Metode HTTP yang dilewati (tidak dialihkan bahasanya).
    */
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],

];
