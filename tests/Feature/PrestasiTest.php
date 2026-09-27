<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Models\ContributionPoint;
use App\Models\Member;
use App\Models\User;
use App\Services\Kontribusi;
use App\Services\Prestasi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Prestasi kader.
 *
 * Yang diuji adalah janji-janjinya:
 *  - klaim kader BELUM TENTANG dianggap benar, jadi belum tayang dan belum berpoin,
 *  - poin menyusul keputusan pengurus, dan DICABUT bila verifikasinya dibatalkan,
 *  - yang sudah terverifikasi tidak bisa dihapus, hanya ditolak beserta alasan,
 *  - halaman publik tidak pernah membocorkan klaim yang belum diperiksa.
 */
class PrestasiTest extends TestCase
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

    /* ===================== Helper ===================== */

    private function sekretaris(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('sekretaris');

        return $user;
    }

    private function kader(string $nama = 'Siti Aminah'): Member
    {
        $user = User::factory()->create([
            'name' => $nama,
            'email' => Str::slug($nama).'@contoh.test',
            'email_verified_at' => now(),
        ]);

        $member = new Member;
        $member->user_id = $user->id;
        $member->nama_lengkap = $nama;
        $member->status = Member::STATUS_AKTIF;
        $member->jalur = Member::JALUR_KADER;
        $member->nomor_anggota = 'A-RAAB-2026-'.str_pad((string) (Member::query()->count() + 1), 3, '0', STR_PAD_LEFT);
        $member->save();

        return $member;
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function ajukan(Member $anggota, array $ganti = []): Achievement
    {
        return app(Prestasi::class)->ajukan($anggota, array_merge([
            'judul' => 'Juara 1 Lomba Karya Tulis',
            'deskripsi' => 'Menulis tentang politik kampus.',
            'tingkat' => Achievement::TINGKAT_KAMPUS,
            'peringkat' => Achievement::PERINGKAT_JUARA_1,
            'tanggal' => '2026-09-10',
            'penyelenggara' => 'Fakultas Dakwah',
        ], $ganti), $anggota->user);
    }

    /* ===================== Klaim belum tentang ===================== */

    public function test_prestasi_yang_diajukan_belum_tayang_publik_dan_belum_berpoin(): void
    {
        $kader = $this->kader();

        $prestasi = $this->ajukan($kader);

        $this->assertSame(Achievement::STATUS_DIAJUKAN, $prestasi->status);
        $this->assertFalse($prestasi->terverifikasi());
        $this->assertSame(0, ContributionPoint::query()->count());
        $this->assertSame(0, Achievement::query()->tayangPublik()->count());
    }

    public function test_tanggal_prestasi_tidak_boleh_di_masa_depan(): void
    {
        $kader = $this->kader();

        $this->expectException(ValidationException::class);
        $this->ajukan($kader, ['tanggal' => now()->addMonth()->toDateString()]);
    }

    /* ===================== Verifikasi ===================== */

    public function test_verifikasi_menayangkan_prestasi_dan_memberi_poin(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader, ['tingkat' => Achievement::TINGKAT_NASIONAL]);

        app(Prestasi::class)->verifikasi($prestasi, $petugas);

        $this->assertTrue($prestasi->fresh()->terverifikasi());
        $this->assertSame(1, Achievement::query()->tayangPublik()->count());

        // 8 poin untuk tingkat nasional, sesuai peta di model.
        $this->assertSame(8, app(Kontribusi::class)->total($kader));

        $baris = ContributionPoint::query()->firstOrFail();
        $this->assertSame(ContributionPoint::SUMBER_PRESTASI, $baris->sumber);
        $this->assertStringContainsString('Juara 1', $baris->keterangan);
    }

    public function test_peringkat_peserta_tidak_mendapat_poin(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        // Sekadar ikut serta bukan prestasi — kalau peserta diberi poin, poin
        // berhenti mengukur apa pun.
        $prestasi = $this->ajukan($kader, ['peringkat' => Achievement::PERINGKAT_PESERTA]);

        app(Prestasi::class)->verifikasi($prestasi, $petugas);

        $this->assertTrue($prestasi->fresh()->terverifikasi());
        $this->assertSame(0, ContributionPoint::query()->count());
        $this->assertSame(0, app(Kontribusi::class)->total($kader));

        // Tetap tayang — ia memang berprestasi, hanya tidak berpoin.
        $this->assertSame(1, Achievement::query()->tayangPublik()->count());
    }

    public function test_verifikasi_dua_kali_tidak_menggandakan_poin(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);
        $layanan = app(Prestasi::class);

        $layanan->verifikasi($prestasi, $petugas);

        try {
            $layanan->verifikasi($prestasi->fresh(), $petugas);
            $this->fail('Verifikasi ulang seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah terverifikasi', collect($e->errors())->flatten()->first());
        }

        $this->assertSame(1, ContributionPoint::query()->count());
        $this->assertSame(4, app(Kontribusi::class)->total($kader));
    }

    /* ===================== Penolakan & pencabutan ===================== */

    public function test_penolakan_wajib_beralasan(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);

        try {
            app(Prestasi::class)->tolak($prestasi, $petugas, '   ');
            $this->fail('Penolakan tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Alasan', collect($e->errors())->flatten()->first());
        }

        $this->assertSame(Achievement::STATUS_DIAJUKAN, $prestasi->fresh()->status);
    }

    public function test_membatalkan_verifikasi_mencabut_poin_dan_menurunkannya_dari_publik(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);
        $layanan = app(Prestasi::class);

        $layanan->verifikasi($prestasi, $petugas);
        $this->assertSame(4, app(Kontribusi::class)->total($kader));

        $layanan->tolak($prestasi->fresh(), $petugas, 'Sertifikat tidak terbaca.');

        $this->assertSame(0, app(Kontribusi::class)->total($kader));
        $this->assertSame(0, Achievement::query()->tayangPublik()->count());
        // Baris poinnya TIDAK dihapus — hanya dibatalkan.
        $this->assertSame(1, ContributionPoint::query()->count());
        $this->assertTrue(ContributionPoint::query()->firstOrFail()->dibatalkan());
    }

    public function test_prestasi_yang_ditolak_kehilangan_status_unggulan(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);
        $layanan = app(Prestasi::class);

        $layanan->verifikasi($prestasi, $petugas);
        $layanan->jadikanUnggulan($prestasi->fresh(), true, $petugas);
        $this->assertTrue($prestasi->fresh()->unggulan);

        $layanan->tolak($prestasi->fresh(), $petugas, 'Sertifikat milik orang lain.');

        $this->assertFalse($prestasi->fresh()->unggulan);
    }

    /* ===================== Unggulan ===================== */

    public function test_hanya_prestasi_terverifikasi_yang_bisa_diunggulkan(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);

        // Kalau klaim yang belum diperiksa bisa diunggulkan, halaman depan rayon
        // menayangkan sesuatu yang belum terbukti.
        $this->expectException(ValidationException::class);
        app(Prestasi::class)->jadikanUnggulan($prestasi, true, $petugas);
    }

    public function test_unggulan_hanya_memuat_yang_terverifikasi_dan_tampil(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $layanan = app(Prestasi::class);

        $terverifikasi = $this->ajukan($kader, ['judul' => 'Juara Nasional Debat']);
        $menunggu = $this->ajukan($kader, ['judul' => 'Klaim Belum Diperiksa']);

        $layanan->verifikasi($terverifikasi, $petugas);
        $layanan->jadikanUnggulan($terverifikasi->fresh(), true, $petugas);

        $unggulan = $layanan->unggulan();

        $this->assertCount(1, $unggulan);
        $this->assertSame('Juara Nasional Debat', $unggulan->first()->judulTeks());
        $this->assertNotNull($menunggu->id);
    }

    /* ===================== Sakelar milik kader ===================== */

    public function test_prestasi_yang_disembunyikan_kader_tidak_tayang_tetapi_poinnya_tetap(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);
        $layanan = app(Prestasi::class);

        $layanan->verifikasi($prestasi, $petugas);
        $layanan->aturTampilPublik($prestasi->fresh(), false);

        // Ini keputusan PEMILIK DATA, bukan pengurus: yang berubah hanya
        // penayangannya, bukan pengakuannya.
        $this->assertSame(0, Achievement::query()->tayangPublik()->count());
        $this->assertSame(4, app(Kontribusi::class)->total($kader));
    }

    /* ===================== Hapus vs tolak ===================== */

    public function test_pengajuan_yang_belum_diperiksa_dapat_dihapus(): void
    {
        $kader = $this->kader();
        $prestasi = $this->ajukan($kader);

        app(Prestasi::class)->hapusPengajuan($prestasi);

        $this->assertSame(0, Achievement::query()->count());
    }

    public function test_prestasi_yang_sudah_diperiksa_tidak_dapat_dihapus(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);
        $layanan = app(Prestasi::class);

        $layanan->verifikasi($prestasi, $petugas);

        // Menghapusnya akan menghapus jejak verifikasi sekaligus poinnya.
        $this->expectException(ValidationException::class);
        $layanan->hapusPengajuan($prestasi->fresh());
    }

    /* ===================== Rekap ===================== */

    public function test_rekap_menghitung_hanya_yang_terverifikasi_per_tingkat(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $layanan = app(Prestasi::class);

        $kampus = $this->ajukan($kader, ['tingkat' => Achievement::TINGKAT_KAMPUS]);
        $nasional = $this->ajukan($kader, ['tingkat' => Achievement::TINGKAT_NASIONAL]);
        $this->ajukan($kader, ['tingkat' => Achievement::TINGKAT_INTERNASIONAL]);

        $layanan->verifikasi($kampus, $petugas);
        $layanan->verifikasi($nasional, $petugas);

        $rekap = $layanan->rekap($kader);

        $this->assertSame(2, $rekap['total']);
        $this->assertSame(1, $rekap['per_tingkat']['Tingkat Kampus']);
        $this->assertSame(1, $rekap['per_tingkat']['Tingkat Nasional']);
        $this->assertSame(0, $rekap['per_tingkat']['Tingkat Internasional']);
    }

    /* ===================== Lewat HTTP ===================== */

    public function test_halaman_publik_prestasi_hanya_menampilkan_yang_terverifikasi(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $layanan = app(Prestasi::class);

        $tayang = $this->ajukan($kader, ['judul' => 'Juara Debat Nasional']);
        $this->ajukan($kader, ['judul' => 'Klaim Belum Diperiksa']);
        $ditolak = $this->ajukan($kader, ['judul' => 'Klaim Yang Ditolak']);

        $layanan->verifikasi($tayang, $petugas);
        $layanan->tolak($ditolak, $petugas, 'Sertifikat tidak jelas.');

        $respons = $this->get('/prestasi');

        $respons->assertOk();
        $respons->assertSee('Juara Debat Nasional', false);
        // Klaim yang belum diperiksa dan yang ditolak TIDAK PERNAH bocor.
        $respons->assertDontSee('Klaim Belum Diperiksa', false);
        $respons->assertDontSee('Klaim Yang Ditolak', false);
    }

    public function test_profil_kader_menampilkan_prestasi_terverifikasi(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $layanan = app(Prestasi::class);

        $kader->forceFill(['profil_publik' => true])->save();

        $prestasi = $this->ajukan($kader, ['judul' => 'Juara Karya Tulis Kampus']);
        $layanan->verifikasi($prestasi, $petugas);

        $this->get('/prestasi/kader/'.$kader->fresh()->slug)
            ->assertOk()
            ->assertSee('Juara Karya Tulis Kampus', false);
    }

    public function test_tamu_tidak_dapat_mengajukan_prestasi(): void
    {
        $this->post('/prestasi-saya', [])->assertRedirect('/login');
    }

    public function test_kader_mengajukan_prestasi_lewat_halaman_prestasi_saya(): void
    {
        $kader = $this->kader();

        $respons = $this->actingAs($kader->user)->post('/prestasi-saya', [
            'judul' => 'Juara 2 LKTI',
            'tingkat' => Achievement::TINGKAT_KAMPUS,
            'peringkat' => Achievement::PERINGKAT_JUARA_2,
            'tanggal' => '2026-08-01',
            'penyelenggara' => 'BEM Fakultas',
            'sertifikat' => UploadedFile::fake()->create('sertifikat.png', 120, 'image/png'),
        ]);

        $respons->assertRedirect();

        $prestasi = Achievement::query()->firstOrFail();

        $this->assertSame('Juara 2 LKTI', $prestasi->judulTeks());
        $this->assertSame($kader->id, $prestasi->member_id);
        $this->assertNotNull($prestasi->sertifikat_media_id);
        $this->assertSame(Achievement::STATUS_DIAJUKAN, $prestasi->status);
    }

    public function test_kader_tidak_dapat_mengubah_prestasi_orang_lain(): void
    {
        $siti = $this->kader('Siti Aminah');
        $budi = $this->kader('Budi Santoso');

        $prestasiBudi = $this->ajukan($budi);

        $this->actingAs($siti->user)
            ->put("/prestasi-saya/{$prestasiBudi->id}", [
                'judul' => 'Diubah orang lain',
                'tingkat' => Achievement::TINGKAT_KAMPUS,
                'peringkat' => Achievement::PERINGKAT_JUARA_1,
                'tanggal' => '2026-09-10',
            ])
            ->assertRedirect();

        $this->assertSame('Juara 1 Lomba Karya Tulis', $prestasiBudi->fresh()->judulTeks());
    }

    public function test_halaman_prestasi_panel_butuh_izin(): void
    {
        $konten = User::factory()->create(['email_verified_at' => now()]);
        $konten->assignRole('konten_manager');

        // Konten Manager MEMANG berhak memverifikasi prestasi.
        $this->actingAs($konten)->get('/panel/prestasi')->assertOk();

        $tanpaPeran = $this->kader('Kader Biasa');
        $this->actingAs($tanpaPeran->user)->get('/panel/prestasi')->assertForbidden();
    }

    public function test_verifikasi_lewat_panel_menayangkan_dan_memberi_poin(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);

        $this->actingAs($petugas)
            ->post("/panel/prestasi/{$prestasi->id}/verifikasi", [])
            ->assertRedirect();

        $this->assertTrue($prestasi->fresh()->terverifikasi());
        $this->assertSame(4, app(Kontribusi::class)->total($kader));
    }

    public function test_tolak_lewat_panel_wajib_beralasan(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $prestasi = $this->ajukan($kader);

        $this->actingAs($petugas)
            ->post("/panel/prestasi/{$prestasi->id}/tolak", ['alasan' => ''])
            ->assertSessionHasErrors('alasan');

        $this->assertSame(Achievement::STATUS_DIAJUKAN, $prestasi->fresh()->status);
    }

    public function test_hapus_lewat_panel_hanya_untuk_pengajuan_belum_diperiksa(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        $belumDiperiksa = $this->ajukan($kader, ['judul' => 'Pengajuan Sampah']);
        $terverifikasi = $this->ajukan($kader, ['judul' => 'Prestasi Betulan']);

        app(Prestasi::class)->verifikasi($terverifikasi, $petugas);

        $this->actingAs($petugas)->delete("/panel/prestasi/{$belumDiperiksa->id}")->assertRedirect();

        $this->assertNull(Achievement::query()->find($belumDiperiksa->id));

        $this->actingAs($petugas)->delete("/panel/prestasi/{$terverifikasi->id}")->assertRedirect();

        $this->assertNotNull(Achievement::query()->find($terverifikasi->id));
    }

    public function test_ekspor_csv_prestasi_memuat_poin_dan_status(): void
    {
        $kader = $this->kader('Siti Aminah');
        $petugas = $this->sekretaris();

        $prestasi = $this->ajukan($kader, ['tingkat' => Achievement::TINGKAT_NASIONAL]);
        app(Prestasi::class)->verifikasi($prestasi, $petugas);

        $respons = $this->actingAs($petugas)->get('/panel/prestasi/ekspor');

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $isi = $respons->getContent();

        $this->assertStringContainsString('Siti Aminah', $isi);
        $this->assertStringContainsString('Tingkat Nasional', $isi);
        $this->assertStringContainsString(';"8";', $isi);
        $this->assertStringContainsString('Terverifikasi', $isi);
    }

    public function test_kategori_prestasi_dapat_dibuat_dan_kode_nya_unik(): void
    {
        $petugas = $this->sekretaris();

        $this->actingAs($petugas)->post('/panel/prestasi/kategori', ['nama' => 'Akademik'])->assertRedirect();
        $this->actingAs($petugas)->post('/panel/prestasi/kategori', ['nama' => 'Akademik'])->assertRedirect();

        $this->assertSame(2, AchievementCategory::query()->count());
        $this->assertSame(2, AchievementCategory::query()->distinct()->count('kode'));
    }

    public function test_halaman_prestasi_saya_menampilkan_status_dan_poin(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        $prestasi = $this->ajukan($kader, ['tingkat' => Achievement::TINGKAT_NASIONAL]);
        app(Prestasi::class)->verifikasi($prestasi, $petugas);

        $this->actingAs($kader->user)
            ->get('/prestasi-saya')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anggota/Prestasi', false)
                ->has('daftar', 1)
                ->where('daftar.0.status', Achievement::STATUS_TERVERIFIKASI)
                ->where('daftar.0.poin_diberikan', 8)
                ->where('rekap.total', 1));
    }
}
