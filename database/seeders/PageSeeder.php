<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Tiga halaman tetap: Sejarah, Visi & Misi, Sambutan.
 *
 * Isinya masih placeholder dwibahasa (id + en) agar Konten Manager
 * tinggal menyunting lewat Panel Pengurus.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $halaman = [
            [
                'kunci' => Page::TIPE_SEJARAH,
                'tipe' => Page::TIPE_SEJARAH,
                'judul_id' => 'Sejarah',
                'judul_en' => 'History',
                'ringkasan_id' => 'Jejak perjalanan PMII Rayon Ali Ahmad Baktsir.',
                'ringkasan_en' => 'The journey of PMII Rayon Ali Ahmad Baktsir.',
                'konten_id' => '<p>Naskah sejarah rayon sedang disusun oleh pengurus. Bagian ini akan memuat latar berdirinya PMII Rayon Ali Ahmad Baktsir, tokoh-tokoh pendiri, serta tonggak perjalanan dari masa ke masa.</p><p><em>Catatan: isi ini masih contoh dan akan diperbarui.</em></p>',
                'konten_en' => '<p>The rayon history is being compiled by the board. This section will cover the founding of PMII Rayon Ali Ahmad Baktsir, its early figures, and its milestones over the years.</p><p><em>Note: this content is a sample and will be updated.</em></p>',
            ],
            [
                'kunci' => Page::TIPE_VISI_MISI,
                'tipe' => Page::TIPE_VISI_MISI,
                'judul_id' => 'Visi & Misi',
                'judul_en' => 'Vision & Mission',
                'ringkasan_id' => 'Arah gerak dan komitmen rayon.',
                'ringkasan_en' => 'The direction and commitment of the rayon.',
                'konten_id' => '<h2>Visi</h2><p>Terbentuknya kader yang berilmu, berakhlak, dan mampu menjadi pelopor perubahan di masyarakat.</p><h2>Misi</h2><ul><li>Menyelenggarakan kaderisasi yang terencana dan berkelanjutan.</li><li>Mengembangkan tradisi intelektual melalui kajian, diskusi, dan penerbitan.</li><li>Menguatkan peran sosial rayon melalui advokasi dan pengabdian.</li><li>Membangun jejaring alumni yang aktif berkontribusi.</li></ul><p><em>Catatan: isi ini masih contoh dan akan diperbarui.</em></p>',
                'konten_en' => '<h2>Vision</h2><p>To shape cadres who are knowledgeable, principled, and capable of leading change in society.</p><h2>Mission</h2><ul><li>To run planned and continuous cadre development.</li><li>To build an intellectual tradition through studies, discussions, and publishing.</li><li>To strengthen the rayon\'s social role through advocacy and service.</li><li>To build an active and contributing alumni network.</li></ul><p><em>Note: this content is a sample and will be updated.</em></p>',
            ],
            [
                'kunci' => Page::TIPE_SAMBUTAN,
                'tipe' => Page::TIPE_SAMBUTAN,
                'judul_id' => 'Sambutan Ketua Rayon',
                'judul_en' => 'Message from the Rayon Chair',
                'ringkasan_id' => 'Sambutan resmi Ketua Rayon Ali Ahmad Baktsir.',
                'ringkasan_en' => 'An official message from the Chair of Rayon Ali Ahmad Baktsir.',
                'konten_id' => '<p>Assalamu\'alaikum warahmatullahi wabarakatuh.</p><p>Selamat datang di rumah digital PMII Rayon Ali Ahmad Baktsir. Situs ini kami siapkan sebagai ruang bersama: tempat kader berkarya, alumni kembali bersapa, dan siapa pun mengenal arah gerak kami.</p><p>Terima kasih atas kunjungan Anda. Mari bergerak bersama, tumbuh bersama.</p><p><em>Catatan: isi sambutan masih contoh dan akan diperbarui oleh Ketua Rayon.</em></p>',
                'konten_en' => '<p>Peace be upon you all.</p><p>Welcome to the digital home of PMII Rayon Ali Ahmad Baktsir. We built this site as a shared space: where cadres create, alumni reconnect, and anyone can learn about our direction.</p><p>Thank you for visiting. Let us move and grow together.</p><p><em>Note: this message is a sample and will be updated by the Chair.</em></p>',
            ],
        ];

        foreach ($halaman as $data) {
            $page = Page::query()->updateOrCreate(
                ['kunci' => $data['kunci']],
                [
                    'tipe' => $data['tipe'],
                    'judul' => $data['judul_id'],
                    'ringkasan' => $data['ringkasan_id'],
                    'konten' => $data['konten_id'],
                    'slug' => ['id' => $data['kunci'], 'en' => $data['kunci']],
                    'status' => Page::STATUS_TERBIT,
                    'terbit_pada' => now(),
                ],
            );

            $page->setTranslations('judul', ['id' => $data['judul_id'], 'en' => $data['judul_en']]);
            $page->setTranslations('ringkasan', ['id' => $data['ringkasan_id'], 'en' => $data['ringkasan_en']]);
            $page->setTranslations('konten', ['id' => $data['konten_id'], 'en' => $data['konten_en']]);
            $page->save();
        }

        $this->command?->info('Halaman statis siap: '.count($halaman).' halaman (dwibahasa).');
    }
}
