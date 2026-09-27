<?php

namespace Database\Seeders;

use App\Models\SocialLink;
use Illuminate\Database\Seeder;

/**
 * Tautan media sosial untuk footer & halaman "Media Sosial".
 * URL sengaja dibiarkan kosong (placeholder) sampai akun resmi diserahkan.
 */
class SocialLinkSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            ['instagram', 'Instagram', 'instagram', 1],
            ['facebook', 'Facebook', 'facebook', 2],
            ['youtube', 'YouTube', 'youtube', 3],
            ['tiktok', 'TikTok', 'tiktok', 4],
            ['whatsapp', 'WhatsApp', 'whatsapp', 5],
        ];

        foreach ($daftar as [$platform, $label, $ikon, $urutan]) {
            SocialLink::query()->updateOrCreate(
                ['platform' => $platform],
                [
                    'label' => ['id' => $label, 'en' => $label],
                    'url' => null,
                    'ikon' => $ikon,
                    'urutan' => $urutan,
                    'aktif' => true,
                ],
            );
        }

        $this->command?->info('Tautan media sosial siap: '.count($daftar).' platform (URL menyusul).');
    }
}
