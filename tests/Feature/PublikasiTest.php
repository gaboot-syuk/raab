<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Tag;
use App\Models\User;
use App\Services\Redaksi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Publikasi & alur redaksi.
 *
 * DUA HAL YANG PALING PENTING DIJAGA DI SINI:
 *  1. Naskah yang belum terbit TIDAK BOLEH muncul di halaman publik — baik
 *     draf, menunggu review, maupun artikel terjadwal yang waktunya belum tiba.
 *  2. Setiap perpindahan status meninggalkan jejak (revisi) dan memberi kabar
 *     kepada penulis.
 */
class PublikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
            SocialLinkSeeder::class,
        ]);
    }

    private function pengurus(string $peran): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function data(array $ganti = []): array
    {
        return array_merge([
            'tipe' => Article::TIPE_BERITA,
            'judul' => ['id' => 'Mapaba 2026 Berlangsung Meriah', 'en' => ''],
            'ringkasan' => ['id' => 'Kegiatan Mapaba tahun ini diikuti 120 peserta baru.', 'en' => ''],
            'konten' => ['id' => '<p>'.str_repeat('Mapaba berlangsung dengan penuh semangat. ', 6).'</p>', 'en' => ''],
            'tag' => 'mapaba, kaderisasi',
        ], $ganti);
    }

    /**
     * Buat artikel langsung lewat layanan (melewati HTTP).
     */
    private function artikel(User $penulis, array $ganti = []): Article
    {
        $artikel = new Article;

        return app(Redaksi::class)->simpan($artikel, $this->data($ganti), $penulis);
    }

    /* ====================== Publik ====================== */

    public function test_halaman_publikasi_dapat_dibuka(): void
    {
        $this->get('/publikasi')->assertOk();
    }

    public function test_artikel_terbit_tampil_di_halaman_publik(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);
        app(Redaksi::class)->terbitkan($artikel, $penulis);

        $this->get('/publikasi')->assertOk()->assertSee('Mapaba 2026 Berlangsung Meriah', false);
        $this->get('/publikasi/berita')->assertOk()->assertSee('Mapaba 2026 Berlangsung Meriah', false);
        $this->get('/publikasi/berita/'.$artikel->getTranslation('slug', 'id'))->assertOk();
    }

    public function test_draf_dan_naskah_menunggu_review_tidak_tampil_publik(): void
    {
        $penulis = $this->pengurus('konten_manager');

        $draf = $this->artikel($penulis, ['judul' => ['id' => 'Draf Rahasia Satu', 'en' => '']]);
        $menunggu = $this->artikel($penulis, ['judul' => ['id' => 'Naskah Menunggu Dua', 'en' => '']]);
        app(Redaksi::class)->kirimReview($menunggu, $penulis);

        $isi = (string) $this->get('/publikasi')->assertOk()->getContent();

        $this->assertStringNotContainsString('Draf Rahasia Satu', $isi);
        $this->assertStringNotContainsString('Naskah Menunggu Dua', $isi);

        // Detail draf pun tidak boleh dapat dibuka lewat URL slug-nya.
        $this->get('/publikasi/berita/'.$draf->getTranslation('slug', 'id'))->assertNotFound();
    }

    public function test_artikel_terjadwal_belum_tampil_sebelum_waktunya(): void
    {
        $penulis = $this->pengurus('konten_manager');

        $artikel = $this->artikel($penulis, [
            'judul' => ['id' => 'Terbit Pekan Depan', 'en' => ''],
            'dijadwalkan_pada' => now()->addWeek()->format('Y-m-d H:i'),
        ]);
        app(Redaksi::class)->terbitkan($artikel, $penulis);

        // Statusnya sudah "terbit", tetapi waktunya belum tiba.
        $this->assertSame(Article::STATUS_TERBIT, $artikel->fresh()->status);
        $this->get('/publikasi')->assertOk()->assertDontSee('Terbit Pekan Depan', false);
    }

    public function test_tipe_yang_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get('/publikasi/berita-acara')->assertNotFound();
        $this->get('/publikasi/ngawur')->assertNotFound();
    }

    /* ====================== Alur redaksi ====================== */

    public function test_kirim_review_mengubah_status_dan_menyimpan_revisi(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);

        $this->actingAs($penulis)
            ->post("/panel/artikel/{$artikel->id}/kirim")
            ->assertSessionHas('sukses');

        $this->assertSame(Article::STATUS_MENUNGGU, $artikel->fresh()->status);

        // Satu revisi saat dibuat + satu saat dikirim.
        $this->assertGreaterThanOrEqual(2, ArticleRevision::query()->where('article_id', $artikel->id)->count());
    }

    public function test_minta_revisi_mencatat_catatan(): void
    {
        $pengelola = $this->pengurus('konten_manager');
        $artikel = $this->artikel($pengelola);
        app(Redaksi::class)->kirimReview($artikel, $pengelola);

        $this->actingAs($pengelola)
            ->post("/panel/artikel/{$artikel->id}/revisi", ['catatan' => 'Perkuat bagian pembuka dan tambahkan data peserta.'])
            ->assertSessionHas('sukses');

        $artikel->refresh();
        $this->assertSame(Article::STATUS_REVISI, $artikel->status);
        $this->assertStringContainsString('pembuka', (string) $artikel->catatan_review);
    }

    public function test_penolakan_wajib_disertai_alasan(): void
    {
        $pengelola = $this->pengurus('konten_manager');
        $artikel = $this->artikel($pengelola);
        app(Redaksi::class)->kirimReview($artikel, $pengelola);

        $this->actingAs($pengelola)
            ->post("/panel/artikel/{$artikel->id}/tolak", ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(Article::STATUS_MENUNGGU, $artikel->fresh()->status);
    }

    public function test_terbitkan_mengisi_waktu_terbit_dan_waktu_baca(): void
    {
        $pengelola = $this->pengurus('konten_manager');
        $artikel = $this->artikel($pengelola);
        app(Redaksi::class)->kirimReview($artikel, $pengelola);

        $this->actingAs($pengelola)
            ->post("/panel/artikel/{$artikel->id}/terbitkan")
            ->assertSessionHas('sukses');

        $artikel->refresh();
        $this->assertSame(Article::STATUS_TERBIT, $artikel->status);
        $this->assertNotNull($artikel->terbit_pada);
        $this->assertGreaterThanOrEqual(1, $artikel->waktu_baca_menit);
    }

    public function test_revisi_dipangkas_pada_batas_maksimum(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);

        for ($i = 0; $i < Article::MAKS_REVISI + 5; $i++) {
            ArticleRevision::simpan($artikel, $penulis->id, 'Uji pemangkasan '.$i);
        }

        $this->assertLessThanOrEqual(
            Article::MAKS_REVISI,
            ArticleRevision::query()->where('article_id', $artikel->id)->count(),
        );
    }

    /* ====================== Izin & penyimpanan ====================== */

    public function test_pengguna_tanpa_izin_tidak_dapat_membuka_publikasi_panel(): void
    {
        $biasa = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($biasa)->get('/panel/artikel')->assertForbidden();
    }

    public function test_konten_manager_dapat_membuka_publikasi_panel(): void
    {
        $this->actingAs($this->pengurus('konten_manager'))->get('/panel/artikel')->assertOk();
    }

    public function test_slug_dibuat_otomatis_dan_unik(): void
    {
        $penulis = $this->pengurus('konten_manager');

        $satu = $this->artikel($penulis, ['judul' => ['id' => 'Judul Sama', 'en' => '']]);
        $dua = $this->artikel($penulis, ['judul' => ['id' => 'Judul Sama', 'en' => '']]);

        $this->assertSame('judul-sama', $satu->getTranslation('slug', 'id'));
        $this->assertSame('judul-sama-2', $dua->getTranslation('slug', 'id'));
    }

    public function test_tag_dibuat_otomatis_dari_isi_kolom(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);

        $this->assertSame(2, $artikel->tags()->count());
        $this->assertDatabaseHas('tags', ['dipakai' => 1]);
        $this->assertInstanceOf(Tag::class, Tag::query()->first());
    }

    public function test_berita_acara_wajib_memuat_nomor_dokumen(): void
    {
        $sekretaris = $this->pengurus('sekretaris');

        $this->actingAs($sekretaris)
            ->post('/panel/artikel', $this->data([
                'tipe' => Article::TIPE_BERITA_ACARA,
                'nomor_dokumen' => '',
                'tanggal_agenda' => '',
                'agenda' => '',
                'keputusan' => '',
                'penandatangan' => '',
                'jabatan_penandatangan' => '',
            ]))
            ->assertSessionHasErrors(['nomor_dokumen', 'agenda', 'keputusan', 'penandatangan']);
    }

    public function test_berita_acara_tidak_punya_halaman_publik(): void
    {
        $penulis = $this->pengurus('konten_manager');

        $artikel = $this->artikel($penulis, [
            'tipe' => Article::TIPE_BERITA_ACARA,
            'nomor_dokumen' => '012/BA/RAAB/IX/2026',
            'tanggal_agenda' => now()->format('Y-m-d'),
            'agenda' => 'Pembahasan program kerja semester ganjil.',
            'keputusan' => 'Program kerja disetujui dengan catatan revisi anggaran.',
            'penandatangan' => 'Ahmad Fauzi',
            'jabatan_penandatangan' => 'Sekretaris Rayon',
        ]);
        app(Redaksi::class)->terbitkan($artikel, $penulis);

        // Berita acara bukan bagian dari lima tipe publik.
        $this->get('/publikasi/berita_acara')->assertNotFound();
        $this->get('/publikasi')->assertOk()->assertDontSee('012/BA/RAAB/IX/2026', false);
    }

    public function test_pencarian_belum_diterjemahkan_menemukan_artikel_terbit(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);
        app(Redaksi::class)->terbitkan($artikel, $penulis);

        // Belum ada versi Inggris → harus terhitung.
        $this->assertSame(1, Article::query()->belumDiterjemahkan()->count());
    }
}
