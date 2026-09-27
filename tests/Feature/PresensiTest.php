<?php

namespace Tests\Feature;

use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRsvp;
use App\Models\Member;
use App\Models\User;
use App\Services\Presensi;
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
 * Kegiatan & presensi kader.
 *
 * Yang diuji bukan tampilannya, melainkan janji-janjinya:
 *  - kehadiran hanya bisa dicatat saat presensi DIBUKA,
 *  - satu orang hanya bisa presensi SEKALI per kegiatan,
 *  - memutar token QR mematikan tautan lama,
 *  - kesediaan hadir TIDAK pernah dihitung sebagai kehadiran.
 */
class PresensiTest extends TestCase
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
        $member->save();

        return $member;
    }

    private function kegiatan(array $ganti = []): AttendanceActivity
    {
        return app(Presensi::class)->simpan([
            'judul' => $ganti['judul'] ?? 'Rapat Anggota Bulanan',
            'deskripsi' => null,
            'jenis' => $ganti['jenis'] ?? AttendanceActivity::JENIS_RAPAT,
            'mulai' => $ganti['mulai'] ?? now()->toDateTimeString(),
            'mode_presensi' => $ganti['mode_presensi'] ?? AttendanceActivity::MODE_KEDUANYA,
            'poin' => $ganti['poin'] ?? 5,
            'wajib' => $ganti['wajib'] ?? false,
        ], $this->sekretaris());
    }

    /* ===================== Presensi harus dibuka ===================== */

    public function test_kehadiran_tidak_dapat_dicatat_sebelum_presensi_dibuka(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        $this->assertSame(AttendanceActivity::STATUS_DRAF, $kegiatan->status);

        $this->expectException(ValidationException::class);

        app(Presensi::class)->catatManual($kegiatan, $kader, AttendanceRecord::STATUS_HADIR, $petugas);
    }

    public function test_membuka_presensi_menerbitkan_token_qr(): void
    {
        $kegiatan = $this->kegiatan();

        $this->assertNull($kegiatan->qr_token);

        $dibuka = app(Presensi::class)->buka($kegiatan, $this->sekretaris());

        $this->assertSame(AttendanceActivity::STATUS_TERBUKA, $dibuka->status);
        $this->assertNotNull($dibuka->qr_token);
        $this->assertTrue($dibuka->qrAktif());
        $this->assertTrue($dibuka->qr_berlaku_sampai->isFuture());
    }

    public function test_menutup_presensi_mematikan_token_dan_menolak_catatan_baru(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);
        $token = $kegiatan->qr_token;

        $presensi->tutup($kegiatan, $petugas);

        $this->assertNull($kegiatan->fresh()->qr_token);
        $this->assertFalse($kegiatan->fresh()->qrAktif());

        // Token yang tadinya sah kini tidak dikenali lagi.
        try {
            $presensi->scanQr($token, $kader);
            $this->fail('Pemindaian seharusnya ditolak setelah presensi ditutup.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak dikenali', collect($e->errors())->flatten()->first());
        }
    }

    public function test_kegiatan_yang_dibatalkan_tidak_dapat_dibuka_atau_dicatat(): void
    {
        $kegiatan = $this->kegiatan();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->batalkan($kegiatan, 'Pemateri berhalangan hadir.', $petugas);

        $this->assertSame(AttendanceActivity::STATUS_BATAL, $kegiatan->fresh()->status);
        $this->assertStringContainsString('Dibatalkan', $kegiatan->fresh()->getTranslation('deskripsi', 'id'));

        $this->expectException(ValidationException::class);
        $presensi->buka($kegiatan->fresh(), $petugas);
    }

    /* ===================== Satu orang, satu catatan ===================== */

    public function test_pemindaian_qr_mencatat_kehadiran(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $this->sekretaris());

        $catatan = $presensi->scanQr($kegiatan->fresh()->qr_token, $kader);

        $this->assertSame(AttendanceRecord::STATUS_HADIR, $catatan->status);
        $this->assertSame(AttendanceRecord::METODE_QR, $catatan->metode);
        $this->assertTrue($catatan->dihitungHadir());
    }

    public function test_pemindaian_qr_kedua_oleh_orang_yang_sama_ditolak(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $this->sekretaris());
        $token = $kegiatan->fresh()->qr_token;

        $presensi->scanQr($token, $kader);

        try {
            $presensi->scanQr($token, $kader);
            $this->fail('Pemindaian kedua seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah tercatat', collect($e->errors())->flatten()->first());
        }

        // Yang penting: tetap hanya SATU catatan di basis data.
        $this->assertSame(1, AttendanceRecord::query()
            ->where('activity_id', $kegiatan->id)
            ->where('member_id', $kader->id)
            ->count());
    }

    public function test_kader_lain_tetap_dapat_presensi_pada_kegiatan_yang_sama(): void
    {
        $kegiatan = $this->kegiatan();
        $siti = $this->kader('Siti Aminah');
        $budi = $this->kader('Budi Santoso');
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $this->sekretaris());
        $token = $kegiatan->fresh()->qr_token;

        $presensi->scanQr($token, $siti);
        $presensi->scanQr($token, $budi);

        $this->assertSame(2, AttendanceRecord::query()->where('activity_id', $kegiatan->id)->count());
    }

    public function test_memutar_qr_mematikan_tautan_lama(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);
        $tokenLama = $kegiatan->fresh()->qr_token;

        $presensi->putarQr($kegiatan->fresh(), $petugas);
        $tokenBaru = $kegiatan->fresh()->qr_token;

        $this->assertNotSame($tokenLama, $tokenBaru);

        // Tangkapan layar QR lama tidak lagi berguna.
        try {
            $presensi->scanQr($tokenLama, $kader);
            $this->fail('Token lama seharusnya tidak dikenali lagi.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak dikenali', collect($e->errors())->flatten()->first());
        }

        // Yang baru tetap berfungsi.
        $this->assertSame(
            AttendanceRecord::STATUS_HADIR,
            $presensi->scanQr($tokenBaru, $kader)->status,
        );
    }

    /* ===================== Pencatatan manual ===================== */

    public function test_catat_manual_dua_kali_memperbarui_bukan_menambah(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);

        $presensi->catatManual($kegiatan->fresh(), $kader, AttendanceRecord::STATUS_ALPA, $petugas);
        $presensi->catatManual($kegiatan->fresh(), $kader, AttendanceRecord::STATUS_HADIR, $petugas);

        $this->assertSame(1, AttendanceRecord::query()
            ->where('activity_id', $kegiatan->id)
            ->where('member_id', $kader->id)
            ->count());

        $this->assertSame(
            AttendanceRecord::STATUS_HADIR,
            AttendanceRecord::query()->where('member_id', $kader->id)->firstOrFail()->status,
        );
    }

    public function test_tanda_serentak_menolak_status_selain_hadir_dan_terlambat(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);

        try {
            $presensi->catatMassal($kegiatan->fresh(), [$kader->id], AttendanceRecord::STATUS_ALPA, $petugas);
            $this->fail('Penandaan massal alpa seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('dicatat satu per satu', collect($e->errors())->flatten()->first());
        }

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_menandai_sisa_membutuhkan_alasan_dan_tidak_menimpa_yang_sudah_ada(): void
    {
        $kegiatan = $this->kegiatan();
        $hadir = $this->kader('Siti Aminah');
        $pergi = $this->kader('Budi Santoso');
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);
        $presensi->catatManual($kegiatan->fresh(), $hadir, AttendanceRecord::STATUS_HADIR, $petugas);

        try {
            $presensi->tandaiAlpaSisa($kegiatan->fresh(), [$hadir->id, $pergi->id], '', $petugas);
            $this->fail('Penandaan tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Alasan', collect($e->errors())->flatten()->first());
        }

        $jumlah = $presensi->tandaiAlpaSisa($kegiatan->fresh(), [$hadir->id, $pergi->id], 'Daftar pintu sudah lengkap.', $petugas);

        $this->assertSame(1, $jumlah);
        // Yang sudah hadir TIDAK ikut ditimpa menjadi alpa.
        $this->assertSame(
            AttendanceRecord::STATUS_HADIR,
            AttendanceRecord::query()->where('member_id', $hadir->id)->firstOrFail()->status,
        );
    }

    public function test_kegiatan_mode_qr_menolak_pencatatan_manual(): void
    {
        $kegiatan = $this->kegiatan(['mode_presensi' => AttendanceActivity::MODE_QR]);
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);

        $this->expectException(ValidationException::class);
        $presensi->catatManual($kegiatan->fresh(), $kader, AttendanceRecord::STATUS_HADIR, $petugas);
    }

    /* ===================== RSVP ≠ kehadiran ===================== */

    public function test_kesediaan_hadir_tidak_pernah_dihitung_sebagai_kehadiran(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);
        $presensi->rsvp($kegiatan->fresh(), $kader, AttendanceRsvp::STATUS_HADIR, 'Insya Allah datang.');

        $rekap = $presensi->rekapKader($kader);

        $this->assertSame(0, $rekap['total_hadir']);
        $this->assertSame(0, $rekap['poin']);
        $this->assertSame(0.0, $rekap['persen']);

        // Dan rekap kegiatan tetap mencatat kesediaannya secara terpisah.
        $this->assertSame(1, $presensi->rekap($kegiatan->fresh())['rsvp_hadir']);
    }

    public function test_kesediaan_terakhir_yang_menang(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);

        $presensi->rsvp($kegiatan->fresh(), $kader, AttendanceRsvp::STATUS_HADIR);
        $presensi->rsvp($kegiatan->fresh(), $kader, AttendanceRsvp::STATUS_TIDAK_HADIR, 'Ada acara keluarga.');

        $this->assertSame(1, AttendanceRsvp::query()->where('member_id', $kader->id)->count());
        $this->assertSame(
            AttendanceRsvp::STATUS_TIDAK_HADIR,
            AttendanceRsvp::query()->where('member_id', $kader->id)->firstOrFail()->status,
        );
    }

    public function test_kesediaan_ditolak_setelah_kegiatan_selesai(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);
        $presensi->tutup($kegiatan->fresh(), $petugas);

        $this->expectException(ValidationException::class);
        $presensi->rsvp($kegiatan->fresh(), $kader, AttendanceRsvp::STATUS_HADIR);
    }

    /* ===================== Rekap & poin ===================== */

    public function test_rekap_kader_mengabaikan_kegiatan_yang_masih_draf(): void
    {
        $draf = $this->kegiatan(['judul' => 'Masih Draf']);
        $kader = $this->kader();

        // Kegiatan draf belum pernah diumumkan, jadi ketidakhadiran di sana
        // bukan kesalahan kader.
        $rekap = app(Presensi::class)->rekapKader($kader);

        $this->assertSame(0, $rekap['total_kegiatan']);
        $this->assertNotNull($draf->id);
    }

    public function test_poin_dihitung_hanya_dari_kegiatan_yang_dihadiri(): void
    {
        $hadir = $this->kegiatan(['judul' => 'Rapat', 'poin' => 5]);
        $tidakHadir = $this->kegiatan(['judul' => 'Kajian', 'poin' => 3]);
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($hadir, $petugas);
        $presensi->buka($tidakHadir, $petugas);

        $presensi->catatManual($hadir->fresh(), $kader, AttendanceRecord::STATUS_HADIR, $petugas);
        $presensi->catatManual($tidakHadir->fresh(), $kader, AttendanceRecord::STATUS_IZIN, $petugas);

        $rekap = $presensi->rekapKader($kader);

        $this->assertSame(5, $rekap['poin']);
        $this->assertSame(1, $rekap['total_hadir']);
        $this->assertSame(2, $rekap['total_kegiatan']);
        $this->assertSame(50.0, $rekap['persen']);
        $this->assertSame(1, $rekap['izin']);
    }

    public function test_terlambat_tetap_dihitung_hadir(): void
    {
        $kegiatan = $this->kegiatan(['poin' => 4]);
        $kader = $this->kader();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);
        $presensi->catatManual($kegiatan->fresh(), $kader, AttendanceRecord::STATUS_TERLAMBAT, $petugas);

        $rekap = $presensi->rekapKader($kader);

        $this->assertSame(1, $rekap['total_hadir']);
        $this->assertSame(4, $rekap['poin']);
    }

    public function test_rekap_kegiatan_menghitung_yang_belum_punya_catatan(): void
    {
        $this->kader('Siti Aminah');
        $this->kader('Budi Santoso');
        $kegiatan = $this->kegiatan();
        $petugas = $this->sekretaris();
        $presensi = app(Presensi::class);

        $presensi->buka($kegiatan, $petugas);

        $rekap = $presensi->rekap($kegiatan->fresh());

        $this->assertSame(2, $rekap['total_anggota']);
        $this->assertSame(2, $rekap['belum']);
        $this->assertSame(0, $rekap['total_hadir']);
    }

    /* ===================== Lewat HTTP ===================== */

    public function test_halaman_presensi_panel_butuh_izin(): void
    {
        $kegiatan = $this->kegiatan();

        $konten = User::factory()->create(['email_verified_at' => now()]);
        $konten->assignRole('konten_manager');

        $this->actingAs($konten)->get('/panel/presensi')->assertForbidden();
        $this->actingAs($konten)->get('/panel/kegiatan')->assertForbidden();

        $this->actingAs($this->sekretaris())
            ->get('/panel/presensi?kegiatan='.$kegiatan->id)
            ->assertOk();
    }

    public function test_tamu_di_arahkan_masuk_saat_memindai_qr(): void
    {
        $this->get('/presensi/scan/token-apa-saja')->assertRedirect('/login');
    }

    public function test_pemindaian_lewat_http_mencatat_kehadiran_dan_menolak_ulangan(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        app(Presensi::class)->buka($kegiatan, $petugas);
        $token = $kegiatan->fresh()->qr_token;

        $this->actingAs($kader->user)
            ->get('/presensi/scan/'.$token)
            ->assertOk()
            ->assertSee('Tercatat', false);

        $this->actingAs($kader->user)
            ->get('/presensi/scan/'.$token)
            ->assertOk()
            ->assertSee('Tidak Tercatat', false)
            ->assertSee('sudah tercatat', false);
    }

    public function test_kader_dapat_menyatakan_kesediaan_dan_melihat_riwayatnya(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        app(Presensi::class)->buka($kegiatan, $petugas);

        $this->actingAs($kader->user)
            ->post("/kegiatan/{$kegiatan->id}/rsvp", ['status' => AttendanceRsvp::STATUS_HADIR])
            ->assertRedirect();

        $this->assertSame(1, AttendanceRsvp::query()->where('member_id', $kader->id)->count());

        $this->actingAs($kader->user)
            ->get('/kegiatan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anggota/Kegiatan', false)
                ->has('akanDatang', 1)
                ->where('akanDatang.0.label_rsvp', 'Akan Hadir')
                ->where('rekap.total_kegiatan', 1));
    }

    public function test_presensi_kegiatan_muncul_di_panel_beserta_rekapnya(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        app(Presensi::class)->buka($kegiatan, $petugas);
        app(Presensi::class)->catatManual($kegiatan->fresh(), $kader, AttendanceRecord::STATUS_HADIR, $petugas);

        $this->actingAs($petugas)
            ->get('/panel/presensi?kegiatan='.$kegiatan->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Presensi/Index', false)
                ->where('rekap.hadir', 1)
                ->where('rekap.total_hadir', 1)
                ->where('kegiatan.boleh_dicatat', true));
    }

    public function test_ekspor_presensi_berupa_csv_dengan_baris_yang_belum_tercatat(): void
    {
        $kegiatan = $this->kegiatan();
        $kader = $this->kader('Siti Aminah');
        $this->kader('Budi Santoso');
        $petugas = $this->sekretaris();

        app(Presensi::class)->buka($kegiatan, $petugas);
        app(Presensi::class)->catatManual($kegiatan->fresh(), $kader, AttendanceRecord::STATUS_HADIR, $petugas);

        $respons = $this->actingAs($petugas)->get("/panel/presensi/{$kegiatan->id}/ekspor");

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $isi = $respons->getContent();

        $this->assertStringContainsString('Siti Aminah', $isi);
        $this->assertStringContainsString('Budi Santoso', $isi);
        $this->assertStringContainsString('Belum Ada Catatan', $isi);
        $this->assertStringContainsString('Hadir;1', $isi);
    }
}
