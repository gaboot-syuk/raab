<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\MemberCard;
use App\Models\User;
use App\Services\Keanggotaan;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Alur keanggotaan: verifikasi pendaftar, penerbitan nomor & kartu, dan
 * kenaikan status kader → alumni.
 *
 * Uji ini menjaga aturan yang paling mudah rusak: nomor anggota tidak boleh
 * terbit sebelum disetujui, kartu wajib dicabut saat menjadi alumni, dan
 * Sekretaris tidak boleh meloloskan dirinya sendiri.
 */
class KeanggotaanTest extends TestCase
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

    private function pengurus(string $peran = 'sekretaris'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    /**
     * Buat pendaftar beserta pengajuannya.
     */
    private function pendaftar(array $ganti = []): MemberApplication
    {
        $user = User::factory()->create([
            'name' => $ganti['nama'] ?? 'Nurul Hidayah',
            'email_verified_at' => now(),
        ]);

        return MemberApplication::query()->create([
            'user_id' => $user->id,
            'jalur' => $ganti['jalur'] ?? Member::JALUR_KADER,
            'status' => MemberApplication::STATUS_MENUNGGU,
            'data' => array_merge([
                'nama_lengkap' => $ganti['nama'] ?? 'Nurul Hidayah',
                'jenis_kelamin' => 'perempuan',
                'tempat_lahir' => 'Sukoharjo',
                'tanggal_lahir' => '2004-05-12',
                'telepon' => '081234567890',
                'alamat' => 'Jl. Pandawa No. 10, Kartasura',
                'nim' => '2212345678',
                'fakultas' => 'Fakultas Dakwah',
                'program_studi' => 'Komunikasi dan Penyiaran Islam',
                'angkatan' => 2022,
            ], $ganti['data'] ?? []),
        ]);
    }

    public function test_pengguna_tanpa_izin_tidak_dapat_membuka_antrean_verifikasi(): void
    {
        $konten = $this->pengurus('konten_manager');

        $this->actingAs($konten)->get('/panel/verifikasi')->assertForbidden();
    }

    public function test_sekretaris_dapat_membuka_antrean_verifikasi(): void
    {
        $this->pendaftar();

        $this->actingAs($this->pengurus())
            ->get('/panel/verifikasi')
            ->assertOk()
            ->assertSee('Nurul Hidayah', false);
    }

    public function test_persetujuan_menerbitkan_nomor_anggota_kartu_dan_riwayat(): void
    {
        $pengajuan = $this->pendaftar();

        $this->actingAs($this->pengurus())
            ->post("/panel/verifikasi/{$pengajuan->id}/setujui")
            ->assertRedirect(route('panel.verifikasi'))
            ->assertSessionHas('sukses');

        $pengajuan->refresh();
        $this->assertSame(MemberApplication::STATUS_DISETUJUI, $pengajuan->status);

        $anggota = Member::query()->where('user_id', $pengajuan->user_id)->firstOrFail();
        $this->assertSame(Member::STATUS_AKTIF, $anggota->status);
        $this->assertSame(Member::JALUR_KADER, $anggota->jalur);

        // Nomor anggota mengikuti pola RAAB-{tahun}-{urut}
        $this->assertMatchesRegularExpression('/^RAAB-\d{4}-\d{3}$/', (string) $anggota->nomor_anggota);

        // Kartu kader terbit otomatis untuk jalur kader
        $kartu = MemberCard::query()->where('member_id', $anggota->id)->firstOrFail();
        $this->assertSame(MemberCard::STATUS_AKTIF, $kartu->status);
        $this->assertNotEmpty($kartu->token);

        // Riwayat status tercatat
        $this->assertDatabaseHas('member_status_histories', [
            'member_id' => $anggota->id,
            'status_baru' => Member::STATUS_AKTIF,
        ]);
    }

    public function test_nomor_anggota_berurutan(): void
    {
        $layanan = app(Keanggotaan::class);

        $pertama = $layanan->setujui($this->pendaftar(['nama' => 'Kader Satu']), $this->pengurus());
        $kedua = $layanan->setujui($this->pendaftar(['nama' => 'Kader Dua']), $this->pengurus());

        $tahun = now()->format('Y');

        $this->assertSame("RAAB-{$tahun}-001", $pertama->nomor_anggota);
        $this->assertSame("RAAB-{$tahun}-002", $kedua->nomor_anggota);
    }

    /**
     * Nomor kartu punya urutannya sendiri, dan TIDAK boleh bertabrakan dengan
     * urutan nomor anggota.
     *
     * Kegagalan yang dijaga di sini pernah benar-benar terjadi: satu anggota
     * menerima kartu ketika nomor anggotanya belum terbit. Nomor yang dipakai
     * untuk kartunya dihitung dari urutan nomor anggota, tetapi TIDAK disimpan
     * ke anggota tersebut — sehingga urutan keduanya berbeda satu langkah.
     * Anggota berikutnya yang disetujui mendapat nomor anggota yang sama dengan
     * nomor yang sudah terpakai di kartu, dan persetujuannya GAGAL total karena
     * batas unik.
     *
     * Yang dilihat Sekretaris saat itu: menekan "Ya, setujui", lalu tidak
     * terjadi apa-apa. Pengajuannya tetap menunggu, tanpa keterangan apa pun.
     */
    public function test_nomor_kartu_tidak_bertabrakan_dengan_nomor_anggota(): void
    {
        $pengurus = $this->pengurus();

        // Keadaan yang memicu masalah: anggota yang sudah punya kartu, tetapi
        // nomor anggotanya belum pernah terbit.
        $anggotaLama = Member::query()->create([
            'user_id' => User::factory()->create(['email_verified_at' => now()])->id,
            'nomor_anggota' => null,
            'slug' => 'anggota-lama',
            'jalur' => Member::JALUR_KADER,
            'status' => Member::STATUS_AKTIF,
            'nama_lengkap' => 'Anggota Lama',
            'jenis_kelamin' => 'laki_laki',
            'tempat_lahir' => 'Surakarta',
            'tanggal_lahir' => '2001-01-01',
            'alamat' => 'Sukoharjo',
            'telepon' => '081200000000',
        ]);

        app(Keanggotaan::class)->terbitkanKartu($anggotaLama);

        // Sekarang pengaju berikutnya disetujui. Nomor anggotanya akan menjadi
        // RAAB-{tahun}-001, dan nomor kartunya TIDAK boleh ikut menjadi -001.
        $anggotaBaru = app(Keanggotaan::class)->setujui($this->pendaftar(['nama' => 'Kader Baru']), $pengurus);

        $kartuLama = MemberCard::query()->where('member_id', $anggotaLama->id)->firstOrFail();
        $kartuBaru = MemberCard::query()->where('member_id', $anggotaBaru->id)->firstOrFail();

        $this->assertNotSame(
            $kartuLama->nomor_kartu,
            $kartuBaru->nomor_kartu,
            'Nomor kartu bertabrakan — persetujuan anggota berikutnya akan gagal.'
        );

        $this->assertSame(2, MemberCard::query()->count());
    }

    /**
     * Nomor kartu tetap berurutan meski anggota dan kartu tidak sejalan.
     */
    public function test_nomor_kartu_berurutan(): void
    {
        $layanan = app(Keanggotaan::class);
        $pengurus = $this->pengurus();

        $satu = $layanan->setujui($this->pendaftar(['nama' => 'Kader Satu']), $pengurus);
        $dua = $layanan->setujui($this->pendaftar(['nama' => 'Kader Dua']), $pengurus);

        $tahun = now()->format('Y');

        $this->assertSame(
            "K-RAAB-{$tahun}-001",
            MemberCard::query()->where('member_id', $satu->id)->value('nomor_kartu')
        );
        $this->assertSame(
            "K-RAAB-{$tahun}-002",
            MemberCard::query()->where('member_id', $dua->id)->value('nomor_kartu')
        );
    }

    public function test_persetujuan_jalur_alumni_tidak_menerbitkan_kartu(): void
    {
        $pengajuan = $this->pendaftar([
            'nama' => 'Ahmad Fauzi',
            'jalur' => Member::JALUR_ALUMNI,
            'data' => ['tahun_lulus' => 2021, 'instansi' => 'Dinas Pendidikan', 'kota_domisili' => 'Klaten'],
        ]);

        $this->actingAs($this->pengurus())->post("/panel/verifikasi/{$pengajuan->id}/setujui");

        $anggota = Member::query()->where('user_id', $pengajuan->user_id)->firstOrFail();

        $this->assertSame(Member::STATUS_ALUMNI, $anggota->status);
        $this->assertDatabaseCount('member_cards', 0);
        $this->assertDatabaseHas('alumni_profiles', ['member_id' => $anggota->id]);
    }

    public function test_penolakan_wajib_disertai_alasan(): void
    {
        $pengajuan = $this->pendaftar();

        $this->actingAs($this->pengurus())
            ->post("/panel/verifikasi/{$pengajuan->id}/tolak", ['catatan_pengurus' => ''])
            ->assertSessionHasErrors('catatan_pengurus');

        $this->assertSame(MemberApplication::STATUS_MENUNGGU, $pengajuan->fresh()->status);
    }

    public function test_penolakan_mengubah_status_pengajuan(): void
    {
        $pengajuan = $this->pendaftar();

        $this->actingAs($this->pengurus())->post("/panel/verifikasi/{$pengajuan->id}/tolak", [
            'catatan_pengurus' => 'Data NIM tidak sesuai dengan basis data kampus.',
        ])->assertSessionHas('sukses');

        $this->assertSame(MemberApplication::STATUS_DITOLAK, $pengajuan->fresh()->status);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_sekretaris_tidak_dapat_memverifikasi_pengajuannya_sendiri(): void
    {
        $pengurus = $this->pengurus();

        $pengajuan = MemberApplication::query()->create([
            'user_id' => $pengurus->id,
            'jalur' => Member::JALUR_KADER,
            'status' => MemberApplication::STATUS_MENUNGGU,
            'data' => ['nama_lengkap' => $pengurus->name],
        ]);

        $this->actingAs($pengurus)
            ->post("/panel/verifikasi/{$pengajuan->id}/setujui")
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
    }

    public function test_pengajuan_yang_sudah_diproses_tidak_dapat_disetujui_lagi(): void
    {
        $pengajuan = $this->pendaftar();
        $pengurus = $this->pengurus();

        $this->actingAs($pengurus)->post("/panel/verifikasi/{$pengajuan->id}/setujui");
        $this->actingAs($pengurus)->post("/panel/verifikasi/{$pengajuan->id}/setujui")->assertForbidden();

        $this->assertDatabaseCount('members', 1);
    }

    public function test_naik_status_alumni_mencabut_kartu_dan_membuat_profil_alumni(): void
    {
        $pengajuan = $this->pendaftar();
        $pengurus = $this->pengurus();

        $this->actingAs($pengurus)->post("/panel/verifikasi/{$pengajuan->id}/setujui");

        $anggota = Member::query()->where('user_id', $pengajuan->user_id)->firstOrFail();
        $kartu = MemberCard::query()->where('member_id', $anggota->id)->firstOrFail();

        $this->actingAs($pengurus)
            ->patch("/panel/keanggotaan/{$anggota->id}/status", [
                'status' => Member::STATUS_ALUMNI,
                'alasan' => 'Telah lulus pada September 2026.',
            ])
            ->assertSessionHas('sukses');

        $anggota->refresh();
        $this->assertSame(Member::STATUS_ALUMNI, $anggota->status);
        $this->assertSame(Member::JALUR_ALUMNI, $anggota->jalur);

        // Kartu kader tidak lagi berlaku
        $this->assertSame(MemberCard::STATUS_DICABUT, $kartu->fresh()->status);

        // Profil alumni disiapkan untuk direktori
        $this->assertDatabaseHas('alumni_profiles', ['member_id' => $anggota->id]);

        // Riwayat mencatat perpindahan
        $this->assertDatabaseHas('member_status_histories', [
            'member_id' => $anggota->id,
            'status_lama' => Member::STATUS_AKTIF,
            'status_baru' => Member::STATUS_ALUMNI,
        ]);
    }

    public function test_direktori_publik_tidak_menampilkan_data_sensitif(): void
    {
        $pengajuan = $this->pendaftar();
        $this->actingAs($this->pengurus())->post("/panel/verifikasi/{$pengajuan->id}/setujui");

        $isi = (string) $this->get('/anggota')->assertOk()->getContent();

        /*
         * Nama kini juga TIDAK boleh tampil. Sebelumnya uji ini justru
         * mewajibkan nama ada; kebijakannya berubah, dan nama yang telanjur
         * diindeks mesin pencari tidak dapat ditarik kembali.
         */
        $this->assertStringNotContainsString('Nurul Hidayah', $isi);

        // Data sensitif tidak boleh muncul meski tersimpan di basis data.
        $this->assertStringNotContainsString('2212345678', $isi);      // NIM
        $this->assertStringNotContainsString('081234567890', $isi);     // telepon
        $this->assertStringNotContainsString('Jl. Pandawa No. 10', $isi); // alamat
    }

    public function test_direktori_alumni_hanya_menampilkan_alumni(): void
    {
        // Kader aktif
        $kader = $this->pendaftar(['nama' => 'Kader Aktif']);
        // Alumni
        $alumni = $this->pendaftar([
            'nama' => 'Alumni Satu',
            'jalur' => Member::JALUR_ALUMNI,
            'data' => ['tahun_lulus' => 2020, 'instansi' => 'Universitas Contoh', 'kota_domisili' => 'Surakarta'],
        ]);

        $pengurus = $this->pengurus();
        $this->actingAs($pengurus)->post("/panel/verifikasi/{$kader->id}/setujui");
        $this->actingAs($pengurus)->post("/panel/verifikasi/{$alumni->id}/setujui");

        $isi = (string) $this->get('/alumni')->assertOk()->getContent();

        // Nama tidak lagi menjadi penanda di halaman ini, jadi yang diperiksa
        // adalah jumlah yang benar-benar sampai ke tampilan: satu, yaitu alumni.
        $this->assertStringNotContainsString('Alumni Satu', $isi);
        $this->assertStringNotContainsString('Kader Aktif', $isi);

        $this->get('/alumni')
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar): bool => $daftar->total() === 1);
    }

    public function test_area_anggota_menampilkan_status_pengajuan(): void
    {
        $pengajuan = $this->pendaftar();

        // Halaman area anggota memakai Inertia + Vue (dirender di sisi klien),
        // jadi yang diperiksa adalah data yang dikirim server — bukan teks HTML.
        $this->actingAs($pengajuan->user)
            ->get('/dasbor')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Anggota/Dasbor', false)
                ->where('pengajuan.status', MemberApplication::STATUS_MENUNGGU)
                ->where('anggota', null));
    }

    public function test_anggota_terverifikasi_melihat_nomor_anggota_di_dasbor(): void
    {
        $pengajuan = $this->pendaftar();
        $this->actingAs($this->pengurus())->post("/panel/verifikasi/{$pengajuan->id}/setujui");

        $this->actingAs($pengajuan->user)
            ->get('/dasbor')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Anggota/Dasbor', false)
                ->where('anggota.status', Member::STATUS_AKTIF)
                ->where('anggota.kartu', fn ($kartu) => $kartu !== null));
    }

    public function test_anggota_dapat_memperbarui_profilnya(): void
    {
        $pengajuan = $this->pendaftar();
        $this->actingAs($this->pengurus())->post("/panel/verifikasi/{$pengajuan->id}/setujui");

        $anggota = Member::query()->where('user_id', $pengajuan->user_id)->firstOrFail();

        $this->actingAs($pengajuan->user)
            ->put('/profil', [
                'nama_lengkap' => 'Nurul Hidayah',
                'jenis_kelamin' => 'perempuan',
                'program_studi' => 'Komunikasi dan Penyiaran Islam',
                'keahlian' => 'jurnalistik, desain grafis',
                'privasi' => ['telepon' => false],
            ])
            ->assertSessionHas('sukses');

        $anggota->refresh();
        $this->assertSame(['jurnalistik', 'desain grafis'], $anggota->keahlian);
        $this->assertFalse($anggota->bolehTampil('telepon'));
    }
}
