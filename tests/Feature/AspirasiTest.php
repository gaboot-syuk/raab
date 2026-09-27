<?php

namespace Tests\Feature;

use App\Models\Aspiration;
use App\Models\Member;
use App\Models\User;
use App\Services\Aspirasi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Aspirasi.
 *
 * Yang paling penting diuji di sini bukan tampilannya, melainkan DUA JANJI
 * YANG SALING BERLAWANAN:
 *  - identitas pengirim tersimpan lengkap untuk pengurus, dan
 *  - identitas itu TIDAK PERNAH sampai ke halaman publik, termasuk bila ia
 *    ditulis sendiri oleh pengirim di badan suratnya.
 */
class AspirasiTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function aspirasi(array $ganti = []): Aspiration
    {
        return app(Aspirasi::class)->kirim(array_merge([
            'nama_pengirim' => 'Siti Aminah',
            'email_pengirim' => 'siti@contoh.test',
            'telepon_pengirim' => '081234567890',
            'kategori' => Aspiration::KATEGORI_FASILITAS,
            'judul' => 'Ruang sekretariat perlu kipas angin',
            'isi' => 'Ruangan sekretariat sangat panas pada siang hari sehingga sulit dipakai untuk rapat.',
            'tampil_publik' => true,
        ], $ganti));
    }

    private function tanggapi(Aspiration $aspirasi, string $tanggapan = 'Sudah kami ajukan ke komisariat.', string $status = Aspiration::STATUS_DIPROSES): Aspiration
    {
        return app(Aspirasi::class)->tanggapi($aspirasi, $tanggapan, $status, $this->sekretaris());
    }

    /* ===================== Redaksi data pribadi ===================== */

    public function test_redaksi_menyamarkan_telepon_email_dan_nik(): void
    {
        $teks = 'Hubungi saya di 081234567890 atau 0812-3456-7891, email budi@contoh.test, NIK 1234567890123456.';

        $hasil = Aspirasi::redaksi($teks);

        $this->assertStringNotContainsString('081234567890', $hasil);
        $this->assertStringNotContainsString('0812-3456-7891', $hasil);
        $this->assertStringNotContainsString('budi@contoh.test', $hasil);
        $this->assertStringNotContainsString('1234567890123456', $hasil);
        $this->assertStringContainsString(Aspirasi::TANDA_SAMAR, $hasil);
    }

    public function test_redaksi_mengenali_format_telepon_lain(): void
    {
        foreach (['+6281234567890', '6281234567890', '0812 3456 7890', '08123456789'] as $nomor) {
            $hasil = Aspirasi::redaksi('Nomor saya '.$nomor.' ya.');

            $this->assertStringNotContainsString($nomor, $hasil, 'Nomor '.$nomor.' tidak tersamarkan.');
        }
    }

    public function test_redaksi_tidak_merusak_teks_biasa(): void
    {
        $teks = 'Rapat pada 2026 membahas anggaran Rp1.500.000 untuk 12 kader.';

        $this->assertSame($teks, Aspirasi::redaksi($teks));
        $this->assertFalse(Aspirasi::memuatDataPribadi($teks));
    }

    public function test_memuat_data_pribadi_mengenali_nomor_di_dalam_kalimat(): void
    {
        $this->assertTrue(Aspirasi::memuatDataPribadi('Bisa dihubungi di 081234567890.'));
        $this->assertTrue(Aspirasi::memuatDataPribadi('Email saya a@b.test.'));
    }

    /* ===================== Papan publik ===================== */

    public function test_papan_publik_tidak_memuat_identitas_pengirim(): void
    {
        $this->tanggapi($this->aspirasi());

        $respons = $this->get('/aspirasi');

        $respons->assertOk();
        // Identitas TIDAK PERNAH sampai ke halaman publik — termasuk NAMA-nya,
        // bukan hanya email dan teleponnya.
        $respons->assertDontSee('Siti Aminah', false);
        $respons->assertDontSee('siti@contoh.test', false);
        $respons->assertDontSee('081234567890', false);
        // Yang boleh tampil hanya nomor tiket, kategori, isi, dan tanggapan.
        $respons->assertSee('ASP-2026-0001', false);
        $respons->assertSee('Ruang sekretariat perlu kipas angin', false);
    }

    public function test_papan_hanya_memuat_kolom_yang_boleh_tayang(): void
    {
        $this->tanggapi($this->aspirasi());

        $papan = app(Aspirasi::class)->papan();

        $this->assertSame(
            ['judul', 'isi', 'kategori', 'status', 'label_status', 'tanggapan', 'ditanggapi_pada', 'tanggal'],
            array_keys($papan[0]),
        );
    }

    public function test_papan_hanya_menampilkan_yang_sudah_ditanggapi(): void
    {
        $this->aspirasi(['judul' => 'Belum ditanggapi sama sekali']);
        $this->tanggapi($this->aspirasi(['judul' => 'Sudah ditanggapi pengurus']));

        $respons = $this->get('/aspirasi');

        $respons->assertOk();
        $respons->assertSee('Sudah ditanggapi pengurus', false);
        // Papan yang menampilkan keluhan tanpa jawaban terbaca seperti rayon
        // yang tidak pernah menanggapi apa pun.
        $respons->assertDontSee('Belum ditanggapi sama sekali', false);
    }

    public function test_isi_di_papan_sudah_dibersihkan_dari_data_pribadi(): void
    {
        $this->tanggapi($this->aspirasi([
            'judul' => 'Kipas angin sekretariat',
            'isi' => 'Ruangannya panas. Bisa dihubungi di 081234567890 atau lewat budi@contoh.test untuk menyerahkan sumbangannya.',
        ]));

        $respons = $this->get('/aspirasi');

        $respons->assertOk();
        $respons->assertSee('Ruangannya panas', false);
        $respons->assertDontSee('081234567890', false);
        $respons->assertDontSee('budi@contoh.test', false);
        $respons->assertSee(Aspirasi::TANDA_SAMAR, false);
    }

    public function test_nama_pengirim_tidak_pernah_tayang_meski_identitas_tersimpan(): void
    {
        $this->tanggapi($this->aspirasi());

        // Identitasnya TETAP tersimpan lengkap untuk pengurus.
        $aspirasi = Aspiration::query()->firstOrFail();
        $this->assertSame('Siti Aminah', $aspirasi->nama_pengirim);
        $this->assertSame('siti@contoh.test', $aspirasi->email_pengirim);

        // Tetapi tidak satu pun potongannya tayang di papan.
        $respons = $this->get('/aspirasi');
        $respons->assertOk();
        $respons->assertDontSee('Siti Aminah', false);
        $respons->assertDontSee('Siti', false);
    }

    public function test_sakelar_tampil_publik_mengeluarkan_aspirasi_dari_papan(): void
    {
        $aspirasi = $this->aspirasi();
        $this->tanggapi($aspirasi);

        app(Aspirasi::class)->aturTampil($aspirasi->fresh(), false, $this->sekretaris());

        $this->assertCount(0, app(Aspirasi::class)->papan());
        $this->get('/aspirasi')->assertDontSee('Ruang sekretariat perlu kipas angin', false);
    }

    /* ===================== Penerimaan ===================== */

    public function test_aspirasi_terkirim_lewat_formulir_dan_token_ditampilkan_sekali(): void
    {
        $respons = $this->post('/aspirasi', [
            'nama_pengirim' => 'Budi Santoso',
            'email_pengirim' => 'budi@contoh.test',
            'kategori' => Aspiration::KATEGORI_AKADEMIK,
            'judul' => 'Jadwal kajian bentrok dengan kuliah',
            'isi' => 'Jadwal kajian sering bentrok dengan mata kuliah wajib sehingga banyak kader tidak bisa ikut.',
        ]);

        $respons->assertRedirect();
        $respons->assertSessionHas('nomor_tiket_baru');
        $respons->assertSessionHas('token_baru');

        $aspirasi = Aspiration::query()->firstOrFail();

        $this->assertSame(Aspiration::STATUS_BARU, $aspirasi->status);
        $this->assertStringStartsWith('ASP-', $aspirasi->nomor_tiket);

        // Ikut halaman berikutnya untuk memastikan token benar-benar tampil.
        $this->get('/aspirasi')
            ->assertOk()
            ->assertSee($aspirasi->nomor_tiket, false)
            ->assertSee($aspirasi->token_lacak, false);
    }

    public function test_identitas_wajib_diisi(): void
    {
        $this->post('/aspirasi', [
            'kategori' => Aspiration::KATEGORI_AKADEMIK,
            'judul' => 'Tanpa identitas',
            'isi' => str_repeat('Isi aspirasi yang cukup panjang. ', 3),
        ])->assertSessionHasErrors(['nama_pengirim', 'email_pengirim']);

        $this->assertSame(0, Aspiration::query()->count());
    }

    public function test_isi_terlalu_pendek_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        app(Aspirasi::class)->kirim([
            'nama_pengirim' => 'Budi',
            'email_pengirim' => 'budi@contoh.test',
            'kategori' => Aspiration::KATEGORI_AKADEMIK,
            'judul' => 'Panas',
            'isi' => 'Panas.',
        ]);
    }

    public function test_honeypot_menolak_kiriman_bot(): void
    {
        $this->post('/aspirasi', [
            'nama_pengirim' => 'Bot',
            'email_pengirim' => 'bot@contoh.test',
            'kategori' => Aspiration::KATEGORI_LAINNYA,
            'judul' => 'Isi otomatis',
            'isi' => str_repeat('Kiriman otomatis dari bot. ', 3),
            'situs_web' => 'https://spam.contoh',
        ])->assertSessionHasErrors('situs_web');

        $this->assertSame(0, Aspiration::query()->count());
    }

    /* ===================== Pelacakan ===================== */

    public function test_lacak_butuh_nomor_tiket_dan_token_sekaligus(): void
    {
        $aspirasi = $this->aspirasi();

        // Nomor tiket saja TIDAK cukup: nomornya berurutan dan mudah ditebak.
        $this->assertNull(app(Aspirasi::class)->lacak($aspirasi->nomor_tiket, 'token-yang-salah'));
        $this->assertNull(app(Aspirasi::class)->lacak('ASP-2026-9999', $aspirasi->token_lacak));

        $this->assertNotNull(app(Aspirasi::class)->lacak($aspirasi->nomor_tiket, $aspirasi->token_lacak));
    }

    public function test_lacak_lewat_halaman_menampilkan_hasil(): void
    {
        $aspirasi = $this->aspirasi();
        $this->tanggapi($aspirasi, 'Sudah kami belikan kipas angin.');

        $this->post('/aspirasi/lacak', [
            'nomor_tiket' => $aspirasi->nomor_tiket,
            'token_lacak' => $aspirasi->token_lacak,
        ])->assertRedirect()->assertSessionHas('hasil_lacak');

        $this->get('/aspirasi')
            ->assertOk()
            ->assertSee('Sudah kami belikan kipas angin.', false);
    }

    public function test_lacak_dengan_token_salah_tidak_memberi_tahu_apakah_nomornya_ada(): void
    {
        $aspirasi = $this->aspirasi();

        $this->post('/aspirasi/lacak', [
            'nomor_tiket' => $aspirasi->nomor_tiket,
            'token_lacak' => 'token-palsu',
        ])->assertRedirect()->assertSessionHas('galat_lacak');

        $respons = $this->get('/aspirasi');

        $respons->assertOk();
        $respons->assertSee('Tidak ada aspirasi yang cocok', false);
        $respons->assertDontSee('Ruang sekretariat perlu kipas angin', false);
    }

    /* ===================== Panel ===================== */

    public function test_tanggapan_lewat_panel_tayang_di_papan_publik(): void
    {
        $aspirasi = $this->aspirasi();
        $petugas = $this->sekretaris();

        $this->actingAs($petugas)
            ->post("/panel/aspirasi/{$aspirasi->id}/tanggapi", [
                'tanggapan' => 'Kipas angin sudah dibeli pekan ini.',
                'status' => Aspiration::STATUS_SELESAI,
            ])
            ->assertRedirect();

        $this->assertSame(Aspiration::STATUS_SELESAI, $aspirasi->fresh()->status);
        $this->assertSame($petugas->id, $aspirasi->fresh()->ditanggapi_oleh);

        $this->get('/aspirasi')->assertSee('Kipas angin sudah dibeli pekan ini.', false);
    }

    public function test_menutup_aspirasi_wajib_beralasan(): void
    {
        $aspirasi = $this->aspirasi();
        $petugas = $this->sekretaris();

        $this->actingAs($petugas)
            ->post("/panel/aspirasi/{$aspirasi->id}/tutup", ['tanggapan' => ''])
            ->assertSessionHasErrors('tanggapan');

        $this->assertNull($aspirasi->fresh()->tanggapan);

        $this->actingAs($petugas)
            ->post("/panel/aspirasi/{$aspirasi->id}/tutup", ['tanggapan' => 'Bukan kewenangan rayon.'])
            ->assertRedirect();

        $this->assertSame(Aspiration::STATUS_DITOLAK, $aspirasi->fresh()->status);
        // Alasannya ikut tayang: pengirim berhak tahu mengapa aspirasinya berhenti.
        $this->get('/aspirasi')->assertSee('Bukan kewenangan rayon.', false);
    }

    public function test_tanggapan_tidak_bisa_langsung_ditolak_lewat_jalur_tanggapi(): void
    {
        $aspirasi = $this->aspirasi();

        $this->actingAs($this->sekretaris())
            ->post("/panel/aspirasi/{$aspirasi->id}/tanggapi", [
                'tanggapan' => 'Tidak bisa.',
                'status' => Aspiration::STATUS_DITOLAK,
            ])
            ->assertSessionHasErrors('status');

        $this->assertNull($aspirasi->fresh()->tanggapan);
    }

    public function test_identitas_tidak_dikirim_ke_pengguna_tanpa_izin(): void
    {
        $this->aspirasi();

        // Izin diberikan LANGSUNG, bukan lewat peran: tidak ada peran bawaan
        // yang punya aspirations.view tanpa aspirations.view-identity.
        $tanpaIzin = User::factory()->create(['email_verified_at' => now()]);
        $tanpaIzin->givePermissionTo('aspirations.view');

        $this->actingAs($tanpaIzin)
            ->get('/panel/aspirasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Aspirasi/Index', false)
                ->where('bolehLihatIdentitas', false)
                ->where('daftar.0.nama_pengirim', null)
                ->where('daftar.0.email_pengirim', null)
                ->where('daftar.0.telepon_pengirim', null));
    }

    public function test_identitas_dikirim_ke_yang_berhak(): void
    {
        $this->aspirasi();

        $this->actingAs($this->sekretaris())
            ->get('/panel/aspirasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bolehLihatIdentitas', true)
                ->where('daftar.0.nama_pengirim', 'Siti Aminah')
                ->where('daftar.0.email_pengirim', 'siti@contoh.test'));
    }

    public function test_panel_aspirasi_butuh_izin(): void
    {
        $kader = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($kader)->get('/panel/aspirasi')->assertForbidden();

        $this->actingAs($this->sekretaris())->get('/panel/aspirasi')->assertOk();
    }

    public function test_aspirasi_yang_memuat_data_pribadi_ditandai_untuk_diperiksa(): void
    {
        $this->aspirasi(['isi' => 'Ruangannya panas. Hubungi saya di 081234567890 untuk sumbangan kipas.']);

        $this->actingAs($this->sekretaris())
            ->get('/panel/aspirasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('daftar.0.perlu_diperiksa', true));
    }

    public function test_ekspor_csv_mengosongkan_identitas_bila_tidak_berhak(): void
    {
        $this->aspirasi();

        $petugas = User::factory()->create(['email_verified_at' => now()]);
        $petugas->givePermissionTo('aspirations.view');

        $respons = $this->actingAs($petugas)->get('/panel/aspirasi/ekspor');

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $isi = $respons->getContent();

        $this->assertStringNotContainsString('siti@contoh.test', $isi);
        $this->assertStringContainsString('Ruang sekretariat perlu kipas angin', $isi);
    }

    public function test_kader_yang_masuk_namanya_terisi_otomatis_di_formulir(): void
    {
        $user = User::factory()->create(['name' => 'Kader Terdaftar', 'email_verified_at' => now()]);
        $user->givePermissionTo('dashboard.view');

        $member = new Member;
        $member->user_id = $user->id;
        $member->nama_lengkap = 'Kader Terdaftar';
        $member->status = Member::STATUS_AKTIF;
        $member->jalur = Member::JALUR_KADER;
        $member->save();

        $this->actingAs($user)
            ->get('/aspirasi')
            ->assertOk()
            ->assertSee('Kader Terdaftar', false);

        $this->actingAs($user)->post('/aspirasi', [
            'nama_pengirim' => 'Kader Terdaftar',
            'email_pengirim' => $user->email,
            'kategori' => Aspiration::KATEGORI_KEGIATAN,
            'judul' => 'Usulan kajian rutin',
            'isi' => 'Sebaiknya kajian rutin diadakan dua pekan sekali agar lebih teratur.',
        ])->assertRedirect();

        // Tautan ke data anggota tersimpan, supaya pengurus tahu ini kader.
        $this->assertSame($member->id, Aspiration::query()->firstOrFail()->member_id);
    }
}
