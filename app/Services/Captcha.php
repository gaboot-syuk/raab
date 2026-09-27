<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Captcha untuk formulir publik.
 *
 * SATU PINTU untuk tiga penyedia yang berbeda. Formulir publik tidak perlu tahu
 * penyedia mana yang dipakai — ia hanya menanyakan dua hal: aturan validasi apa
 * yang harus ditambahkan, dan widget apa yang harus dirender.
 *
 * BAWAANNYA MATI (`none`). Bukan kelalaian: formulir pendaftaran, aspirasi, dan
 * pesan publik harus tetap bisa dipakai di lingkungan pengembangan dan
 * pengujian tanpa kunci dari layanan luar, dan memaksa captcha menyala berarti
 * seluruh uji itu harus menembus jaringan ke pihak ketiga.
 *
 * Yang menjadi pertahanan pertama formulir publik tetaplah yang tidak
 * bergantung pada pihak luar: honeypot, pembatasan laju, dan pemeriksaan di
 * sisi server. Captcha adalah lapisan TAMBAHAN.
 *
 * KEGAGALAN LAYANAN CAPTCHA TIDAK PERNAH DIPERLAKUKAN SEBAGAI "LOLOS". Kalau
 * penyedianya tidak bisa dihubungi, permintaan DITOLAK dengan pesan yang jujur.
 * Membiarkan kiriman lewat saat pemeriksaannya gagal berarti captcha hanya
 * berfungsi tepat pada saat ia tidak dibutuhkan.
 */
final class Captcha
{
    public const NONE = 'none';

    public const TURNSTILE = 'turnstile';

    public const HCAPTCHA = 'hcaptcha';

    public const RECAPTCHA = 'recaptcha';

    /**
     * @var array<string, string>
     */
    public const PENYEDIA = [
        self::NONE => 'Tidak dipakai',
        self::TURNSTILE => 'Cloudflare Turnstile (disarankan)',
        self::HCAPTCHA => 'hCaptcha',
        self::RECAPTCHA => 'Google reCAPTCHA v2',
    ];

    /**
     * Nama kolom pada formulir. Satu nama untuk semua penyedia supaya tata
     * kelola formulir tidak perlu bercabang.
     */
    public const KOLOM = 'captcha';

    public static function penyedia(): string
    {
        $penyedia = (string) config('services.captcha.penyedia', self::NONE);

        return array_key_exists($penyedia, self::PENYEDIA) ? $penyedia : self::NONE;
    }

    public static function kunciSitus(): ?string
    {
        $kunci = config('services.captcha.kunci_situs');

        return $kunci !== null && $kunci !== '' ? (string) $kunci : null;
    }

    private static function kunciRahasia(): ?string
    {
        $kunci = config('services.captcha.kunci_rahasia');

        return $kunci !== null && $kunci !== '' ? (string) $kunci : null;
    }

    /**
     * Captcha benar-benar aktif hanya bila penyedianya dipilih DAN kuncinya ada.
     *
     * Memilih penyedia tanpa mengisi kuncinya adalah keadaan yang mudah terjadi
     * saat menyiapkan hosting. Kalau keadaan itu dianggap "aktif", seluruh
     * formulir publik langsung mati. Karena itu keadaan setengah terpasang
     * dianggap TIDAK aktif, dan dicatat di log supaya ketahuan.
     */
    public static function aktif(): bool
    {
        if (self::penyedia() === self::NONE) {
            return false;
        }

        if (self::kunciSitus() === null || self::kunciRahasia() === null) {
            Log::warning('Captcha dipilih tetapi kuncinya belum lengkap; captcha dianggap tidak aktif.', [
                'penyedia' => self::penyedia(),
                'kunci_situs' => self::kunciSitus() !== null,
                'kunci_rahasia' => self::kunciRahasia() !== null,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Aturan validasi yang harus ditambahkan pada formulir publik.
     *
     * @return array<int, mixed>
     */
    public static function aturan(): array
    {
        return self::aktif()
            ? ['required', 'string', new \App\Rules\Captcha]
            : ['nullable'];
    }

    private static function urlVerifikasi(): string
    {
        return match (self::penyedia()) {
            self::TURNSTILE => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            self::HCAPTCHA => 'https://hcaptcha.com/siteverify',
            self::RECAPTCHA => 'https://www.google.com/recaptcha/api/siteverify',
            default => '',
        };
    }

    /**
     * Periksa token yang dikirim pengunjung ke layanan captcha.
     *
     * @return array{berhasil: bool, pesan: string}
     */
    public static function periksa(?string $token, ?string $ip = null): array
    {
        if (! self::aktif()) {
            return ['berhasil' => true, 'pesan' => ''];
        }

        if ($token === null || trim($token) === '') {
            return ['berhasil' => false, 'pesan' => 'Penyaringan captcha belum diselesaikan.'];
        }

        try {
            $respons = Http::asForm()
                ->timeout(10)
                ->post(self::urlVerifikasi(), array_filter([
                    'secret' => self::kunciRahasia(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Layanan captcha tidak dapat dihubungi.', ['penyedia' => self::penyedia(), 'galat' => $e->getMessage()]);

            // Gagal menghubungi layanan TIDAK berarti lolos.
            return ['berhasil' => false, 'pesan' => 'Penyaringan captcha sedang tidak dapat diperiksa. Coba lagi sebentar lagi.'];
        }

        if (! $respons->successful()) {
            Log::warning('Layanan captcha menolak permintaan.', ['penyedia' => self::penyedia(), 'status' => $respons->status()]);

            return ['berhasil' => false, 'pesan' => 'Penyaringan captcha sedang tidak dapat diperiksa. Coba lagi sebentar lagi.'];
        }

        return $respons->json('success') === true
            ? ['berhasil' => true, 'pesan' => '']
            : ['berhasil' => false, 'pesan' => 'Penyaringan captcha menyatakan kiriman ini bukan dari manusia. Ulangi pemeriksaannya.'];
    }

    /**
     * Sumber widget yang perlu diizinkan CSP, bila ada.
     */
    public static function sumberSkrip(): ?string
    {
        return match (self::penyedia()) {
            self::TURNSTILE => 'https://challenges.cloudflare.com',
            self::HCAPTCHA => 'https://js.hcaptcha.com',
            self::RECAPTCHA => 'https://www.google.com',
            default => null,
        };
    }
}
