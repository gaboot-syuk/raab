<?php

namespace Tests\Feature;

use App\Models\AlumniProfile;
use App\Models\Member;
use App\Models\OrganisationUnit;
use App\Models\Period;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\PeriodSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 — Organisasi & Alumni.
 *
 * TIGA JANJI YANG DIJAGA DI SINI:
 *  1. Mengganti periode berjalan TIDAK merusak data periode lama.
 *  2. Hanya Superadmin yang boleh mengubah daftar periode.
 *  3. Data pribadi alumni hanya tampil bila pemiliknya mengizinkan.
 */
class OrganisasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            UnitSeeder::class,
            PeriodSeeder::class,
            PositionSeeder::class,
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
     */
    private function anggota(array $ganti = []): Member
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $data = array_merge([
            'nama_lengkap' => 'Ahmad Fauzi',
            'status' => Member::STATUS_AKTIF,
            'jalur' => Member::JALUR_KADER,
            'angkatan' => 2024,
            'program_studi' => 'Hukum Tata Negara',
            'fakultas' => 'Syariah',
            'telepon' => '081234567890',
            'email_kontak' => 'ahmad@contoh.test',
        ], $ganti);

        $anggota = new Member($data);
        $anggota->user_id = $user->id;
        $anggota->save();

        return $anggota;
    }

    private function lso(string $nama = 'LSO Jurnalistik'): OrganisationUnit
    {
        $unit = new OrganisationUnit;
        $unit->jenis = OrganisationUnit::JENIS_LSO;
        $unit->nama = $nama;
        $unit->slug = \Illuminate\Support\Str::slug($nama);
        $unit->aktif = true;

        return $unit->save() ? $unit : $unit;
    }

    /* ===================== Halaman publik ===================== */

    public function test_halaman_struktur_dapat_dibuka(): void
    {
        $this->get('/struktur')->assertOk();
    }

    public function test_struktur_menampilkan_pengurus_periode_berjalan(): void
    {
        $periode = Period::query()->aktif()->firstOrFail();
        $jabatan = Position::query()->where('level', 1)->firstOrFail();
        $anggota = $this->anggota(['nama_lengkap' => 'Ketua Terpilih']);

        $penugasan = new PositionAssignment;
        $penugasan->period_id = $periode->id;
        $penugasan->position_id = $jabatan->id;
        $penugasan->member_id = $anggota->id;
        $penugasan->aktif = true;
        $penugasan->save();

        $this->get('/struktur')
            ->assertOk()
            ->assertSee('Ketua Terpilih', false);
    }

    public function test_pemilih_periode_tidak_menghapus_data_periode_lama(): void
    {
        $semua = Period::query()->orderBy('tahun_mulai')->get();
        $this->assertGreaterThanOrEqual(2, $semua->count(), 'Butuh minimal dua periode untuk menguji pemilih.');

        [$lama, $baru] = [$semua->first(), $semua->last()];

        $jabatan = Position::query()->where('level', 1)->firstOrFail();

        foreach ([[$lama, 'Ketua Periode Lama'], [$baru, 'Ketua Periode Baru']] as [$periode, $nama]) {
            $penugasan = new PositionAssignment;
            $penugasan->period_id = $periode->id;
            $penugasan->position_id = $jabatan->id;
            $penugasan->nama_manual = $nama;
            $penugasan->aktif = true;
            $penugasan->save();
        }

        // Membuka periode lama tetap menampilkan pengurus periode itu saja.
        $isiLama = (string) $this->get('/struktur?periode='.$lama->id)->assertOk()->getContent();

        $this->assertStringContainsString('Ketua Periode Lama', $isiLama);
        $this->assertStringNotContainsString('Ketua Periode Baru', $isiLama);

        // Dan penugasan periode lama tidak tersentuh oleh pilihan itu.
        $this->assertSame(1, PositionAssignment::query()->where('period_id', $lama->id)->count());
        $this->assertSame(1, PositionAssignment::query()->where('period_id', $baru->id)->count());
    }

    public function test_menandai_periode_berjalan_tidak_menghapus_penugasan_lama(): void
    {
        $superadmin = $this->pengurus('superadmin');

        $sebelum = PositionAssignment::query()->count();
        $target = Period::query()->orderBy('tahun_mulai')->firstOrFail();

        $this->actingAs($superadmin)
            ->post('/panel/organisasi/periode/'.$target->id.'/aktifkan')
            ->assertSessionHas('sukses');

        $this->assertTrue($target->fresh()->aktif);
        $this->assertSame(1, Period::query()->where('aktif', true)->count(), 'Hanya satu periode boleh berjalan.');
        $this->assertSame($sebelum, PositionAssignment::query()->count(), 'Penugasan lama tidak boleh hilang.');
    }

    public function test_halaman_lso_dan_profilnya_dapat_dibuka(): void
    {
        $unit = $this->lso();

        $this->get('/lso')->assertOk()->assertSee($unit->nama, false);
        $this->get('/lso/'.$unit->slug)->assertOk()->assertSee($unit->nama, false);
    }

    public function test_unit_nonaktif_tidak_dapat_dibuka_publik(): void
    {
        $unit = $this->lso('LSO Lama');
        $unit->forceFill(['aktif' => false])->save();

        $this->get('/lso/'.$unit->slug)->assertNotFound();
        $this->get('/lso')->assertOk()->assertDontSee('LSO Lama', false);
    }

    public function test_halaman_galeri_dapat_dibuka(): void
    {
        $this->get('/galeri')->assertOk();
    }

    public function test_profil_kader_hanya_terbuka_bila_pemiliknya_bersedia(): void
    {
        $anggota = $this->anggota(['nama_lengkap' => 'Rina Maulida']);

        $this->get('/prestasi/kader/'.$anggota->slug)->assertNotFound();

        $anggota->forceFill(['profil_publik' => true])->save();

        $this->get('/prestasi/kader/'.$anggota->slug)
            ->assertOk()
            ->assertSee('Rina Maulida', false);
    }

    /* ===================== CRUD panel ===================== */

    public function test_periode_hanya_boleh_diubah_superadmin(): void
    {
        $sekretaris = $this->pengurus('sekretaris');

        // Melihat boleh…
        $this->actingAs($sekretaris)->get('/panel/organisasi/periode')->assertOk();

        // …mengubah tidak.
        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/periode', [
                'tahun_mulai' => 2030,
                'tahun_selesai' => 2031,
            ])
            ->assertForbidden();
    }

    public function test_superadmin_dapat_menambah_periode_baru(): void
    {
        $superadmin = $this->pengurus('superadmin');

        $this->actingAs($superadmin)
            ->post('/panel/organisasi/periode', [
                'nama' => '2030/2031',
                'tahun_mulai' => 2030,
                'tahun_selesai' => 2031,
                'urutan' => 0,
            ])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('periods', ['nama' => '2030/2031']);
    }

    public function test_periode_yang_masih_punya_penugasan_tidak_dapat_dihapus(): void
    {
        $superadmin = $this->pengurus('superadmin');

        $periode = Period::query()->orderBy('tahun_mulai')->firstOrFail();
        $periode->forceFill(['aktif' => false])->save();

        $jabatan = Position::query()->firstOrFail();

        $penugasan = new PositionAssignment;
        $penugasan->period_id = $periode->id;
        $penugasan->position_id = $jabatan->id;
        $penugasan->nama_manual = 'Pengurus Lama';
        $penugasan->save();

        $this->actingAs($superadmin)
            ->delete('/panel/organisasi/periode/'.$periode->id)
            ->assertSessionHas('galat');

        $this->assertDatabaseHas('periods', ['id' => $periode->id]);
    }

    public function test_periode_berjalan_tidak_dapat_dihapus(): void
    {
        $superadmin = $this->pengurus('superadmin');
        $periode = Period::query()->aktif()->firstOrFail();

        $this->actingAs($superadmin)
            ->delete('/panel/organisasi/periode/'.$periode->id)
            ->assertSessionHas('galat');

        $this->assertDatabaseHas('periods', ['id' => $periode->id, 'aktif' => true]);
    }

    public function test_jabatan_dapat_ditambah_dan_dihapus(): void
    {
        $sekretaris = $this->pengurus('sekretaris');

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/jabatan', [
                'nama' => 'Koordinator Advokasi',
                'level' => 2,
                'urutan' => 5,
                'aktif' => true,
            ])
            ->assertSessionHas('sukses');

        $jabatan = Position::query()->where('nama', 'Koordinator Advokasi')->firstOrFail();

        $this->actingAs($sekretaris)
            ->delete('/panel/organisasi/jabatan/'.$jabatan->id)
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('positions', ['id' => $jabatan->id]);
    }

    public function test_penugasan_wajib_memuat_anggota_atau_nama_manual(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $periode = Period::query()->aktif()->firstOrFail();
        $jabatan = Position::query()->firstOrFail();

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/penugasan', [
                'period_id' => $periode->id,
                'position_id' => $jabatan->id,
                'member_id' => null,
                'nama_manual' => '',
            ])
            ->assertSessionHasErrors('nama_manual');
    }

    public function test_penugasan_boleh_dibuat_untuk_bukan_anggota(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $periode = Period::query()->aktif()->firstOrFail();
        $jabatan = Position::query()->firstOrFail();

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/penugasan', [
                'period_id' => $periode->id,
                'position_id' => $jabatan->id,
                'nama_manual' => 'Dr. Pembina, M.Pd.',
                'keterangan' => 'Dosen Pembina',
            ])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('position_assignments', [
            'period_id' => $periode->id,
            'nama_manual' => 'Dr. Pembina, M.Pd.',
            'member_id' => null,
        ]);
    }

    public function test_unit_yang_masih_punya_anggota_tidak_dapat_dihapus(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $unit = $this->lso('LSO Seni');

        $this->anggota(['unit_id' => $unit->id]);

        $this->actingAs($sekretaris)
            ->delete('/panel/organisasi/unit/'.$unit->id)
            ->assertSessionHas('galat');

        $this->assertDatabaseHas('organisation_units', ['id' => $unit->id]);
    }

    public function test_unit_baru_mendapat_slug_otomatis(): void
    {
        $sekretaris = $this->pengurus('sekretaris');

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/unit', [
                'jenis' => OrganisationUnit::JENIS_LSO,
                'nama' => 'LSO Lingkungan',
                'aktif' => true,
            ])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('organisation_units', [
            'nama' => 'LSO Lingkungan',
            'slug' => 'lso-lingkungan',
        ]);
    }

    /* ===================== Anggota & alumni ===================== */

    public function test_slug_anggota_dibuat_otomatis_dan_unik(): void
    {
        $pertama = $this->anggota(['nama_lengkap' => 'Nama Sama']);
        $kedua = $this->anggota(['nama_lengkap' => 'Nama Sama']);

        $this->assertSame('nama-sama', $pertama->slug);
        $this->assertSame('nama-sama-2', $kedua->slug);
    }

    public function test_direktori_anggota_dapat_disaring_per_fakultas(): void
    {
        $this->anggota(['nama_lengkap' => 'Anak Syariah', 'fakultas' => 'Syariah']);
        $this->anggota(['nama_lengkap' => 'Anak Tarbiyah', 'fakultas' => 'Tarbiyah']);

        $this->get('/anggota?fakultas=Tarbiyah')
            ->assertOk()
            ->assertSee('Anak Tarbiyah', false)
            ->assertDontSee('Anak Syariah', false);
    }

    public function test_kontak_alumni_tersembunyi_kecuali_diizinkan(): void
    {
        $tertutup = $this->anggota([
            'nama_lengkap' => 'Alumni Tertutup',
            'status' => Member::STATUS_ALUMNI,
            'telepon' => '081111111111',
        ]);

        $terbuka = $this->anggota([
            'nama_lengkap' => 'Alumni Terbuka',
            'status' => Member::STATUS_ALUMNI,
            'telepon' => '082222222222',
        ]);

        foreach ([[$tertutup, false], [$terbuka, true]] as [$anggota, $boleh]) {
            $profil = new AlumniProfile;
            $profil->member_id = $anggota->id;
            $profil->kontak_publik = ['telepon' => $boleh, 'instansi' => $boleh];
            $profil->instansi = $boleh ? 'Kantor Terbuka' : 'Kantor Tertutup';
            $profil->save();
        }

        $isi = (string) $this->get('/alumni')->assertOk()->getContent();

        // Nomor yang tidak diizinkan sama sekali tidak dikirim ke peramban.
        $this->assertStringNotContainsString('081111111111', $isi);
        $this->assertStringContainsString('082222222222', $isi);
        $this->assertStringNotContainsString('Kantor Tertutup', $isi);
    }

    public function test_direktori_alumni_dapat_disaring_per_instansi(): void
    {
        $satu = $this->anggota(['nama_lengkap' => 'Alumni Satu', 'status' => Member::STATUS_ALUMNI]);
        $dua = $this->anggota(['nama_lengkap' => 'Alumni Dua', 'status' => Member::STATUS_ALUMNI]);

        foreach ([[$satu, 'Kemenag'], [$dua, 'Bank Syariah']] as [$anggota, $instansi]) {
            $profil = new AlumniProfile;
            $profil->member_id = $anggota->id;
            $profil->instansi = $instansi;
            $profil->kontak_publik = ['instansi' => true];
            $profil->save();
        }

        $this->get('/alumni?instansi=Kemenag')
            ->assertOk()
            ->assertSee('Kemenag', false)
            ->assertDontSee('Bank Syariah', false);
    }

    public function test_daftar_mentor_hanya_untuk_pengurus(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $kader = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($sekretaris)->get('/panel/organisasi/mentor')->assertOk();
        $this->actingAs($kader)->get('/panel/organisasi/mentor')->assertForbidden();
    }

    public function test_daftar_mentor_menampilkan_alumni_yang_bersedia(): void
    {
        $sekretaris = $this->pengurus('sekretaris');

        $anggota = $this->anggota([
            'nama_lengkap' => 'Alumni Mentor',
            'status' => Member::STATUS_ALUMNI,
        ]);

        $profil = new AlumniProfile;
        $profil->member_id = $anggota->id;
        $profil->bersedia_mentor = true;
        $profil->topik_mentor = 'Jurnalistik investigasi';
        $profil->save();

        $this->actingAs($sekretaris)
            ->get('/panel/organisasi/mentor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Organisasi/Mentor', false)
                ->where('jumlah', 1)
                ->where('daftar.0.nama', 'Alumni Mentor'),
            );
    }

    public function test_halaman_panel_fase4_terbuka_bagi_pengurus_yang_berhak(): void
    {
        $sekretaris = $this->pengurus('sekretaris');

        foreach ([
            '/panel/organisasi/periode',
            '/panel/organisasi/jabatan',
            '/panel/organisasi/penugasan',
            '/panel/organisasi/unit',
        ] as $tautan) {
            $this->actingAs($sekretaris)->get($tautan)->assertOk();
        }
    }

    /* ===================== Peta, saringan, tautan profil ===================== */

    public function test_direktori_anggota_dapat_disaring_per_program_studi(): void
    {
        $this->anggota(['nama_lengkap' => 'Anak Hukum', 'program_studi' => 'Hukum Tata Negara']);
        $this->anggota(['nama_lengkap' => 'Anak Komunikasi', 'program_studi' => 'Komunikasi']);

        $this->get('/anggota?prodi=Komunikasi')
            ->assertOk()
            ->assertSee('Anak Komunikasi', false)
            ->assertDontSee('Anak Hukum', false);
    }

    public function test_peta_sebaran_hanya_memuat_alumni_yang_mengisi_koordinat(): void
    {
        $berkoordinat = $this->anggota([
            'nama_lengkap' => 'Alumni Berkoordinat',
            'status' => Member::STATUS_ALUMNI,
        ]);

        $tanpaKoordinat = $this->anggota([
            'nama_lengkap' => 'Alumni Tanpa Koordinat',
            'status' => Member::STATUS_ALUMNI,
        ]);

        $profil = new AlumniProfile;
        $profil->member_id = $berkoordinat->id;
        $profil->kota_domisili = 'Sukoharjo';
        $profil->latitude = -7.6833;
        $profil->longitude = 110.8333;
        $profil->kontak_publik = ['instansi' => true];
        $profil->instansi = 'Kantor Wilayah';
        $profil->save();

        $polos = new AlumniProfile;
        $polos->member_id = $tanpaKoordinat->id;
        $polos->kota_domisili = 'Surabaya';
        $polos->save();

        $halaman = $this->get('/alumni')->assertOk();
        $isi = (string) $halaman->getContent();

        $this->assertStringContainsString('id="peta-alumni"', $isi);

        // Isi atribut di-escape Blade, jadi dikembalikan dulu sebelum dibaca.
        // Dipotong pada tanda kutip penutup, bukan pada '">' — karena atribut
        // ini diikuti baris baru, bukan langsung oleh tag penutup.
        $mentah = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($isi, 'data-titik="'),
            '"',
        );

        $data = json_decode(html_entity_decode($mentah), true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data, 'Hanya alumni berkoordinat yang boleh masuk ke peta.');
        $this->assertEqualsWithDelta(-7.6833, (float) $data[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(110.8333, (float) $data[0]['lng'], 0.0001);
        $this->assertSame('Alumni Berkoordinat', $data[0]['nama']);
        $this->assertSame('Kantor Wilayah', $data[0]['instansi']);
    }

    public function test_instansi_alumni_tidak_masuk_peta_bila_tidak_diizinkan(): void
    {
        $anggota = $this->anggota(['nama_lengkap' => 'Alumni Tertutup Peta', 'status' => Member::STATUS_ALUMNI]);

        $profil = new AlumniProfile;
        $profil->member_id = $anggota->id;
        $profil->latitude = -7.0;
        $profil->longitude = 110.0;
        $profil->instansi = 'Kantor Rahasia';
        $profil->kontak_publik = ['instansi' => false];
        $profil->save();

        $isi = (string) $this->get('/alumni')->assertOk()->getContent();

        $mentah = \Illuminate\Support\Str::before(
            \Illuminate\Support\Str::after($isi, 'data-titik="'),
            '"',
        );

        $data = json_decode(html_entity_decode($mentah), true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertNull($data[0]['instansi'], 'Instansi yang tidak diizinkan tidak boleh ikut ke peta.');
        $this->assertStringNotContainsString('Kantor Rahasia', html_entity_decode($isi));
    }

    public function test_direktori_menautkan_ke_profil_publik_hanya_bila_dibuka(): void
    {
        $terbuka = $this->anggota([
            'nama_lengkap' => 'Kader Terbuka',
            'profil_publik' => true,
        ]);

        $this->get('/anggota')
            ->assertOk()
            ->assertSee('/prestasi/kader/'.$terbuka->slug, false);

        $terbuka->forceFill(['profil_publik' => false])->save();

        $this->get('/anggota')
            ->assertOk()
            ->assertDontSee('/prestasi/kader/'.$terbuka->slug, false);
    }

    public function test_superadmin_dapat_mengubah_profil_publik_anggota(): void
    {
        // Hanya penanda bahwa kolom baru ikut tersimpan lewat model biasa.
        $anggota = $this->anggota(['nama_lengkap' => 'Kader Uji Simpan']);
        $this->assertFalse((bool) $anggota->profil_publik);

        $anggota->forceFill(['profil_publik' => true])->save();

        $this->assertTrue((bool) $anggota->fresh()->profil_publik);
    }
}
