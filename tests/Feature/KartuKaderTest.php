<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberCard;
use App\Models\User;
use App\Services\Keanggotaan;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kartu kader: penerbitan, kartu digital milik kader, dan halaman verifikasi
 * publik.
 *
 * Yang paling penting diuji di sini bukan tampilannya, melainkan DUA JANJI:
 * kartu yang tidak sah selalu ketahuan, dan halaman publik tidak membocorkan
 * kontak pemilik kartu.
 */
class KartuKaderTest extends TestCase
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
        // Data kontak disimpan di sini; halaman verifikasi publik TIDAK BOLEH
        // menampilkannya.
        $member->privasi = ['telepon' => '081234567890', 'alamat' => 'Jl. Pandawa No. 10'];
        $member->save();

        return $member;
    }

    private function beriKartu(Member $member): MemberCard
    {
        return app(Keanggotaan::class)->terbitkanKartu($member);
    }

    /* ===================== Halaman verifikasi publik ===================== */

    public function test_kartu_sah_dapat_diverifikasi_tanpa_login(): void
    {
        $kader = $this->kader();
        $kartu = $this->beriKartu($kader);

        $respons = $this->get('/verifikasi-kader/'.$kartu->token);

        $respons->assertOk();
        $respons->assertSee('Kartu Sah', false);
        $respons->assertSee('Siti Aminah', false);
        $respons->assertSee($kartu->nomor_kartu, false);
    }

    public function test_halaman_verifikasi_tidak_membocorkan_kontak_pemilik_kartu(): void
    {
        $kader = $this->kader();
        $kartu = $this->beriKartu($kader);

        $respons = $this->get('/verifikasi-kader/'.$kartu->token);

        // Kartu bisa jatuh di jalan; kalau halamannya bocor, yang bocor adalah
        // data pribadi pemiliknya.
        $respons->assertOk();
        $respons->assertDontSee($kader->user->email, false);
        $respons->assertDontSee('081234567890', false);
        $respons->assertDontSee('Jl. Pandawa', false);
    }

    public function test_token_yang_tidak_dikenal_ditolak_beserta_penjelasannya(): void
    {
        $respons = $this->get('/verifikasi-kader/token-yang-tidak-pernah-diterbitkan');

        $respons->assertOk();
        $respons->assertSee('Kartu Tidak Sah', false);
        $respons->assertSee('Tidak ada kartu dengan kode verifikasi itu', false);
    }

    public function test_kartu_yang_dicabut_ditolak_dan_menyebut_sebabnya(): void
    {
        $kader = $this->kader();
        $kartu = $this->beriKartu($kader);

        app(Keanggotaan::class)->cabutKartu($kader, 'Status berubah menjadi alumni.');

        $respons = $this->get('/verifikasi-kader/'.$kartu->token);

        $respons->assertOk();
        $respons->assertSee('Kartu Tidak Sah', false);
        $respons->assertSee('sudah dicabut', false);
        // Nama pemiliknya pun tidak lagi ditampilkan begitu kartunya dicabut.
        $respons->assertDontSee('Siti Aminah', false);
    }

    public function test_kartu_kedaluwarsa_ditolak_dengan_sebab_yang_berbeda_dari_pencabutan(): void
    {
        $kader = $this->kader();
        $kartu = $this->beriKartu($kader);

        $kartu->forceFill(['berlaku_sampai' => now()->subDay()])->save();

        $respons = $this->get('/verifikasi-kader/'.$kartu->token);

        $respons->assertOk();
        $respons->assertSee('Kartu Tidak Sah', false);
        $respons->assertSee('Masa berlaku kartu ini sudah habis', false);
        // Kader yang kartunya hanya kedaluwarsa tidak boleh diberi tahu bahwa
        // kartunya "dicabut" — ia tidak melakukan kesalahan apa pun.
        $respons->assertDontSee('sudah dicabut', false);
    }

    public function test_kartu_yang_dicabut_tidak_bisa_dihidupkan_hanya_dengan_masa_berlaku(): void
    {
        $kader = $this->kader();
        $kartu = $this->beriKartu($kader);

        $kartu->forceFill([
            'status' => MemberCard::STATUS_DICABUT,
            'berlaku_sampai' => now()->addYear(),
        ])->save();

        $this->get('/verifikasi-kader/'.$kartu->token)
            ->assertSee('Kartu Tidak Sah', false);
    }

    /* ===================== Kartu digital milik kader ===================== */

    public function test_kader_melihat_kartunya_sendiri_beserta_tautan_verifikasi(): void
    {
        $kader = $this->kader();
        $kartu = $this->beriKartu($kader);

        $respons = $this->actingAs($kader->user)->get('/kartu-kader');

        $respons->assertOk();
        $respons->assertSee($kartu->nomor_kartu, false);
        $respons->assertSee('/verifikasi-kader/'.$kartu->token, false);
    }

    public function test_halaman_kartu_tidak_menampilkan_kartu_orang_lain(): void
    {
        $siti = $this->kader('Siti Aminah');
        $budi = $this->kader('Budi Santoso');

        $this->beriKartu($siti);
        $kartuBudi = $this->beriKartu($budi);

        $respons = $this->actingAs($siti->user)->get('/kartu-kader');

        $respons->assertOk();
        $respons->assertSee('Siti Aminah', false);
        $respons->assertDontSee($kartuBudi->nomor_kartu, false);
    }

    public function test_tamu_tidak_dapat_membuka_halaman_kartu(): void
    {
        $this->get('/kartu-kader')->assertRedirect('/login');
    }

    public function test_anggota_tanpa_kartu_mendapat_penjelasan_bukan_halaman_rusak(): void
    {
        // Alumni tidak memerlukan kartu, jadi halaman ini harus menjelaskan
        // keadaannya, bukan menampilkan galat.
        $alumni = $this->kader('Alumni Uji');
        $alumni->forceFill(['jalur' => Member::JALUR_ALUMNI, 'status' => Member::STATUS_ALUMNI])->save();

        $respons = $this->actingAs($alumni->user)->get('/kartu-kader');

        $respons->assertOk();

        /*
         * Penjelasannya harus MENYEBUT ALUMNI, bukan memakai pesan umum
         * "kartumu belum diterbitkan, hubungi sekretariat".
         *
         * Pesan umum itu salah untuk alumni: kartu mereka memang tidak akan
         * pernah diterbitkan, karena kartu dicabut saat statusnya berubah.
         * Alumni yang membaca pesan umum akan menghubungi sekretariat untuk
         * sesuatu yang tidak ada — dan sekretariat tidak punya jawaban.
         */
        $respons->assertSee('tidak berlaku untuk alumni', false);
        $respons->assertDontSee('Hubungi sekretariat rayon', false);
    }

    /**
     * Kader yang belum berkartu tetap mendapat pesan umum, bukan pesan alumni.
     */
    public function test_kader_yang_belum_berkartu_mendapat_pesan_umum(): void
    {
        $kader = $this->kader();

        $respons = $this->actingAs($kader->user)->get('/kartu-kader');

        $respons->assertOk();
        $respons->assertSee('belum diterbitkan', false);
    }

    public function test_kartu_yang_dicabut_tetap_ditampilkan_dengan_peringatan(): void
    {
        $kader = $this->kader();
        $kartu = $this->beriKartu($kader);

        app(Keanggotaan::class)->cabutKartu($kader, 'Kader dinonaktifkan.');

        $respons = $this->actingAs($kader->user)->get('/kartu-kader');

        $respons->assertOk();
        $respons->assertSee($kartu->nomor_kartu, false);
        $respons->assertSee('sudah dicabut', false);
    }
}
