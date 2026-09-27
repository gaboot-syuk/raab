<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pemicu penjadwal
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh GET /internal/scheduler/{token} — pengganti cron untuk
    | hosting yang tidak menyediakan cron. Selama nilainya kosong, jalur itu
    | menjawab 404: fitur yang belum dikonfigurasi harus MATI, bukan terbuka.
    |
    */
    'scheduler' => [
        'token' => env('SCHEDULER_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Penerjemah otomatis (Fase 3).
     *
     * Kunci API disimpan di .env, tidak di basis data — lihat penjelasan pada
     * App\Services\Penerjemah::kunci(). Pilihan penyedia tetap dapat diubah
     * pengurus lewat panel (Pengaturan Situs → Terjemahan).
     */
    'penerjemah' => [
        'kunci' => env('PENERJEMAH_KUNCI'),
    ],

    /*
     * Captcha (Fase 9).
     *
     * Bawaannya `none`, dan itu memang disengaja: formulir publik harus tetap
     * bisa dipakai di lingkungan pengembangan dan pengujian tanpa kunci dari
     * layanan luar. Yang menjadi pertahanan pertama formulir publik tetap
     * honeypot, pembatasan laju, dan pemeriksaan di sisi server; captcha
     * adalah lapisan tambahan yang dinyalakan saat situs sudah online.
     *
     * Kunci disimpan di .env — kunci rahasia tidak boleh masuk basis data,
     * karena isi `site_settings` ikut terbaca oleh halaman publik.
     */
    'captcha' => [
        'penyedia' => env('CAPTCHA_PENYEDIA', 'none'),
        'kunci_situs' => env('CAPTCHA_SITUS'),
        'kunci_rahasia' => env('CAPTCHA_RAHASIA'),
    ],

];
