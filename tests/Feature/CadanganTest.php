<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Document;
use App\Models\Member;
use App\Models\User;
use App\Services\Cadangan;
use App\Support\Audiens;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Cadangan basis data.
 *
 * UJI YANG PALING PENTING DI BERKAS INI ADALAH `test_cadangan_dapat_dipulihkan_kembali`.
 * Cadangan yang tidak pernah dicoba dipulihkan bukan cadangan — ia hanya berkas
 * yang membuat orang merasa aman. Bukti yang diminta adalah: data dibuat,
 * dicadangkan, DIHAPUS, lalu dipulihkan, dan datanya kembali utuh.
 *
 * Data uji sengaja memuat baris baru dan tanda kutip, karena justru nilai-nilai
 * itulah yang membuat berkas cadangan rusak tanpa terlihat: satu tanda kutip
 * yang lolos begitu saja membuat seluruh pernyataan berikutnya gagal dibaca.
 */
class CadanganTest extends TestCase
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

        Storage::fake('local');
    }

    private function cadangan(): Cadangan
    {
        return app(Cadangan::class);
    }

    private function superadmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('superadmin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function pengumuman(array $ganti = []): Announcement
    {
        return app(\App\Services\Pengumuman::class)->simpan(array_merge([
            'judul' => 'Rapat evaluasi bulanan',
            'isi' => "Baris pertama pengumuman.\n\nBaris kedua, dengan 'tanda kutip' dan titik koma; di dalamnya.",
            'tipe' => Announcement::TIPE_INTERNAL,
            'target_audience' => [Audiens::KADER],
        ], $ganti), $this->superadmin());
    }

    /* ===================== Bukti bisa dipulihkan ===================== */

    public function test_cadangan_dapat_dipulihkan_kembali(): void
    {
        $pengumuman = $this->pengumuman(['judul' => 'Pengumuman Penting Sekali']);

        $anggota = new Member;
        $anggota->user_id = User::factory()->create()->id;
        $anggota->nomor_anggota = 'RAAB-2026-5001';
        $anggota->nama_lengkap = "Nama dengan 'kutip' dan titik; koma";
        $anggota->status = Member::STATUS_AKTIF;
        $anggota->jalur = Member::JALUR_KADER;
        $anggota->save();

        $jumlahPengumuman = Announcement::query()->count();
        $jumlahAnggota = Member::query()->count();
        $jumlahPengguna = User::query()->count();

        $hasil = $this->cadangan()->buat();

        $this->assertTrue($this->cadangan()->ada($hasil['berkas']));
        $this->assertGreaterThan(10, $hasil['tabel'], 'Cadangan seharusnya memuat banyak tabel.');
        $this->assertGreaterThan(0, $hasil['baris']);

        // HAPUS datanya — meniru kehilangan yang sebenarnya.
        Announcement::query()->forceDelete();
        Member::query()->forceDelete();
        User::query()->whereKeyNot(1)->delete();

        $this->assertSame(0, Announcement::query()->count());

        $pulih = $this->cadangan()->pulihkan($hasil['berkas']);

        $this->assertGreaterThan(0, $pulih['baris']);

        // Datanya kembali, dan bukan sekadar jumlahnya sama.
        $this->assertSame($jumlahPengumuman, Announcement::query()->count());
        $this->assertSame($jumlahAnggota, Member::query()->count());
        $this->assertSame($jumlahPengguna, User::query()->count());

        $kembali = Announcement::query()->whereKey($pengumuman->id)->firstOrFail();

        $this->assertSame('Pengumuman Penting Sekali', $kembali->judulTeks());

        // Nilai yang paling mudah merusak berkas cadangan harus utuh: baris
        // baru, tanda kutip tunggal, dan titik koma.
        $this->assertStringContainsString("'tanda kutip'", $kembali->isiTeks());
        $this->assertStringContainsString('titik koma;', $kembali->isiTeks());
        $this->assertStringContainsString("\n", $kembali->isiTeks());

        $anggotaKembali = Member::query()->where('nomor_anggota', 'RAAB-2026-5001')->firstOrFail();
        $this->assertSame("Nama dengan 'kutip' dan titik; koma", $anggotaKembali->nama_lengkap);
    }

    public function test_berkas_cadangan_tidak_pernah_menyimpan_baris_baru_di_dalam_pernyataan(): void
    {
        $this->pengumuman();

        $hasil = $this->cadangan()->buat();
        $isi = Storage::disk('local')->get($this->cadangan()->jalur($hasil['berkas']));

        $this->assertNotNull($isi);

        // SETIAP pernyataan harus berada pada satu baris. Kalau ada baris baru
        // yang lolos ke dalam nilai, pemulihan tidak lagi bisa dipastikan.
        foreach (explode("\n", (string) $isi) as $baris) {
            $baris = trim($baris);

            if ($baris === '' || str_starts_with($baris, '--')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/;\s*$/',
                $baris,
                'Ada pernyataan yang terpecah menjadi beberapa baris: '.mb_substr($baris, 0, 80),
            );
        }
    }

    public function test_cadangan_memuat_seluruh_tabel_yang_berisi_data(): void
    {
        $this->pengumuman();
        app(\App\Services\Arsip::class)->simpan([
            'judul' => 'AD/ART',
            'kategori' => Document::KATEGORI_AD_ART,
            'akses' => [Audiens::PUBLIK],
        ], $this->superadmin());

        $tabel = $this->cadangan()->daftarTabel();

        $this->assertContains('announcements', $tabel);
        $this->assertContains('documents', $tabel);
        $this->assertContains('users', $tabel);
        $this->assertContains('members', $tabel);

        // `migrations` tidak dicadangkan: memulihkannya bisa membuat migrasi
        // berikutnya terlewat.
        $this->assertNotContains('migrations', $tabel);
    }

    public function test_pemulihan_berkas_yang_tidak_ada_ditolak_jelas(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->cadangan()->pulihkan('cadangan-yang-tidak-pernah-ada.sql');
    }

    /* ===================== Daftar, hapus, bersihkan ===================== */

    public function test_daftar_cadangan_diurutkan_terbaru_dulu(): void
    {
        $satu = $this->cadangan()->buat();
        $this->travel(1)->seconds();
        $dua = $this->cadangan()->buat();

        $daftar = $this->cadangan()->daftar();

        $this->assertCount(2, $daftar);
        $this->assertSame($dua['berkas'], $daftar[0]['nama']);
        $this->assertSame($satu['berkas'], $daftar[1]['nama']);
    }

    public function test_pembersihan_menyisakan_sejumlah_berkas_terbaru(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->cadangan()->buat();
            $this->travel(1)->seconds();
        }

        $this->assertCount(4, $this->cadangan()->daftar());

        $dihapus = $this->cadangan()->bersihkan(2);

        $this->assertSame(2, $dihapus);
        $this->assertCount(2, $this->cadangan()->daftar());
    }

    /* ===================== Panel ===================== */

    public function test_panel_cadangan_hanya_untuk_superadmin(): void
    {
        $this->actingAs($this->superadmin())->get('/panel/cadangan')->assertOk();

        $sekretaris = User::factory()->create(['email_verified_at' => now()]);
        $sekretaris->assignRole('sekretaris');

        // Bahkan Sekretaris tidak boleh: berkasnya memuat seluruh isi basis data.
        $this->actingAs($sekretaris)->get('/panel/cadangan')->assertForbidden();

        $kader = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($kader)->get('/panel/cadangan')->assertForbidden();
    }

    public function test_superadmin_membuat_dan_mengunduh_cadangan_lewat_panel(): void
    {
        $superadmin = $this->superadmin();
        $this->pengumuman();

        $this->actingAs($superadmin)
            ->post('/panel/cadangan')
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $daftar = $this->cadangan()->daftar();
        $this->assertCount(1, $daftar);

        $respons = $this->actingAs($superadmin)
            ->get('/panel/cadangan/'.$daftar[0]['nama'].'/unduh');

        $respons->assertOk();
        $this->assertStringContainsString('-- Cadangan basis data', (string) $respons->streamedContent());
    }

    public function test_halaman_panel_menampilkan_daftar_cadangan(): void
    {
        $this->cadangan()->buat();

        $this->actingAs($this->superadmin())
            ->get('/panel/cadangan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Cadangan/Index', false)
                ->has('daftar', 1));
    }

    public function test_cadangan_tidak_bisa_diunduh_tanpa_masuk_sebagai_superadmin(): void
    {
        $hasil = $this->cadangan()->buat();

        // Tamu ditolak sebelum menyentuh berkasnya sama sekali.
        $respons = $this->get('/panel/cadangan/'.$hasil['berkas'].'/unduh');

        $this->assertContains(
            $respons->getStatusCode(),
            [302, 403],
            'Tamu seharusnya dialihkan ke halaman masuk atau ditolak, bukan dilayani.',
        );
    }

    /* ===================== Penjadwalan & perintah ===================== */

    public function test_cadangan_dijadwalkan_setiap_hari(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        // Dijadwalkan berarti ia berjalan tanpa ada yang ingat menjalankannya —
        // itulah bedanya dengan cadangan manual.
        $this->assertTrue(
            collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
                ->contains(fn ($e) => str_contains($e->command ?? '', 'cadangan:buat')),
            'Perintah cadangan:buat tidak terdaftar pada penjadwal.',
        );
    }

    public function test_perintah_membuat_dan_mendaftar_cadangan(): void
    {
        $this->pengumuman();

        $this->artisan('cadangan:buat')->assertSuccessful();
        $this->artisan('cadangan:daftar')->assertSuccessful();

        $this->assertCount(1, $this->cadangan()->daftar());
    }

    public function test_perintah_pemulihan_menolak_nama_berkas_yang_salah(): void
    {
        $hasil = $this->cadangan()->buat();

        // Nama berkas harus diketik ulang; jawaban yang tidak cocok berarti
        // tidak ada yang berubah.
        $this->artisan('cadangan:pulihkan', ['berkas' => $hasil['berkas']])
            ->expectsQuestion('Ketik ulang nama berkas untuk melanjutkan', 'nama-yang-salah')
            ->assertExitCode(1);
    }
}
