<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jatah Penyimpanan Media
    |--------------------------------------------------------------------------
    |
    | Dipakai panel untuk memperingatkan pengurus SEBELUM jatah penyedia
    | penyimpanan habis. Nilai bawaan mengikuti jatah gratis Cloudflare R2,
    | yaitu 10 GB-bulan per bulan (https://developers.cloudflare.com/r2/pricing/).
    | Ubah lewat MEDIA_KUOTA_MB bila pindah penyedia atau menaikkan paket.
    |
    | Ini murni alat pantau: melewatinya TIDAK menolak unggahan apa pun.
    | Kelebihannya tetap tersimpan dan mulai dihitung berbayar (sekitar
    | $0,015 per GB-bulan pada R2), sehingga peringatan ini bersifat
    | pemberitahuan biaya, bukan pagar yang menghentikan pekerjaan.
    |
    */

    'kuota_mb' => (int) env('MEDIA_KUOTA_MB', 10240),

    /*
    | Bagian dari jatah yang mulai memicu peringatan di panel (0.8 = 80%).
    */

    'ambang_peringatan' => (float) env('MEDIA_AMBANG_PERINGATAN', 0.8),

];
