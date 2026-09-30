<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Pengaturan;
use Illuminate\Http\JsonResponse;

/**
 * Web app manifest — daftar isi yang dibaca peramban saat pengunjung memasang
 * situs ini sebagai pintasan di layar utama.
 *
 * KENAPA ROUTE, BUKAN BERKAS STATIS DI public/
 *
 * Berkas statis tidak bisa mengikuti Pengaturan Situs, sedangkan identitas
 * rayon di situs ini memang hidup di sana: pengurus boleh mengubah nama
 * rayonnya, dan nama yang terpasang di layar utama harus ikut berubah. Berkas
 * statis juga terkunci pada satu kode bahasa, padahal situs ini dwibahasa.
 *
 * APA YANG SENGAJA TIDAK ADA DI SINI
 *
 * Tidak ada service worker, dan itu BUKAN kekurangan. Syarat pemasangan Chrome
 * yang berlaku sekarang hanya: HTTPS, manifest berisi name/short_name,
 * icons berukuran 192 px dan 512 px, start_url, dan display. Service worker
 * tidak lagi termasuk syaratnya.
 *
 * Akibatnya disengaja: pintasan ini murni jalan pintas. Ia tidak menyimpan
 * apa pun di perangkat pengunjung — jadi tidak ada isi yang bisa basi, dan
 * tidak ada data yang tertinggal di ponsel bersama. Sebagai gantinya, membuka
 * pintasan tanpa jaringan akan menampilkan halaman galat peramban.
 */
class ManifestController extends Controller
{
    /**
     * Warna merek, diambil dari token desain di resources/css/app.css.
     * Nilainya disalin ke sini karena manifest tidak bisa membaca CSS; kalau
     * tokennya diubah, ubah juga di sini.
     */
    private const WARNA_MEREK = '#2e3192';

    private const WARNA_LATAR = '#fffdf5';

    public function __invoke(): JsonResponse
    {
        $situs = Pengaturan::semua();

        $nama = $situs['nama_rayon'] ?? __('umum.nama_organisasi');
        $singkat = $situs['nama_singkat'] ?? __('umum.nama_singkat');
        $deskripsi = trim(strip_tags((string) ($situs['seo_deskripsi'] ?? __('umum.footer.tentang_teks'))));

        return response()->json([
            // `id` mengunci identitas aplikasi ini. Tanpa itu, memindahkan
            // start_url kelak akan membuat peramban menganggapnya aplikasi
            // BERBEDA dan memasangnya sekali lagi.
            'id' => '/',

            'name' => $nama,
            'short_name' => $singkat,
            'description' => \Illuminate\Support\Str::limit($deskripsi, 200),

            'start_url' => '/',
            'scope' => '/',

            /*
             * `standalone` adalah inti dari permintaan ini: pintasan dibuka
             * TANPA bilah alamat, sehingga terasa seperti aplikasi — bukan
             * seperti tab peramban yang dipendekkan.
             */
            'display' => 'standalone',

            'background_color' => self::WARNA_LATAR,
            'theme_color' => self::WARNA_MEREK,

            'lang' => app()->getLocale(),
            'dir' => 'ltr',

            /*
             * Alamat ikon DITULIS RELATIF (diawali "/"), bukan lewat asset().
             *
             * asset() memakai APP_URL, dan begitu APP_URL tidak sama persis
             * dengan domain yang sedang dibuka, seluruh alamat di manifest
             * menunjuk host yang salah. Alamat relatif selalu diambil dari
             * host yang sedang dipakai. Situs ini pernah kena persis masalah
             * itu pada alamat berkas media.
             */
            'icons' => [
                [
                    'src' => '/ikon/ikon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/ikon/ikon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    /*
                     * Ikon `maskable` dipakai Android, yang memotong ikon
                     * sesuai bentuk sistem (lingkaran, kotak membulat).
                     * Perisainya sengaja lebih kecil dan berada di dalam zona
                     * aman, karena bagian di luar zona aman boleh dipotong.
                     */
                    'src' => '/ikon/ikon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
        ]);
    }
}
