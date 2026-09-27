<?php

namespace Tests\Feature;

use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\ContributionPoint;
use App\Models\Member;
use App\Models\User;
use App\Services\Kontribusi;
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
 * Poin kontribusi.
 *
 * Yang diuji adalah janji-janjinya, bukan tampilannya:
 *  - poin tidak pernah berganda walau pemberiannya dijalankan berkali-kali,
 *  - poin ikut DICABUT ketika kehadiran diperbaiki menjadi izin,
 *  - penyesuaian manual wajib beralasan dan dibatasi,
 *  - papan peringkat cocok dengan data presensi yang sebenarnya.
 */
class KontribusiTest extends TestCase
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

    private function kegiatan(int $poin = 5, ?string $mulai = null): AttendanceActivity
    {
        $petugas = $this->sekretaris();

        $kegiatan = app(Presensi::class)->simpan([
            'judul' => 'Kegiatan '.Str::random(4),
            'jenis' => AttendanceActivity::JENIS_RAPAT,
            'mulai' => $mulai ?? now()->toDateTimeString(),
            'mode_presensi' => AttendanceActivity::MODE_KEDUANYA,
            'poin' => $poin,
            'wajib' => false,
        ], $petugas);

        app(Presensi::class)->buka($kegiatan, $petugas);

        return $kegiatan->fresh();
    }

    private function hadirkan(AttendanceActivity $kegiatan, Member $anggota, string $status = AttendanceRecord::STATUS_HADIR): AttendanceRecord
    {
        return app(Presensi::class)->catatManual($kegiatan, $anggota, $status, $this->sekretaris());
    }

    /* ===================== Poin otomatis dari presensi ===================== */

    public function test_kehadiran_memberi_poin_secara_otomatis(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader();

        $this->hadirkan($kegiatan, $kader);

        $this->assertSame(1, ContributionPoint::query()->where('member_id', $kader->id)->count());
        $this->assertSame(5, app(Kontribusi::class)->total($kader));

        $baris = ContributionPoint::query()->where('member_id', $kader->id)->firstOrFail();

        $this->assertSame(ContributionPoint::SUMBER_PRESENSI, $baris->sumber);
        $this->assertSame($kegiatan->id, $baris->activity_id);
        $this->assertStringContainsString($kegiatan->kode, $baris->keterangan);
    }

    public function test_poin_tidak_berganda_walau_dijalankan_berkali_kali(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($kegiatan, $kader);

        // Menekan tombol sinkronisasi tiga kali tidak boleh menambah poin.
        $kontribusi->dariPresensi($kegiatan, $this->sekretaris());
        $kontribusi->dariPresensi($kegiatan, $this->sekretaris());
        $hasil = $kontribusi->dariPresensi($kegiatan, $this->sekretaris());

        $this->assertSame(1, ContributionPoint::query()->where('member_id', $kader->id)->count());
        $this->assertSame(5, $kontribusi->total($kader));
        $this->assertSame(0, $hasil['diberi']);
        $this->assertSame(1, $hasil['sudah_ada']);
    }

    public function test_izin_dan_sakit_tidak_memberi_poin(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $izin = $this->kader('Kader Izin');
        $sakit = $this->kader('Kader Sakit');

        $this->hadirkan($kegiatan, $izin, AttendanceRecord::STATUS_IZIN);
        $this->hadirkan($kegiatan, $sakit, AttendanceRecord::STATUS_SAKIT);

        $this->assertSame(0, ContributionPoint::query()->count());
    }

    public function test_kegiatan_tanpa_poin_tidak_membuat_baris(): void
    {
        $kegiatan = $this->kegiatan(poin: 0);
        $kader = $this->kader();

        $this->hadirkan($kegiatan, $kader);

        $this->assertSame(0, ContributionPoint::query()->count());
    }

    public function test_yang_izin_dilaporkan_tidak_berhak_bukan_sudah_punya_poin(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $hadir = $this->kader('Kader Hadir');
        $izin = $this->kader('Kader Izin');

        $this->hadirkan($kegiatan, $hadir);
        $this->hadirkan($kegiatan, $izin, AttendanceRecord::STATUS_IZIN);

        $hasil = app(Kontribusi::class)->dariPresensi($kegiatan, $this->sekretaris());

        // Kader yang izin MEMANG TIDAK BERHAK dan tidak pernah diberi — bukan
        // sedang "dilewati karena sudah punya poin". Laporan yang menyamakan
        // keduanya akan menyesatkan pengurus.
        $this->assertSame(1, $hasil['sudah_ada']);
        $this->assertSame(1, $hasil['tanpa_poin']);
        $this->assertSame(0, $hasil['diberi']);
    }

    public function test_terlambat_tetap_mendapat_poin(): void
    {
        $kegiatan = $this->kegiatan(poin: 4);
        $kader = $this->kader();

        $this->hadirkan($kegiatan, $kader, AttendanceRecord::STATUS_TERLAMBAT);

        $this->assertSame(4, app(Kontribusi::class)->total($kader));
    }

    public function test_pemindaian_qr_memberi_poin(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader();

        app(Presensi::class)->scanQr($kegiatan->qr_token, $kader);

        $this->assertSame(5, app(Kontribusi::class)->total($kader));
    }

    /* ===================== Poin ikut dicabut saat kehadiran diperbaiki ===================== */

    public function test_memperbaiki_kehadiran_menjadi_izin_mencabut_poin(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($kegiatan, $kader);
        $this->assertSame(5, $kontribusi->total($kader));

        // Panitia memperbaiki salah klik: ternyata kader ini izin.
        $this->hadirkan($kegiatan, $kader, AttendanceRecord::STATUS_IZIN);

        $this->assertSame(0, $kontribusi->total($kader));

        // Barisnya TIDAK dihapus — hanya dibatalkan, beserta alasannya.
        $this->assertSame(1, ContributionPoint::query()->count());
        $this->assertTrue(ContributionPoint::query()->firstOrFail()->dibatalkan());
        $this->assertStringContainsString('izin', strtolower((string) ContributionPoint::query()->firstOrFail()->alasan_pembatalan));
    }

    public function test_mengembalikan_status_hadir_menghidupkan_poinnya_lagi(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($kegiatan, $kader);
        $this->hadirkan($kegiatan, $kader, AttendanceRecord::STATUS_IZIN);
        $this->assertSame(0, $kontribusi->total($kader));

        $this->hadirkan($kegiatan, $kader, AttendanceRecord::STATUS_HADIR);

        $this->assertSame(5, $kontribusi->total($kader));
        // Tetap SATU baris, bukan baris baru.
        $this->assertSame(1, ContributionPoint::query()->count());
    }

    /* ===================== Penyesuaian manual ===================== */

    public function test_penyesuaian_manual_wajib_beralasan(): void
    {
        $kader = $this->kader();

        try {
            app(Kontribusi::class)->sesuaikan($kader, 10, '   ', $this->sekretaris());
            $this->fail('Penyesuaian tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Alasan', collect($e->errors())->flatten()->first());
        }

        $this->assertSame(0, ContributionPoint::query()->count());
    }

    public function test_penyesuaian_manual_dibatasi_agar_tidak_menjadi_alat_mengatur_papan(): void
    {
        $kader = $this->kader();

        $this->expectException(ValidationException::class);
        app(Kontribusi::class)->sesuaikan(
            $kader,
            ContributionPoint::BATAS_PENYESUAIAN + 1,
            'Coba saja',
            $this->sekretaris(),
        );
    }

    public function test_penyesuaian_negatif_mengurangi_total(): void
    {
        $kegiatan = $this->kegiatan(poin: 10);
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($kegiatan, $kader);
        $kontribusi->sesuaikan($kader, -4, 'Terlambat 30 menit tanpa keterangan.', $this->sekretaris());

        $this->assertSame(6, $kontribusi->total($kader));
    }

    public function test_penyesuaian_manual_dua_kali_menghasilkan_dua_baris(): void
    {
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);

        $kontribusi->sesuaikan($kader, 3, 'Menjadi pemateri kajian.', $this->sekretaris());
        $kontribusi->sesuaikan($kader, 2, 'Membantu kepanitiaan Mapaba.', $this->sekretaris());

        // Berbeda dari poin presensi, penyesuaian manual MEMANG boleh berulang:
        // dua jasa yang berbeda adalah dua peristiwa yang berbeda.
        $this->assertSame(2, ContributionPoint::query()->count());
        $this->assertSame(5, $kontribusi->total($kader));
    }

    public function test_pembatalan_poin_membutuhkan_alasan_dan_tidak_menghapus_barisnya(): void
    {
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);
        $pemberi = $this->sekretaris();

        $poin = $kontribusi->sesuaikan($kader, 10, 'Salah orang.', $pemberi);

        try {
            $kontribusi->batalkan($poin, '', $pemberi);
            $this->fail('Pembatalan tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Alasan', collect($e->errors())->flatten()->first());
        }

        $kontribusi->batalkan($poin, 'Ternyata bukan dia yang menjadi pemateri.', $pemberi);

        $this->assertSame(0, $kontribusi->total($kader));
        $this->assertSame(1, ContributionPoint::query()->count());
        $this->assertSame($pemberi->id, ContributionPoint::query()->firstOrFail()->dibatalkan_oleh);
    }

    public function test_poin_yang_sudah_dibatalkan_tidak_dapat_dibatalkan_lagi(): void
    {
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);
        $pemberi = $this->sekretaris();

        $poin = $kontribusi->sesuaikan($kader, 5, 'Koreksi.', $pemberi);
        $kontribusi->batalkan($poin, 'Keliru.', $pemberi);

        $this->expectException(ValidationException::class);
        $kontribusi->batalkan($poin->fresh(), 'Keliru lagi.', $pemberi);
    }

    /* ===================== Papan peringkat ===================== */

    public function test_papan_peringkat_mengurutkan_dan_memutus_seri_dengan_jumlah_peristiwa(): void
    {
        $kegiatanA = $this->kegiatan(poin: 5);
        $kegiatanB = $this->kegiatan(poin: 5);

        $rajin = $this->kader('Kader Rajin');
        $sedikit = $this->kader('Kader Sedikit');

        // Keduanya 10 poin, tetapi Kader Rajin mendapatkannya dari 2 kegiatan.
        $this->hadirkan($kegiatanA, $rajin);
        $this->hadirkan($kegiatanB, $rajin);

        app(Kontribusi::class)->sesuaikan($sedikit, 10, 'Poin kompensasi kepanitiaan.', $this->sekretaris());

        $papan = app(Kontribusi::class)->peringkat();

        $this->assertSame('Kader Rajin', $papan[0]['nama']);
        $this->assertSame(2, $papan[0]['jumlah_peristiwa']);
        $this->assertSame('Kader Sedikit', $papan[1]['nama']);
        $this->assertSame(2, $papan[1]['peringkat']);
    }

    public function test_papan_peringkat_cocok_dengan_data_presensi(): void
    {
        $kegiatan = $this->kegiatan(poin: 3);
        $hadir = $this->kader('Kader Hadir');
        $izin = $this->kader('Kader Izin');

        $this->hadirkan($kegiatan, $hadir);
        $this->hadirkan($kegiatan, $izin, AttendanceRecord::STATUS_IZIN);

        $papan = app(Kontribusi::class)->peringkat();

        // Hanya yang benar-benar hadir yang muncul, dan angka hadirnya cocok
        // dengan tabel presensi.
        $this->assertCount(1, $papan);
        $this->assertSame('Kader Hadir', $papan[0]['nama']);
        $this->assertSame(3, $papan[0]['poin']);
        $this->assertSame(1, $papan[0]['total_hadir']);
        $this->assertSame(
            $papan[0]['total_hadir'],
            AttendanceRecord::query()->hadir()->where('member_id', $hadir->id)->count(),
        );
    }

    public function test_alumni_tidak_masuk_papan_peringkat_tetapi_poinnya_tersimpan(): void
    {
        $kegiatan = $this->kegiatan(poin: 8);
        $alumni = $this->kader('Kader Lulus');
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($kegiatan, $alumni);

        $alumni->forceFill(['status' => Member::STATUS_ALUMNI, 'jalur' => Member::JALUR_ALUMNI])->save();

        $this->assertSame([], $kontribusi->peringkat());
        // Poinnya tidak hilang — kalau statusnya kembali aktif, riwayatnya utuh.
        $this->assertSame(8, $kontribusi->total($alumni->fresh()));
    }

    public function test_posisi_kader_dihitung_dari_poin_yang_lebih_tinggi(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $bawah = $this->kader('Kader Bawah');
        $atas = $this->kader('Kader Atas');
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($kegiatan, $bawah);
        $kontribusi->sesuaikan($atas, 20, 'Menjadi pemateri pelatihan.', $this->sekretaris());

        $this->assertSame(1, $kontribusi->posisi($atas));
        $this->assertSame(2, $kontribusi->posisi($bawah));
    }

    public function test_posisi_kader_selalu_cocok_dengan_papan_peringkat(): void
    {
        $kegiatanA = $this->kegiatan(poin: 5);
        $kegiatanB = $this->kegiatan(poin: 5);
        $kontribusi = app(Kontribusi::class);

        $seriSatu = $this->kader('Kader Seri Satu');
        $seriDua = $this->kader('Kader Seri Dua');
        $atas = $this->kader('Kader Atas');

        // Dua kader dengan poin SAMA — inilah keadaan yang dulu membuat
        // halaman kader menulis "#1" sementara panel menulis "#2".
        $this->hadirkan($kegiatanA, $seriSatu);
        $this->hadirkan($kegiatanA, $seriDua);
        $kontribusi->sesuaikan($atas, 9, 'Memimpin rapat.', $this->sekretaris());

        $papan = collect($kontribusi->peringkat(null, 12));
        $this->assertCount(3, $papan);

        foreach ($papan as $baris) {
            $anggota = Member::query()->findOrFail($baris['anggota_id']);

            $this->assertSame(
                $baris['peringkat'],
                $kontribusi->posisi($anggota),
                'Posisi '.$anggota->nama_lengkap.' berbeda antara halaman kader dan papan peringkat panel.',
            );
        }

        // Dan kedua kader berseri itu memang tidak sama peringkatnya.
        $this->assertSame(2, $kontribusi->posisi($seriSatu));
        $this->assertSame(3, $kontribusi->posisi($seriDua));
    }

    public function test_alumni_tidak_punya_posisi_di_papan_peringkat(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $alumni = $this->kader('Kader Lulus');

        $this->hadirkan($kegiatan, $alumni);

        $alumni->forceFill(['status' => Member::STATUS_ALUMNI, 'jalur' => Member::JALUR_ALUMNI])->save();

        $this->assertNull(app(Kontribusi::class)->posisi($alumni->fresh()));
    }

    public function test_rekap_sumber_memisahkan_asal_poin(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($kegiatan, $kader);
        $kontribusi->sesuaikan($kader, 3, 'Membantu dokumentasi.', $this->sekretaris());

        $rincian = collect($kontribusi->rekapSumber($kader))->keyBy('sumber');

        $this->assertSame(5, $rincian[ContributionPoint::SUMBER_PRESENSI]['poin']);
        $this->assertSame(1, $rincian[ContributionPoint::SUMBER_PRESENSI]['jumlah']);
        $this->assertSame(3, $rincian[ContributionPoint::SUMBER_MANUAL]['poin']);
        $this->assertSame(0, $rincian[ContributionPoint::SUMBER_ARTIKEL]['poin']);
    }

    public function test_periode_memisahkan_poin_antar_bulan(): void
    {
        $september = $this->kegiatan(poin: 5, mulai: '2026-09-10 09:00:00');
        $oktober = $this->kegiatan(poin: 7, mulai: '2026-10-10 09:00:00');
        $kader = $this->kader();
        $kontribusi = app(Kontribusi::class);

        $this->hadirkan($september, $kader);
        $this->hadirkan($oktober, $kader);

        $this->assertSame(12, $kontribusi->total($kader));
        $this->assertSame(5, $kontribusi->total($kader, '2026-09'));
        $this->assertSame(7, $kontribusi->total($kader, '2026-10'));
        $this->assertSame(['2026-10', '2026-09'], $kontribusi->periodeTersedia());
    }

    public function test_artikel_terbit_memberi_poin_kepada_penulisnya(): void
    {
        $kader = $this->kader('Penulis Kader');
        $pengelola = User::factory()->create(['email_verified_at' => now()]);
        $pengelola->assignRole('konten_manager');

        $artikel = app(\App\Services\Redaksi::class)->simpan(new \App\Models\Article, [
            'tipe' => \App\Models\Article::TIPE_BERITA,
            'judul' => ['id' => 'Kabar dari Kader', 'en' => ''],
            'ringkasan' => ['id' => 'Ringkasan kabar kader untuk rayon.', 'en' => ''],
            'konten' => ['id' => '<p>'.str_repeat('Kabar kegiatan rayon. ', 6).'</p>', 'en' => ''],
        ], $kader->user);

        $redaksi = app(\App\Services\Redaksi::class);

        $redaksi->terbitkan($artikel, $pengelola);

        $kontribusi = app(Kontribusi::class);

        $this->assertSame(Kontribusi::POIN_ARTIKEL, $kontribusi->total($kader));

        // Menerbitkan ulang artikel yang sama tidak menggandakan poinnya.
        $redaksi->terbitkan($artikel->fresh(), $pengelola);

        $this->assertSame(1, ContributionPoint::query()
            ->where('member_id', $kader->id)
            ->where('sumber', ContributionPoint::SUMBER_ARTIKEL)
            ->count());
    }

    /* ===================== Lewat HTTP ===================== */

    public function test_halaman_poin_panel_butuh_izin(): void
    {
        $konten = User::factory()->create(['email_verified_at' => now()]);
        $konten->assignRole('konten_manager');

        $this->actingAs($konten)->get('/panel/kontribusi')->assertForbidden();

        $this->actingAs($this->sekretaris())->get('/panel/kontribusi')->assertOk();
    }

    public function test_penyesuaian_lewat_panel_mencatat_poin_dan_menolak_tanpa_alasan(): void
    {
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        $this->actingAs($petugas)
            ->post('/panel/kontribusi/penyesuaian', [
                'member_id' => $kader->id,
                'poin' => 6,
                'alasan' => 'Menjadi moderator diskusi.',
            ])
            ->assertRedirect();

        $this->assertSame(6, app(Kontribusi::class)->total($kader));

        $this->actingAs($petugas)
            ->post('/panel/kontribusi/penyesuaian', [
                'member_id' => $kader->id,
                'poin' => 6,
                'alasan' => '',
            ])
            ->assertSessionHasErrors('alasan');

        $this->assertSame(1, ContributionPoint::query()->count());
    }

    public function test_sinkronisasi_lewat_panel_aman_ditekan_dua_kali(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader();
        $petugas = $this->sekretaris();

        $this->hadirkan($kegiatan, $kader);

        $tautan = "/panel/kontribusi/kegiatan/{$kegiatan->id}/sinkronkan";

        $this->actingAs($petugas)->post($tautan)->assertRedirect();
        $this->actingAs($petugas)->post($tautan)->assertRedirect();

        $this->assertSame(1, ContributionPoint::query()->count());
        $this->assertSame(5, app(Kontribusi::class)->total($kader));
    }

    public function test_kader_hanya_melihat_poinnya_sendiri(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $siti = $this->kader('Siti Aminah');
        $budi = $this->kader('Budi Santoso');

        $this->hadirkan($kegiatan, $siti);
        $this->hadirkan($kegiatan, $budi);

        $this->actingAs($siti->user)
            ->get('/kontribusi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anggota/Kontribusi', false)
                ->where('total', 5)
                ->has('catatan', 1)
                ->where('catatan.0.keterangan', fn (string $teks): bool => ! str_contains($teks, 'Budi')));
    }

    public function test_tamu_tidak_dapat_membuka_halaman_poin_anggota(): void
    {
        $this->get('/kontribusi')->assertRedirect('/login');
    }

    public function test_ekspor_csv_memuat_buku_besar_dan_papan_peringkat(): void
    {
        $kegiatan = $this->kegiatan(poin: 5);
        $kader = $this->kader('Siti Aminah');
        $petugas = $this->sekretaris();

        $this->hadirkan($kegiatan, $kader);

        $respons = $this->actingAs($petugas)->get('/panel/kontribusi/ekspor');

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $isi = $respons->getContent();

        $this->assertStringContainsString('Siti Aminah', $isi);
        $this->assertStringContainsString('Kehadiran Kegiatan', $isi);
        $this->assertStringContainsString('Papan Peringkat', $isi);
        $this->assertStringContainsString('Sah', $isi);
    }

    public function test_baris_yang_dibatalkan_diekspor_dengan_poin_nol(): void
    {
        $kader = $this->kader('Siti Aminah');
        $petugas = $this->sekretaris();
        $kontribusi = app(Kontribusi::class);

        $poin = $kontribusi->sesuaikan($kader, 9, 'Salah orang.', $petugas);
        $kontribusi->batalkan($poin, 'Bukan dia pelakunya.', $petugas);

        $respons = $this->actingAs($petugas)->get('/panel/kontribusi/ekspor');
        $isi = $respons->getContent();

        // Barisnya tetap ada — jumlah kolom Poin di berkas harus selalu sama
        // dengan total nyata, jadi yang dibatalkan ditulis 0.
        $this->assertStringContainsString('Bukan dia pelakunya.', $isi);
        $this->assertStringContainsString(';"0";', $isi);
    }
}
