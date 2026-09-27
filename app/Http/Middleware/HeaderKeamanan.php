<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk SEMUA respons.
 *
 * Dipasang sebagai middleware global, bukan hanya di grup `web`: halaman galat,
 * berkas yang diunduh, dan jawaban JSON juga perlu membawa header ini. Header
 * yang hanya menempel pada halaman yang "normal" tidak menolong justru pada
 * saat-saat yang paling perlu ditolong.
 *
 * TENTANG CSP — DAN BATAS JUJURNYA. Kebijakan ini memuat `'unsafe-inline'` dan
 * `'unsafe-eval'` pada `script-src`, dan itu memang melemahkan dayanya terhadap
 * XSS. Alasannya bukan kelalaian: halaman publik memakai Alpine.js, dan Alpine
 * mengevaluasi ekspresi `x-data` dengan `new AsyncFunction()` — yang tanpa
 * `'unsafe-eval'` akan diblokir, sehingga seluruh menu, sakelar tema, dan
 * penyaring di situs berhenti bekerja. Selain itu ada skrip kecil anti-kedip
 * tema yang ditulis langsung di dalam `<head>`.
 *
 * Jadi yang BENAR-BENAR dijaga CSP ini adalah hal-hal lain, dan itu bukan
 * hal kecil: `form-action` menutup pengiriman data ke host lain, `base-uri`
 * menutup serangan lewat tag `<base>`, `object-src 'none'` menutup plugin,
 * `frame-ancestors` mencegah situs ini dibingkai orang lain (clickjacking), dan
 * `connect-src`/`img-src` membatasi ke mana data bisa dikirim keluar.
 *
 * Untuk menutup sisanya, Alpine perlu diganti dengan build CSP-nya
 * (`@alpinejs/csp`) yang menuntut menulis ulang seluruh ekspresi menjadi
 * pemanggilan metode. Itu pekerjaan tersendiri, dan lebih baik dicatat
 * terbuka di sini daripada diklaim sebagai sudah selesai.
 */
class HeaderKeamanan
{
    /**
     * Sumber luar yang memang dipakai situs ini, dan hanya itu.
     *
     * - unpkg.com      : pustaka QR (kartu kader, halaman presensi) dan Leaflet (peta alumni)
     * - tile server    : ubin peta OpenStreetMap untuk halaman alumni
     *
     * Kalau suatu saat ada sumber baru, ia harus ditambahkan DI SINI — bukan
     * dengan melonggarkan kebijakannya menjadi `*`.
     *
     * `unpkg.com` muncul di SKRIP, GAYA, **dan GAMBAR**. Yang terakhir mudah
     * terlupa, dan akibatnya terlihat: Leaflet memuat penanda petanya
     * (`marker-icon.png`, `marker-shadow.png`) dari paket yang sama, sehingga
     * tanpa izin di `img-src`, petanya tampil dengan penanda rusak — peta yang
     * ada tetapi tidak bisa dibaca.
     */
    private const SUMBER_LUAR = [
        'skrip' => 'https://unpkg.com',
        'gaya' => 'https://unpkg.com',
        'gambar' => 'https://unpkg.com https://tile.openstreetmap.org https://*.tile.openstreetmap.org',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respons = $next($request);

        // Header dasar. Dikirim untuk semua jenis respons.
        $respons->headers->set('X-Content-Type-Options', 'nosniff');
        // SAMEORIGIN, bukan DENY: halaman QR presensi dan kartu kader wajar
        // dibuka di dalam bingkai milik situs ini sendiri saat dicetak.
        $respons->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $respons->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respons->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $respons->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $respons->headers->set(
            'Permissions-Policy',
            'geolocation=(self), camera=(self), microphone=(), payment=(), usb=()',
        );

        if ($request->secure()) {
            $respons->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $respons->headers->has('Content-Security-Policy')) {
            $respons->headers->set('Content-Security-Policy', $this->kebijakan());
        }

        return $respons;
    }

    private function kebijakan(): string
    {
        // Penyedia captcha yang sedang dipakai, bila ada, harus diizinkan
        // memuat skripnya DAN membingkai widget-nya. Tanpa ini, formulir
        // publik yang memakai captcha akan tampil kosong tanpa galat apa pun.
        $captcha = \App\Services\Captcha::sumberSkrip();
        $skrip = trim(self::SUMBER_LUAR['skrip'].' '.($captcha ?? ''));
        $bingkai = trim("'self'".($captcha ? ' '.$captcha : ''));

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' ".$skrip,
            "style-src 'self' 'unsafe-inline' ".self::SUMBER_LUAR['gaya'],
            // `data:` untuk gambar tempelan, `blob:` untuk pratinjau unggahan
            // sebelum berkasnya benar-benar terkirim.
            "img-src 'self' data: blob: ".self::SUMBER_LUAR['gambar'],
            "font-src 'self' data:",
            "connect-src 'self' ".$captcha,
            "media-src 'self'",
            "frame-src ".$bingkai,
            "object-src 'none'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
