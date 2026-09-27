<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use App\Services\Keanggotaan;
use App\Services\Redaksi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submisi karya oleh kader & alumni, serta tampilnya berita di beranda.
 *
 * DUA HAL YANG DIJAGA:
 *  1. Yang boleh menulis adalah anggota TERVERIFIKASI — bukan sekadar punya akun.
 *  2. Kader hanya dapat menyunting karyanya sendiri, dan tidak dapat
 *     menerbitkannya (itu wewenang Konten Manager).
 */
class KaryaKaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            UnitSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
            SocialLinkSeeder::class,
        ]);
    }

    /**
     * Anggota yang sudah diverifikasi (kader aktif) — tanpa peran Spatie.
     */
    private function kader(string $nama = 'Nurul Hidayah'): User
    {
        $user = User::factory()->create(['name' => $nama, 'email_verified_at' => now()]);

        $pengajuan = MemberApplication::query()->create([
            'user_id' => $user->id,
            'jalur' => Member::JALUR_KADER,
            'status' => MemberApplication::STATUS_MENUNGGU,
            'data' => ['nama_lengkap' => $nama],
        ]);

        app(Keanggotaan::class)->setujui($pengajuan, $this->pengelola());

        return $user->fresh();
    }

    private function pengelola(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('konten_manager');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(array $ganti = []): array
    {
        return array_merge([
            'tipe' => Article::TIPE_OPINI,
            'judul' => ['id' => 'Menimbang Ulang Politik Kampus', 'en' => ''],
            'ringkasan' => ['id' => 'Catatan seorang kader tentang ruang diskusi di kampus.', 'en' => ''],
            'konten' => ['id' => '<p>'.str_repeat('Politik kampus bukan sekadar ajang kekuasaan. ', 5).'</p>', 'en' => ''],
            'tag' => 'literasi, opini',
        ], $ganti);
    }

    /* ====================== Akses ====================== */

    public function test_anggota_terverifikasi_dapat_membuka_halaman_karya(): void
    {
        $this->actingAs($this->kader())->get('/karya')->assertOk();
    }

    public function test_anggota_belum_terverifikasi_tidak_dapat_menulis(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/karya/baru')->assertForbidden();
        $this->actingAs($user)->post('/karya', $this->data())->assertForbidden();

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get('/karya')->assertRedirect('/login');
    }

    /* ====================== Menulis ====================== */

    public function test_kader_dapat_menyimpan_karya_sebagai_draf(): void
    {
        $kader = $this->kader();

        $this->actingAs($kader)->post('/karya', $this->data())->assertRedirect();

        $artikel = Article::query()->firstOrFail();

        // Penulisnya adalah kader, dan statusnya SELALU draf — kader tidak dapat
        // menerbitkan sendiri.
        $this->assertSame($kader->id, $artikel->user_id);
        $this->assertSame(Article::STATUS_DRAF, $artikel->status);
        $this->assertSame('', (string) $artikel->terbit_pada);
    }

    public function test_kader_tidak_dapat_menerbitkan_atas_nama_orang_lain(): void
    {
        $kader = $this->kader();
        $lain = $this->kader('Kader Lain');

        // Menyisipkan user_id orang lain pada formulir harus diabaikan.
        $this->actingAs($kader)->post('/karya', [
            ...$this->data(),
            'user_id' => $lain->id,
            'status' => Article::STATUS_TERBIT,
        ]);

        $artikel = Article::query()->firstOrFail();
        $this->assertSame($kader->id, $artikel->user_id);
        $this->assertSame(Article::STATUS_DRAF, $artikel->status);
    }

    public function test_kader_dapat_mengirim_karyanya_untuk_review(): void
    {
        $kader = $this->kader();
        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, $this->data(['user_id' => $kader->id]), $kader);

        $this->actingAs($kader)
            ->post("/karya/{$artikel->id}/kirim")
            ->assertRedirect(route('anggota.karya'));

        $this->assertSame(Article::STATUS_MENUNGGU, $artikel->fresh()->status);
    }

    public function test_kader_tidak_dapat_menyunting_karya_orang_lain(): void
    {
        $pemilik = $this->kader('Pemilik Karya');
        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, $this->data(['user_id' => $pemilik->id]), $pemilik);

        $lain = $this->kader('Kader Lain');

        $this->actingAs($lain)->get("/karya/{$artikel->id}")->assertForbidden();
        $this->actingAs($lain)->put("/karya/{$artikel->id}", $this->data())->assertForbidden();
    }

    public function test_karya_yang_sudah_terbit_tidak_dapat_disunting_kader(): void
    {
        $kader = $this->kader();
        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, $this->data(['user_id' => $kader->id]), $kader);
        app(Redaksi::class)->terbitkan($artikel, $this->pengelola());

        $this->actingAs($kader)
            ->put("/karya/{$artikel->id}", $this->data(['judul' => ['id' => 'Diubah Paksa', 'en' => '']]))
            ->assertForbidden();

        $this->assertNotSame('Diubah Paksa', $artikel->fresh()->getTranslation('judul', 'id'));
    }

    /* ====================== Beranda ====================== */

    public function test_beranda_menampilkan_berita_yang_sudah_terbit(): void
    {
        $kader = $this->kader();
        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, $this->data(['user_id' => $kader->id, 'judul' => ['id' => 'Kabar Kegiatan Rayon', 'en' => '']]), $kader);
        app(Redaksi::class)->terbitkan($artikel, $this->pengelola());

        $this->get('/')->assertOk()->assertSee('Kabar Kegiatan Rayon', false);
    }

    public function test_beranda_tidak_menampilkan_draf(): void
    {
        $kader = $this->kader();
        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, $this->data(['user_id' => $kader->id, 'judul' => ['id' => 'Draf Belum Tayang', 'en' => '']]), $kader);

        $this->get('/')->assertOk()->assertDontSee('Draf Belum Tayang', false);
    }

    public function test_beranda_menampilkan_ajakan_saat_belum_ada_berita(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('umum.beranda.berita_kosong'));
    }

    /* ====================== Batas wewenang Sekretaris ====================== */

    public function test_sekretaris_dapat_menerbitkan_berita_acara(): void
    {
        $sekretaris = User::factory()->create(['email_verified_at' => now()]);
        $sekretaris->assignRole('sekretaris');

        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, [
            'tipe' => Article::TIPE_BERITA_ACARA,
            'judul' => ['id' => 'Berita Acara Rapat Kerja', 'en' => ''],
            'konten' => ['id' => '<p>Rapat kerja membahas program semester.</p>', 'en' => ''],
            'nomor_dokumen' => '012/BA/RAAB/IX/2026',
            'tanggal_agenda' => now()->format('Y-m-d'),
            'agenda' => 'Pembahasan program kerja semester ganjil.',
            'keputusan' => 'Program kerja disetujui.',
            'penandatangan' => 'Ahmad Fauzi',
            'jabatan_penandatangan' => 'Sekretaris Rayon',
        ], $sekretaris);

        $this->actingAs($sekretaris)
            ->post("/panel/artikel/{$artikel->id}/terbitkan-berita-acara")
            ->assertSessionHas('sukses');

        $this->assertSame(Article::STATUS_TERBIT, $artikel->fresh()->status);
    }

    public function test_sekretaris_tidak_dapat_menerbitkan_artikel_biasa(): void
    {
        $sekretaris = User::factory()->create(['email_verified_at' => now()]);
        $sekretaris->assignRole('sekretaris');

        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, $this->data(), $this->pengelola());

        // Rute terbitkan biasa dijaga izin articles.publish yang tidak dimiliki
        // Sekretaris — ia tidak boleh melompati alur review.
        $this->actingAs($sekretaris)
            ->post("/panel/artikel/{$artikel->id}/terbitkan")
            ->assertForbidden();

        $this->assertSame(Article::STATUS_DRAF, $artikel->fresh()->status);
    }

    public function test_berita_acara_tidak_dapat_menerbit_lewat_rute_artikel_biasa(): void
    {
        $pengelola = $this->pengelola();

        $artikel = new Article;
        app(Redaksi::class)->simpan($artikel, [
            'tipe' => Article::TIPE_BERITA_ACARA,
            'judul' => ['id' => 'Berita Acara Lain', 'en' => ''],
            'konten' => ['id' => '<p>Isi berita acara.</p>', 'en' => ''],
            'nomor_dokumen' => '013/BA/RAAB/IX/2026',
            'tanggal_agenda' => now()->format('Y-m-d'),
            'agenda' => 'Agenda lain.',
            'keputusan' => 'Keputusan lain.',
            'penandatangan' => 'Ahmad Fauzi',
            'jabatan_penandatangan' => 'Sekretaris Rayon',
        ], $pengelola);

        // Konten Manager pun harus memakai rute berita acara, bukan rute biasa.
        $this->actingAs($pengelola)
            ->post("/panel/artikel/{$artikel->id}/terbitkan")
            ->assertForbidden();
    }
}
