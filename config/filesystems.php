<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),

            /*
             * Alamat RELATIF, tanpa domain.
             *
             * Sebelumnya di sini tertulis `env('APP_URL').'/storage'`. Akibatnya
             * setiap gambar yang diunggah memakai domain dari APP_URL, dan
             * begitu APP_URL tidak sama persis dengan domain yang sedang dibuka
             * — pratinjau di port lain, staging, `www` versus tanpa `www`,
             * http versus https — gambar itu DIANGGAP BERASAL DARI LUAR dan
             * diblokir oleh kebijakan keamanan kita sendiri:
             *
             *   Loading the image 'http://localhost:8000/storage/2/sertifikat.png'
             *   violates the following Content Security Policy directive: "img-src 'self' …"
             *
             * Yang terlihat pengurus: gambar hilang tanpa penjelasan, hanya ada
             * catatan di konsol peramban yang tidak pernah ia buka.
             *
             * Dengan alamat relatif, gambar selalu diambil dari host yang sedang
             * dibuka, apa pun isi APP_URL. Satu kelas kesalahan hilang
             * seluruhnya — dan berkas media memang dilayani dari host yang sama
             * lewat tautan `public/storage`.
             *
             * Berkas media TIDAK dipakai di surat elektronik, jadi tidak ada
             * tempat yang membutuhkan alamat lengkap. Kalau kelak ada, bungkus
             * dengan `url()` di tempat itu — jangan kembalikan domain ke sini.
             */
            'url' => '/storage',

            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
