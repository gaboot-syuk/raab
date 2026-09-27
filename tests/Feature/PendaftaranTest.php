<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pendaftaran keanggotaan dua jalur (kader aktif / alumni).
 *
 * Yang dibuat saat mendaftar hanyalah akun + pengajuan berstatus "menunggu".
 * Status keanggotaan baru aktif setelah Sekretaris menyetujui.
 */
class PendaftaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UnitSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataKader(array $ganti = []): array
    {
        return array_merge([
            'name' => 'Nurul Hidayah',
            'email' => 'nurul@example.test',
            'password' => 'rahasia123456',
            'password_confirmation' => 'rahasia123456',
            'jalur' => Member::JALUR_KADER,
            'jenis_kelamin' => 'perempuan',
            'tempat_lahir' => 'Sukoharjo',
            'tanggal_lahir' => '2004-05-12',
            'telepon' => '081234567890',
            'alamat' => 'Jl. Pandawa No. 10, Pucangan, Kartasura',
            'nim' => '2212345678',
            'fakultas' => 'Fakultas Dakwah',
            'program_studi' => 'Komunikasi dan Penyiaran Islam',
            'angkatan' => 2022,
            'setuju' => '1',
        ], $ganti);
    }

    public function test_formulir_pendaftaran_dapat_dibuka(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee(__('otentikasi.daftar.judul'))
            ->assertSee('name="jalur"', false)
            ->assertSee('name="nim"', false);
    }

    public function test_alamat_daftar_mengarah_ke_formulir_pendaftaran(): void
    {
        $this->get('/daftar')->assertRedirect('/register');
    }

    public function test_pendaftar_kader_membuat_akun_dan_pengajuan_menunggu(): void
    {
        $this->post('/register', $this->dataKader())->assertRedirect();

        $user = User::query()->where('email', 'nurul@example.test')->firstOrFail();

        // Email belum terverifikasi — tautan dikirim Fortify.
        $this->assertNull($user->email_verified_at);

        $pengajuan = MemberApplication::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(Member::JALUR_KADER, $pengajuan->jalur);
        $this->assertSame(MemberApplication::STATUS_MENUNGGU, $pengajuan->status);
        $this->assertSame('2212345678', $pengajuan->isian('nim'));

        // Belum ada data anggota resmi & belum ada nomor anggota.
        $this->assertDatabaseCount('members', 0);

        // Kata sandi tidak pernah ikut tersimpan pada snapshot pengajuan.
        $this->assertArrayNotHasKey('password', $pengajuan->data);
    }

    public function test_pendaftar_alumni_memakai_jalur_alumni(): void
    {
        $this->post('/register', [
            'name' => 'Ahmad Fauzi',
            'email' => 'fauzi@example.test',
            'password' => 'rahasia123456',
            'password_confirmation' => 'rahasia123456',
            'jalur' => Member::JALUR_ALUMNI,
            'jenis_kelamin' => 'laki_laki',
            'tempat_lahir' => 'Klaten',
            'tanggal_lahir' => '1998-02-20',
            'telepon' => '081200000001',
            'alamat' => 'Perum Griya Asri Blok C, Klaten',
            'tahun_lulus' => 2021,
            'instansi' => 'Dinas Pendidikan Kabupaten Klaten',
            'kota_domisili' => 'Klaten',
            'setuju' => '1',
        ])->assertRedirect();

        $pengajuan = MemberApplication::query()
            ->whereRelation('user', 'email', 'fauzi@example.test')
            ->firstOrFail();

        $this->assertSame(Member::JALUR_ALUMNI, $pengajuan->jalur);
        $this->assertSame('Dinas Pendidikan Kabupaten Klaten', $pengajuan->isian('instansi'));
    }

    public function test_kader_wajib_mengisi_data_kemahasiswaan(): void
    {
        // Jalur kader tanpa NIM/fakultas/prodi/angkatan harus ditolak.
        $this->post('/register', $this->dataKader([
            'nim' => '',
            'fakultas' => '',
            'program_studi' => '',
            'angkatan' => '',
        ]))->assertSessionHasErrors(['nim', 'fakultas', 'program_studi', 'angkatan']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_alumni_wajib_mengisi_data_alumni(): void
    {
        $this->post('/register', [
            'name' => 'Ahmad Fauzi',
            'email' => 'fauzi@example.test',
            'password' => 'rahasia123456',
            'password_confirmation' => 'rahasia123456',
            'jalur' => Member::JALUR_ALUMNI,
            'jenis_kelamin' => 'laki_laki',
            'tempat_lahir' => 'Klaten',
            'tanggal_lahir' => '1998-02-20',
            'telepon' => '081200000001',
            'alamat' => 'Perum Griya Asri Blok C, Klaten',
            'setuju' => '1',
        ])->assertSessionHasErrors(['tahun_lulus', 'instansi', 'kota_domisili']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_ganda_ditolak(): void
    {
        User::factory()->create(['email' => 'nurul@example.test']);

        $this->post('/register', $this->dataKader())
            ->assertSessionHasErrors('email');
    }

    public function test_persetujuan_wajib_dicentang(): void
    {
        $data = $this->dataKader();
        unset($data['setuju']);

        $this->post('/register', $data)->assertSessionHasErrors('setuju');
    }

    public function test_kata_sandi_lemah_ditolak(): void
    {
        $this->post('/register', $this->dataKader([
            'password' => 'hanya-huruf',
            'password_confirmation' => 'hanya-huruf',
        ]))->assertSessionHasErrors('password');
    }

    public function test_jalur_di_luar_daftar_ditolak(): void
    {
        $this->post('/register', $this->dataKader(['jalur' => 'pengurus']))
            ->assertSessionHasErrors('jalur');
    }
}
