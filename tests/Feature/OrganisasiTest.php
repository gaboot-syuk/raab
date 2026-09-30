<?php

namespace Tests\Feature;

use App\Models\AlumniProfile;
use App\Models\MediaLibrary;
use App\Models\Member;
use App\Models\OrganisationUnit;
use App\Models\Period;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\UnitAgenda;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\PeriodSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    /**
     * PNG 1×1 piksel, ditulis apa adanya supaya uji tidak bergantung pada
     * ekstensi GD — yang memang tidak tersedia di lingkungan pengujian.
     */
    private const PNG_KECIL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

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

    public function test_daftar_lso_tetap_terbuka_meski_ada_unit_tanpa_slug(): void
    {
        $satunya = $this->lso('LSO Ber-slug');
        $polos = $this->lso('LSO Tanpa Slug');

        /*
         * Ditulis lewat kueri, bukan lewat model: pengait model akan segera
         * mengisi slug yang kosong, dan justru keadaan rusak itulah yang perlu
         * diuji di sini.
         *
         * Inilah yang pernah mematikan seluruh /lso di produksi —
         * route('public.lso.detail', null) melempar UrlGenerationException,
         * dan satu unit tanpa slug menjatuhkan halaman daftarnya.
         */
        \Illuminate\Support\Facades\DB::table('organisation_units')
            ->where('id', $polos->id)
            ->update(['slug' => null]);

        $halaman = $this->get('/lso')->assertOk();

        // Unit tanpa slug tetap tampil — hanya saja tidak bertaut.
        $halaman->assertSee('LSO Tanpa Slug', false);
        $halaman->assertSee('/lso/'.$satunya->slug, false);
    }

    public function test_migrasi_pengisi_slug_membereskan_unit_yang_belum_berslug(): void
    {
        $unit = $this->lso('LSO Belum Berslug');

        \Illuminate\Support\Facades\DB::table('organisation_units')
            ->where('id', $unit->id)
            ->update(['slug' => null]);

        $migrasi = require database_path('migrations/2026_09_27_220000_backfill_unit_slugs.php');
        $migrasi->up();

        $this->assertSame('lso-belum-berslug', $unit->fresh()->slug);
    }

    public function test_migrasi_pengisi_slug_tidak_menabrak_slug_yang_sudah_dipakai(): void
    {
        $adaSlug = $this->lso('LSO Kembar');

        /*
         * Dibuat LANGSUNG, tanpa memakai pembantu `lso()`.
         *
         * Pembantu itu mengisi kolom slug sendiri, sehingga pengait model —
         * satu-satunya tempat bentrokan slug diselesaikan — tidak pernah ikut
         * bermain. Yang diuji di sini justru pengait itu.
         *
         * Namanya juga harus BERBEDA dari 'LSO Kembar': pasangan (jenis, nama)
         * dijaga indeks unik. Yang dibutuhkan bukan nama yang sama, melainkan
         * nama yang menghasilkan slug yang sama — "LSO-Kembar" -> lso-kembar.
         */
        $belum = new OrganisationUnit;
        $belum->jenis = OrganisationUnit::JENIS_LSO;
        $belum->nama = 'LSO-Kembar';
        $belum->aktif = true;
        $belum->save();

        $this->assertSame('lso-kembar', $adaSlug->slug);
        $this->assertSame('lso-kembar-2', $belum->slug);

        \Illuminate\Support\Facades\DB::table('organisation_units')
            ->where('id', $belum->id)
            ->update(['slug' => null]);

        $migrasi = require database_path('migrations/2026_09_27_220000_backfill_unit_slugs.php');
        $migrasi->up();

        $this->assertSame('lso-kembar-2', $belum->fresh()->slug);
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

    /* ========== Kelola unit: pengurus, anggota, agenda ========== */

    public function test_halaman_kelola_unit_memuat_ketiga_bagian(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $unit = $this->lso('LSO Musik');

        $this->actingAs($sekretaris)
            ->get('/panel/organisasi/unit/'.$unit->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Organisasi/UnitDetail', false)
                ->where('unit.nama', 'LSO Musik')
                ->has('pengurus')
                ->has('anggota')
                ->has('agenda')
                ->has('pilihanAnggota'),
            );
    }

    /**
     * Agenda hanya disajikan untuk LSO.
     *
     * Biro adalah bagian struktural rayon dan tidak menyelenggarakan agenda
     * sendiri, jadi halaman unit biro tidak membawa data agenda.
     */
    public function test_agenda_hanya_disajikan_untuk_lso(): void
    {
        $sekretaris = $this->pengurus('sekretaris');

        $lso = $this->lso('LSO Tari');
        $biro = OrganisationUnit::query()->where('jenis', OrganisationUnit::JENIS_BIRO)->firstOrFail();

        $this->actingAs($sekretaris)
            ->get('/panel/organisasi/unit/'.$lso->id)
            ->assertInertia(fn ($page) => $page->where('unit.lso', true));

        $this->actingAs($sekretaris)
            ->get('/panel/organisasi/unit/'.$biro->id)
            ->assertInertia(fn ($page) => $page->where('unit.lso', false));
    }

    /**
     * Agenda boleh memakai gambar dari pustaka media.
     *
     * Gambarnya dirujuk lewat `gambar_media_id`, bukan disimpan di kolom
     * sendiri — mengikuti pola poster Event, supaya penggantian ukuran dan
     * pembersihan berkas ditangani satu tempat.
     *
     * Uji ini memakai superadmin karena menyentuh TIGA gugus izin sekaligus
     * (unggah media, kelola agenda, dan lihat unit). Izinnya sendiri diuji
     * terpisah, bukan di sini.
     */
    public function test_agenda_dapat_memakai_gambar_dari_pustaka_media(): void
    {
        Storage::fake('public');

        $pengurus = $this->pengurus('superadmin');
        $unit = $this->lso('LSO Poster');

        $this->actingAs($pengurus)
            ->post('/panel/media', [
                'berkas' => [UploadedFile::fake()->createWithContent('poster.png', base64_decode(self::PNG_KECIL))],
                'koleksi' => 'gambar',
                'alt' => 'Poster agenda',
            ])
            ->assertSessionHas('sukses');

        $media = MediaLibrary::induk()->getMedia('gambar')->firstOrFail();

        $this->actingAs($pengurus)
            ->post('/panel/organisasi/agenda', [
                'unit_id' => $unit->id,
                'judul' => ['id' => 'Pengajian Rutin'],
                'mulai' => '2026-10-10 19:00:00',
                'gambar_media_id' => $media->id,
                'publik' => true,
            ])
            ->assertSessionHas('sukses');

        $this->assertSame($media->id, (int) UnitAgenda::query()->firstOrFail()->gambar_media_id);

        // Halaman kelola unit membawa id gambar DAN URL-nya untuk pratinjau.
        $this->actingAs($pengurus)
            ->get('/panel/organisasi/unit/'.$unit->id)
            ->assertInertia(fn ($page) => $page
                ->where('agenda.0.gambar_media_id', $media->id)
                ->has('agenda.0.gambar')
                ->has('pilihanGambar', 1),
            );
    }

    public function test_agenda_tetap_sah_tanpa_gambar(): void
    {
        $konten = $this->pengurus('konten_manager');
        $unit = $this->lso('LSO Tanpa Gambar');

        $this->actingAs($konten)
            ->post('/panel/organisasi/agenda', [
                'unit_id' => $unit->id,
                'judul' => ['id' => 'Rapat Tanpa Poster'],
                'mulai' => '2026-10-11 19:00:00',
                'publik' => true,
            ])
            ->assertSessionHas('sukses');

        // Gambar bersifat opsional: agenda tanpa gambar tetap sah.
        $this->assertNull(UnitAgenda::query()->firstOrFail()->gambar_media_id);
    }

    public function test_gambar_agenda_yang_tidak_ada_ditolak(): void
    {
        $konten = $this->pengurus('konten_manager');
        $unit = $this->lso('LSO Salah Gambar');

        $this->actingAs($konten)
            ->post('/panel/organisasi/agenda', [
                'unit_id' => $unit->id,
                'judul' => ['id' => 'Agenda Salah'],
                'mulai' => '2026-10-12 19:00:00',
                'gambar_media_id' => 999999,
            ])
            ->assertSessionHasErrors('gambar_media_id');

        $this->assertDatabaseCount('unit_agendas', 0);
    }

    public function test_anggota_dapat_ditambahkan_dan_dilepas_dari_unit(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $unit = $this->lso('LSO Teater');
        $orang = $this->anggota(['nama_lengkap' => 'Anggota Teater']);

        $this->assertNull($orang->unit_id);

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/unit/'.$unit->id.'/anggota', ['member_id' => $orang->id])
            ->assertSessionHas('sukses');

        $this->assertSame($unit->id, $orang->fresh()->unit_id);

        $this->actingAs($sekretaris)
            ->delete('/panel/organisasi/unit/'.$unit->id.'/anggota/'.$orang->id)
            ->assertSessionHas('sukses');

        $this->assertNull($orang->fresh()->unit_id);

        // Anggotanya TIDAK dihapus — hanya kaitannya ke unit yang dilepas.
        $this->assertDatabaseHas('members', ['id' => $orang->id]);
    }

    /**
     * Memindahkan anggota berarti MENGGANTI unitnya, bukan menambah
     * keanggotaan kedua. Pesannya dibedakan supaya pengurus sadar bahwa
     * anggota itu berpindah, bukan sekadar ditambahkan.
     */
    public function test_memindahkan_anggota_mengganti_unit_lama(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $asal = $this->lso('LSO Asal');
        $tujuan = $this->lso('LSO Tujuan');
        $orang = $this->anggota(['nama_lengkap' => 'Budi Pindah', 'unit_id' => $asal->id]);

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/unit/'.$tujuan->id.'/anggota', ['member_id' => $orang->id])
            ->assertSessionHas('sukses', 'Anggota Budi Pindah dipindahkan ke LSO Tujuan.');

        $this->assertSame($tujuan->id, $orang->fresh()->unit_id);
    }

    public function test_melepas_anggota_dari_unit_yang_salah_ditolak(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $unit = $this->lso('LSO Salah');
        $orang = $this->anggota(['nama_lengkap' => 'Bukan Anggota Sini']);

        $this->actingAs($sekretaris)
            ->delete('/panel/organisasi/unit/'.$unit->id.'/anggota/'.$orang->id)
            ->assertSessionHas('galat');

        $this->assertNull($orang->fresh()->unit_id);
    }

    public function test_pengurus_dapat_ditunjuk_lewat_halaman_kelola_unit(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $unit = $this->lso('LSO Kaderisasi');
        $periode = Period::query()->aktif()->firstOrFail();

        $jabatan = new Position;
        $jabatan->nama = 'Ketua';
        $jabatan->level = 2;
        $jabatan->urutan = 0;
        $jabatan->unit_id = $unit->id;
        $jabatan->rangkap_diizinkan = false;
        $jabatan->aktif = true;
        $jabatan->save();

        $orang = $this->anggota(['nama_lengkap' => 'Ketua Terpilih', 'unit_id' => $unit->id]);

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/penugasan', [
                'period_id' => $periode->id,
                'position_id' => $jabatan->id,
                'member_id' => $orang->id,
            ])
            ->assertSessionHas('sukses');

        $this->actingAs($sekretaris)
            ->get('/panel/organisasi/unit/'.$unit->id)
            ->assertInertia(fn ($page) => $page
                ->where('pengurus.0.jabatan', 'Ketua')
                ->where('pengurus.0.nama', 'Ketua Terpilih'),
            );
    }

    public function test_pengurus_tanpa_izin_tidak_dapat_mengubah_anggota_unit(): void
    {
        $unit = $this->lso('LSO Terkunci');
        $orang = $this->anggota(['nama_lengkap' => 'Tidak Boleh Pindah']);
        $tanpaPeran = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($tanpaPeran)
            ->post('/panel/organisasi/unit/'.$unit->id.'/anggota', ['member_id' => $orang->id])
            ->assertForbidden();

        $this->assertNull($orang->fresh()->unit_id);
    }

    /* ========== Konten Manager & halaman Biro/LSO ==========
     |
     | Konten Manager mengurus ISI halaman Biro & LSO, bukan kerangka
     | organisasinya. Batas itu diuji di sini supaya pemberian izinnya tidak
     | diam-diam melebar kelak — menambah satu kelompok izin di seeder mudah,
     | menyadari bahwa kelompoknya juga membawa `units.delete` tidak.
     ======================================================= */

    /**
     * @return array<string, mixed>
     */
    private function muatanProfilUnit(string $nama): array
    {
        return [
            'jenis' => OrganisationUnit::JENIS_LSO,
            'nama' => $nama,
            'singkatan' => 'LKO',
            'deskripsi' => ['id' => 'Ruang tulis kader.', 'en' => 'A writing space.'],
            'warna' => 'accent-400',
            'urutan' => 4,
            'aktif' => true,
        ];
    }

    public function test_konten_manager_dapat_membuka_dan_menyunting_profil_unit(): void
    {
        $unit = $this->lso('LSO Konten');
        $konten = $this->pengurus('konten_manager');

        // Menunya muncul karena `units.view`, dan halamannya terbuka.
        $this->actingAs($konten)->get('/panel/organisasi/unit')->assertOk();
        $this->actingAs($konten)->get('/panel/organisasi/unit/'.$unit->id)->assertOk();

        $this->actingAs($konten)
            ->put('/panel/organisasi/unit/'.$unit->id, $this->muatanProfilUnit('LSO Konten'))
            ->assertSessionHas('sukses');

        $unit->refresh();
        $this->assertSame('LKO', $unit->singkatan);
        $this->assertSame('Ruang tulis kader.', $unit->getTranslation('deskripsi', 'id'));
        $this->assertSame(4, $unit->urutan);
    }

    public function test_konten_manager_tidak_dapat_membuat_atau_menghapus_unit(): void
    {
        /*
         * Membuat dan menghapus unit mengubah KERANGKA organisasi, bukan
         * isinya. Itu tetap milik Sekretaris.
         */
        $unit = $this->lso('LSO Jangan Diubah');
        $konten = $this->pengurus('konten_manager');

        $this->actingAs($konten)
            ->post('/panel/organisasi/unit', $this->muatanProfilUnit('LSO Baru'))
            ->assertForbidden();

        $this->actingAs($konten)
            ->delete('/panel/organisasi/unit/'.$unit->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('organisation_units', ['nama' => 'LSO Baru']);
        $this->assertDatabaseHas('organisation_units', ['nama' => 'LSO Jangan Diubah']);
    }

    public function test_konten_manager_tidak_dapat_mengubah_keanggotaan_unit(): void
    {
        // Keanggotaan mengubah data ANGGOTA (members.update), bukan isi halaman.
        $unit = $this->lso('LSO Anggota Tetap');
        $orang = $this->anggota(['nama_lengkap' => 'Anggota Tetap']);
        $konten = $this->pengurus('konten_manager');

        $this->actingAs($konten)
            ->post('/panel/organisasi/unit/'.$unit->id.'/anggota', ['member_id' => $orang->id])
            ->assertForbidden();

        $this->assertNull($orang->fresh()->unit_id);
    }

    public function test_konten_manager_tidak_dapat_menata_pengurus_unit(): void
    {
        $unit = $this->lso('LSO Pengurus Tetap');
        $konten = $this->pengurus('konten_manager');

        $this->actingAs($konten)
            ->post('/panel/organisasi/jabatan', ['nama' => 'Kabiro Baru', 'level' => 2, 'unit_id' => $unit->id])
            ->assertForbidden();

        $this->actingAs($konten)
            ->post('/panel/organisasi/penugasan', ['period_id' => 1, 'position_id' => 1, 'nama_manual' => 'Siapa Saja'])
            ->assertForbidden();
    }

    public function test_konten_manager_tetap_dapat_mengelola_agenda_unit(): void
    {
        /*
         * Galeri dan agenda unit sudah dipegang Konten Manager sebelum
         * perubahan izin ini. Diuji ulang supaya pengelompokan izin yang baru
         * tidak diam-diam mencabutnya.
         */
        $unit = $this->lso('LSO Agenda Konten');
        $konten = $this->pengurus('konten_manager');

        $this->actingAs($konten)
            ->post('/panel/organisasi/agenda', [
                'unit_id' => $unit->id,
                'judul' => ['id' => 'Diskusi Bulanan'],
                'mulai' => '2026-10-20 19:00:00',
                'publik' => true,
            ])
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('unit_agendas', 1);
    }

    public function test_sekretaris_tetap_dapat_membuat_dan_menghapus_unit(): void
    {
        // Pemberian izin kepada Konten Manager tidak boleh mengurangi hak
        // Sekretaris sedikit pun.
        $sekretaris = $this->pengurus('sekretaris');

        $this->actingAs($sekretaris)
            ->post('/panel/organisasi/unit', [
                'jenis' => OrganisationUnit::JENIS_LSO,
                'nama' => 'LSO Baru Sekretaris',
                'aktif' => true,
            ])
            ->assertSessionHas('sukses');

        $baru = OrganisationUnit::query()->where('nama', 'LSO Baru Sekretaris')->firstOrFail();

        $this->actingAs($sekretaris)
            ->delete('/panel/organisasi/unit/'.$baru->id)
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('organisation_units', ['nama' => 'LSO Baru Sekretaris']);
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

        // Nama tidak lagi ditampilkan, jadi yang diperiksa adalah data yang
        // benar-benar dikirim ke tampilan — bukan teks di halaman.
        $this->get('/anggota?fakultas=Tarbiyah')
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar): bool => $daftar->total() === 1
                && $daftar->first()['fakultas'] === 'Tarbiyah');
    }

    public function test_kontak_alumni_tidak_pernah_tampil_di_direktori_publik(): void
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

        /*
         * Nomor telepon tidak pernah dikirim ke peramban — TERMASUK milik
         * alumni yang mengizinkannya. Tanpa nama di kartunya, nomor itu sudah
         * tidak berguna bagi siapa pun, sementara bagi pemiliknya ia tetap
         * berarti membuka satu pintu lebih lebar daripada yang diminta.
         */
        $this->assertStringNotContainsString('081111111111', $isi);
        $this->assertStringNotContainsString('082222222222', $isi);

        // Instansi tetap tunduk pada izin pemilik data.
        $this->assertStringContainsString('Kantor Terbuka', $isi);
        $this->assertStringNotContainsString('Kantor Tertutup', $isi);
    }

    public function test_statistik_alumni_ikut_menghormati_izin_pemilik_data(): void
    {
        $terbuka = $this->anggota(['nama_lengkap' => 'Alumni Buka', 'status' => Member::STATUS_ALUMNI]);
        $tertutup = $this->anggota(['nama_lengkap' => 'Alumni Tutup', 'status' => Member::STATUS_ALUMNI]);

        foreach ([[$terbuka, 'Kantor Terbuka', 'Sukoharjo', true], [$tertutup, 'Kantor Tertutup', 'Rahasia', false]] as [$anggota, $instansi, $kota, $boleh]) {
            $profil = new AlumniProfile;
            $profil->member_id = $anggota->id;
            $profil->instansi = $instansi;
            $profil->kota_domisili = $kota;
            $profil->kontak_publik = ['instansi' => $boleh, 'kota_domisili' => $boleh];
            $profil->save();
        }

        /*
         * Statistik agregat TIDAK boleh menjadi pintu belakang bagi izin yang
         * sudah dipegang pemilik data. Sebelum ini kartunya menghormati izin
         * sementara rinciannya tidak: instansi yang sengaja disembunyikan tetap
         * disebut di daftar "Per instansi" — dan dengan angka di bawah lima,
         * penyebutannya praktis menunjuk langsung ke orangnya.
         */
        $this->get('/alumni')
            ->assertOk()
            ->assertViewHas('statistik', fn (array $statistik): bool => $statistik['instansi']->pluck('label')->all() === ['Kantor Terbuka']
                && $statistik['domisili']->pluck('label')->all() === ['Sukoharjo']);
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

        /*
         * Yang diperiksa adalah data yang dikirim ke tampilan, bukan teks di
         * halaman: daftar pilihan saringan selalu memuat SELURUH program studi
         * yang ada, sehingga "Hukum Tata Negara" tetap muncul di halaman
         * meskipun saringannya benar.
         */
        $this->get('/anggota?prodi=Komunikasi')
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar): bool => $daftar->total() === 1
                && $daftar->first()['program_studi'] === 'Komunikasi');
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
        $this->assertSame('Sukoharjo', $data[0]['kota']);

        // Peta tidak lagi membawa identitas apa pun.
        $this->assertSame(
            ['kota', 'lat', 'lng', 'tahun_lulus'],
            collect(array_keys($data[0]))->sort()->values()->all(),
            'Peta tidak boleh membawa kolom selain kota, koordinat, dan tahun lulus.',
        );
        $this->assertStringNotContainsString('Alumni Berkoordinat', html_entity_decode($isi));
    }

    public function test_peta_alumni_tidak_memuat_identitas_meski_diizinkan(): void
    {
        $anggota = $this->anggota(['nama_lengkap' => 'Nama Di Peta', 'status' => Member::STATUS_ALUMNI]);

        $profil = new AlumniProfile;
        $profil->member_id = $anggota->id;
        $profil->kota_domisili = 'Sukoharjo';
        $profil->latitude = -7.0;
        $profil->longitude = 110.0;
        $profil->instansi = 'Kantor Rahasia';
        $profil->kontak_publik = ['instansi' => true];
        $profil->save();

        $halaman = $this->get('/alumni')->assertOk();
        $isi = (string) $halaman->getContent();

        /*
         * Bahkan ketika pemiliknya SENDIRI mengizinkan instansinya tampil,
         * peta tetap tidak membawanya. Peta menjawab pertanyaan "tersebar di
         * mana", bukan "siapa".
         */
        $halaman->assertViewHas('peta', fn ($peta): bool => $peta->count() === 1
            && ! array_key_exists('instansi', $peta->first())
            && ! array_key_exists('nama', $peta->first())
            && ! array_key_exists('slug', $peta->first()));

        $this->assertStringNotContainsString('Nama Di Peta', html_entity_decode($isi));
    }

    public function test_direktori_tidak_menautkan_ke_profil_publik_meski_dibuka(): void
    {
        $terbuka = $this->anggota([
            'nama_lengkap' => 'Kader Terbuka',
            'profil_publik' => true,
        ]);

        /*
         * Profil publik per kader TETAP ada dan tetap dapat dibuka langsung —
         * itu keputusan pemilik data sendiri (lihat PrestasiTest). Yang dihapus
         * adalah TAUTANNYA dari direktori: alamat /prestasi/kader/{slug} memuat
         * slug yang berasal dari nama orang, jadi satu tautan saja sudah
         * membatalkan seluruh penyamaran di halaman ini.
         */
        $this->get('/anggota')
            ->assertOk()
            ->assertDontSee('/prestasi/kader/'.$terbuka->slug, false)
            ->assertDontSee($terbuka->slug, false);

        $this->get('/prestasi/kader/'.$terbuka->slug)->assertOk();
    }

    public function test_direktori_publik_sama_sekali_tidak_memuat_nama(): void
    {
        $anggota = $this->anggota(['nama_lengkap' => 'Nama Yang Harus Hilang']);

        $isi = (string) $this->get('/anggota')->assertOk()->getContent();

        $this->assertStringNotContainsString('Nama Yang Harus Hilang', $isi);
        // Slug berasal dari nama, jadi menampilkannya sama dengan menulis namanya.
        $this->assertStringNotContainsString($anggota->slug, $isi);
    }

    public function test_pencarian_publik_tidak_mencocokkan_nama_dan_nim(): void
    {
        $this->anggota([
            'nama_lengkap' => 'Nama Unik Sekali',
            'nim' => '229999888777',
            'program_studi' => 'Komunikasi',
        ]);

        /*
         * Sebelumnya kotak cari mencocokkan nama dan NIM. Tanpa nama di layar,
         * kotak itu berubah menjadi alat pembuktian: ketik sebuah nama, dan
         * jumlah hasilnya langsung menjawab "orang ini kader sini atau bukan".
         */
        foreach (['Nama Unik Sekali', '229999888777'] as $kata) {
            $this->get('/anggota?'.http_build_query(['cari' => $kata]))
                ->assertOk()
                ->assertViewHas('daftar', fn ($daftar): bool => $daftar->total() === 0);
        }

        // Yang masih boleh dicari: program studi.
        $this->get('/anggota?cari=Komunikasi')
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar): bool => $daftar->total() === 1);
    }

    public function test_statistik_publik_menyamarkan_kelompok_kecil(): void
    {
        // Satu orang pada satu angkatan: angka "1" akan menunjuk tepat pada
        // orang itu, jadi tidak boleh ditampilkan apa adanya.
        $this->anggota(['angkatan' => 2024, 'program_studi' => 'Komunikasi']);

        $this->get('/anggota')
            ->assertOk()
            ->assertViewHas('statistik', fn (array $statistik): bool => $statistik['total']['tampil'] === '<5'
                && $statistik['angkatan']->first()['tampil'] === '<5'
                && $statistik['angkatan']->first()['jumlah'] === 1);
    }

    public function test_statistik_publik_menampilkan_angka_bila_kelompoknya_cukup(): void
    {
        foreach (range(1, \App\Support\StatistikAman::AMBANG) as $abaikan) {
            $this->anggota(['angkatan' => 2023, 'program_studi' => 'Komunikasi']);
        }

        $this->get('/anggota')
            ->assertOk()
            ->assertViewHas('statistik', fn (array $statistik): bool => $statistik['total']['tampil'] === '5'
                && $statistik['angkatan']->first()['tampil'] === '5');
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
