<?php

namespace Database\Seeders;

use App\Models\SocialLink;
use Illuminate\Database\Seeder;

/**
 * Tautan media sosial untuk footer & halaman "Media Sosial".
 *
 * URL diisi hanya bila akun resminya sudah diserahkan pengurus. Yang sudah
 * diketahui: Instagram, Twitter (X), dan kanal YouTube "PMII RAYON FAB" —
 * ketiganya sudah diperiksa benar-benar hidup sebelum ditulis di sini, karena
 * tautan mati lebih buruk daripada tautan yang jujur belum diisi. Platform
 * lainnya tetap ditampilkan sebagai "akun belum ditautkan".
 *
 * PENTING: URL yang sudah terisi TIDAK ditimpa saat seeder berjalan ulang.
 * Belum ada halaman panel untuk menyunting tautan ini, jadi tanpa aturan itu
 * setiap deploy akan mengembalikan tautan ke nilai di berkas ini.
 */
class SocialLinkSeeder extends Seeder
{
    public function run(): void
    {
        // platform, label, ikon, urutan, URL (null = belum diserahkan)
        $daftar = [
            ['instagram', 'Instagram', 'instagram', 1, 'https://www.instagram.com/pmiirayonfab/'],
            ['twitter', 'Twitter', 'x', 2, 'https://x.com/pmiirayonfab.ofc'],
            // Label tetap nama platform agar sejajar dengan kartu lain; nama
            // kanal ("PMII RAYON FAB") terlihat begitu tautannya dibuka.
            ['youtube', 'YouTube', 'youtube', 3, 'https://www.youtube.com/@pmiirayonfab4144'],
            ['facebook', 'Facebook', 'facebook', 4, null],
            ['tiktok', 'TikTok', 'tiktok', 5, null],
            ['whatsapp', 'WhatsApp', 'whatsapp', 6, null],
        ];

        foreach ($daftar as [$platform, $label, $ikon, $urutan, $url]) {
            $tautan = SocialLink::query()->firstOrNew(['platform' => $platform]);

            $tautan->fill([
                'label' => ['id' => $label, 'en' => $label],
                'ikon' => $ikon,
                'urutan' => $urutan,
                'aktif' => true,
            ]);

            if (blank($tautan->url) && filled($url)) {
                $tautan->url = $url;
            }

            $tautan->save();
        }

        SocialLink::lupakan();

        $terisi = SocialLink::query()->whereNotNull('url')->where('url', '!=', '')->count();

        $this->command?->info(
            "Tautan media sosial siap: {$terisi} dari ".count($daftar).' platform sudah bertaut.'
        );
    }
}
