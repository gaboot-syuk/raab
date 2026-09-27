<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Member;
use App\Models\OrganisationUnit;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Peta situs (sitemap.xml).
 *
 * DIHASILKAN DARI RUTE DAN DATA, bukan ditulis tangan. Peta situs yang ditulis
 * tangan pasti ketinggalan begitu ada halaman baru — dan peta situs yang
 * ketinggalan lebih buruk daripada tidak ada, karena mesin pencari mengira
 * halaman yang tidak terdaftar memang tidak penting.
 *
 * Hanya halaman yang MEMANG ingin diindeks yang didaftarkan: halaman publik.
 * Panel, area anggota, kartu kader, dan halaman pelacakan sengaja TIDAK
 * didaftarkan — semuanya butuh masuk, dan mendaftarkannya hanya menghasilkan
 * ratusan baris "dilarang" di laporan mesin pencari.
 *
 * HASILNYA DI-CACHE. Peta situs dibaca berkali-kali oleh perayap, sementara
 * isinya berubah paling sering beberapa kali sehari. Menyusunnya ulang setiap
 * kali dibaca hanya membebani basis data tanpa manfaat.
 */
class SitemapController extends Controller
{
    /** Berapa lama peta situs disimpan sebelum disusun ulang (detik). */
    private const CACHE_DETIK = 21600; // 6 jam

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', self::CACHE_DETIK, fn (): string => $this->susun());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function susun(): string
    {
        $halaman = $this->halaman();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($halaman as $baris) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.$this->aman($this->dwibahasa($baris['jalur'], app()->getLocale())).'</loc>'."\n";

            // Versi bahasa Inggris didaftarkan sebagai alternatif, bukan
            // sebagai halaman terpisah: isinya sama, hanya bahasanya berbeda.
            if ($baris['dwibahasa'] ?? true) {
                foreach (array_keys(LaravelLocalization::getSupportedLocales()) as $kode) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="'.$this->aman($kode).'" href="'
                        .$this->aman($this->dwibahasa($baris['jalur'], $kode)).'" />'."\n";
                }
            }

            if (isset($baris['terakhir'])) {
                $xml .= '    <lastmod>'.$baris['terakhir']->toAtomString().'</lastmod>'."\n";
            }

            $xml .= '    <changefreq>'.$baris['frekuensi'].'</changefreq>'."\n";
            $xml .= '    <priority>'.number_format($baris['prioritas'], 1, '.', '').'</priority>'."\n";
            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /**
     * Daftar halaman yang didaftarkan, beserta seberapa sering isinya berubah.
     *
     * @return array<int, array{jalur: string, frekuensi: string, prioritas: float, terakhir?: \Carbon\CarbonInterface, dwibahasa?: bool}>
     */
    private function halaman(): array
    {
        $statis = [
            ['jalur' => '/', 'frekuensi' => 'daily', 'prioritas' => 1.0],
            ['jalur' => '/sejarah', 'frekuensi' => 'monthly', 'prioritas' => 0.6],
            ['jalur' => '/visi-misi', 'frekuensi' => 'monthly', 'prioritas' => 0.6],
            ['jalur' => '/sambutan', 'frekuensi' => 'monthly', 'prioritas' => 0.6],
            ['jalur' => '/struktur', 'frekuensi' => 'weekly', 'prioritas' => 0.8],
            ['jalur' => '/lso', 'frekuensi' => 'weekly', 'prioritas' => 0.7],
            ['jalur' => '/publikasi', 'frekuensi' => 'daily', 'prioritas' => 0.9],
            ['jalur' => '/prestasi', 'frekuensi' => 'weekly', 'prioritas' => 0.7],
            ['jalur' => '/pengumuman', 'frekuensi' => 'daily', 'prioritas' => 0.8],
            ['jalur' => '/arsip', 'frekuensi' => 'weekly', 'prioritas' => 0.6],
            ['jalur' => '/galeri', 'frekuensi' => 'weekly', 'prioritas' => 0.6],
            ['jalur' => '/anggota', 'frekuensi' => 'weekly', 'prioritas' => 0.5],
            ['jalur' => '/alumni', 'frekuensi' => 'weekly', 'prioritas' => 0.5],
            ['jalur' => '/perpustakaan', 'frekuensi' => 'weekly', 'prioritas' => 0.5],
            ['jalur' => '/inventaris', 'frekuensi' => 'weekly', 'prioritas' => 0.5],
            ['jalur' => '/pendaftaran', 'frekuensi' => 'weekly', 'prioritas' => 0.8],
            ['jalur' => '/kontak', 'frekuensi' => 'monthly', 'prioritas' => 0.6],
            ['jalur' => '/lokasi', 'frekuensi' => 'monthly', 'prioritas' => 0.5],
            ['jalur' => '/media-sosial', 'frekuensi' => 'monthly', 'prioritas' => 0.4],
            ['jalur' => '/aspirasi', 'frekuensi' => 'weekly', 'prioritas' => 0.7],
        ];

        // Tipe publikasi yang benar-benar punya artikel terbit.
        $tipe = Article::query()->terbit()->distinct()->pluck('tipe')->filter()->all();

        foreach ($tipe as $satu) {
            $statis[] = ['jalur' => '/publikasi/'.$satu, 'frekuensi' => 'daily', 'prioritas' => 0.8];
        }

        $halaman = [];

        foreach ($statis as $baris) {
            $halaman[] = $baris;
        }

        foreach (Article::query()->terbit()->orderByDesc('terbit_pada')->limit(500)->get() as $artikel) {
            $halaman[] = [
                'jalur' => '/publikasi/'.$artikel->tipe.'/'.$artikel->slug,
                'frekuensi' => 'monthly',
                'prioritas' => 0.7,
                'terakhir' => $artikel->updated_at,
            ];
        }

        foreach (OrganisationUnit::query()->whereNotNull('slug')->get() as $unit) {
            $halaman[] = [
                'jalur' => '/lso/'.$unit->slug,
                'frekuensi' => 'monthly',
                'prioritas' => 0.6,
                'terakhir' => $unit->updated_at,
            ];
        }

        // Profil kader hanya bila pemiliknya sendiri mengizinkannya tampil.
        foreach (Member::query()->profilTerbuka()->get() as $anggota) {
            $halaman[] = [
                'jalur' => '/prestasi/kader/'.$anggota->slug,
                'frekuensi' => 'monthly',
                'prioritas' => 0.4,
                'terakhir' => $anggota->updated_at,
            ];
        }

        return $halaman;
    }

    /**
     * Bentuk alamat lengkap untuk sebuah jalur, dalam bahasa tertentu.
     *
     * ALAMATNYA DIBANGUN DARI `APP_URL`, bukan dari permintaan yang sedang
     * berjalan.
     *
     * Alasannya bukan kerapian. Peta situs ini di-CACHE, dan cache-nya tidak
     * menyimpan dari mana permintaan itu datang. Kalau alamatnya mengikuti
     * permintaan, maka kunjungan PERTAMA-lah yang menentukan alamat untuk
     * semua orang selama enam jam berikutnya: satu perayap yang kebetulan
     * datang lewat alamat IP atau nama staging akan membuat peta situs
     * mengumumkan alamat itu ke seluruh mesin pencari. `APP_URL` membuat
     * hasilnya sama dari mana pun diminta.
     */
    private function dwibahasa(string $jalur, string $kode): string
    {
        $dasar = rtrim((string) config('app.url'), '/');
        $bersih = '/'.ltrim($jalur, '/');

        return $kode === 'id' ? $dasar.$bersih : $dasar.'/'.$kode.$bersih;
    }

    private function aman(string $teks): string
    {
        return htmlspecialchars($teks, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
