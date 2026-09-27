<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ke mana pengguna diarahkan setelah masuk.
 *
 * Fortify mengarahkan SEMUA pengguna ke `/panel` setelah masuk atau setelah
 * memverifikasi email. Sebelum diperbaiki, kader yang baru mendaftar karena itu
 * mendarat di panel pengurus dan melihat halaman kosong bertuliskan "Belum ada
 * menu yang tersedia untuk peranmu. Hubungi Superadmin." — ditambah daftar peta
 * jalan pengembangan yang tidak ada hubungannya dengan dirinya.
 *
 * Kesan yang tertinggal: aplikasinya rusak. Padahal halaman yang seharusnya ia
 * lihat — status pengajuannya — sudah ada, hanya tidak pernah dituju.
 *
 * Panel pengurus bukan "halaman setelah masuk". Ia halaman kerja pengurus.
 */
class ArahSetelahMasukTest extends TestCase
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

    private function pengurus(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('sekretaris');

        return $user;
    }

    /**
     * Kader: akunnya ada, tetapi tidak memegang peran kepengurusan.
     */
    private function kader(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        Member::query()->create([
            'user_id' => $user->id,
            'nomor_anggota' => 'RAAB-2026-100',
            'slug' => 'kader-arah',
            'jalur' => Member::JALUR_KADER,
            'status' => Member::STATUS_AKTIF,
            'nama_lengkap' => 'Kader Arah',
            'jenis_kelamin' => 'laki_laki',
            'tempat_lahir' => 'Surakarta',
            'tanggal_lahir' => '2002-01-01',
            'alamat' => 'Sukoharjo',
            'telepon' => '081234567890',
        ]);

        return $user;
    }

    public function test_kader_dialihkan_dari_panel_ke_dasbor_anggota(): void
    {
        $respons = $this->actingAs($this->kader())->get('/panel');

        $respons->assertRedirect('/dasbor');
    }

    public function test_pengalihan_disertai_keterangan_bukan_halaman_kosong(): void
    {
        $this->actingAs($this->kader())
            ->get('/panel')
            ->assertRedirect('/dasbor')
            ->assertSessionHas('sukses');
    }

    public function test_pengurus_tetap_melihat_dasbor_panel(): void
    {
        $this->actingAs($this->pengurus())->get('/panel')->assertOk();
    }

    /**
     * Kader tidak boleh melihat daftar peta jalan pengembangan.
     *
     * Isi itu catatan internal, bukan informasi untuk anggota — dan seluruh
     * fasenya sudah selesai, sehingga yang terbaca hanyalah rencana usang.
     */
    public function test_kader_tidak_pernah_melihat_peta_jalan_pengembangan(): void
    {
        $respons = $this->actingAs($this->kader())->get('/panel');

        $respons->assertRedirect('/dasbor');
    }

    /**
     * Pengurus yang bukan sekaligus anggota tetap bisa membuka panel.
     *
     * Status keanggotaan dan peran kepengurusan berdiri sendiri-sendiri; akun
     * pengurus demo memang tidak punya baris anggota.
     */
    public function test_pengurus_tanpa_data_anggota_tetap_dianggap_pengurus(): void
    {
        $pengurus = $this->pengurus();

        $this->assertTrue($pengurus->pengurus());
        $this->assertSame('/panel', $pengurus->berandaSetelahMasuk());
    }

    public function test_kader_diarahkan_ke_dasbor_anggota_setelah_masuk(): void
    {
        $this->assertSame('/dasbor', $this->kader()->berandaSetelahMasuk());
    }
}
