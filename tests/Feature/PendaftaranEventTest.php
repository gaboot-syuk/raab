<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventField;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\User;
use App\Notifications\Event\KabarPendaftaran;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 5 — Event Mapaba & PKD.
 *
 * EMPAT JANJI YANG DIJAGA DI SINI:
 *  1. Pendaftaran tertutup OTOMATIS saat kuota penuh atau tanggal terlewat.
 *  2. Pendaftar ganda dengan email sama DITOLAK.
 *  3. Peserta yang lulus dapat dijadikan Kader Aktif TANPA input ulang data.
 *  4. Data pribadi pendaftar tidak bocor lewat penebakan kode pendaftaran.
 */
class PendaftaranEventTest extends TestCase
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

    private function pengurus(string $peran = 'sekretaris'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function event(array $ganti = []): Event
    {
        $judul = $ganti['judul']['id'] ?? 'Mapaba 2026';

        $event = new Event;
        $event->jenis = Event::JENIS_MAPABA;
        $event->kuota = null;
        $event->biaya = 0;
        $event->aktif = true;
        $event->urutan = 0;
        $event->setTranslations('judul', $ganti['judul'] ?? ['id' => $judul]);
        $event->setTranslations('slug', ['id' => Event::slugUnik($judul)]);
        $event->setTranslations('deskripsi', $ganti['deskripsi'] ?? ['id' => '<p>Kaderisasi dasar PMII.</p>']);

        foreach (['jenis', 'kuota', 'biaya', 'lokasi', 'aktif', 'urutan'] as $kolom) {
            if (array_key_exists($kolom, $ganti)) {
                $event->{$kolom} = $ganti[$kolom];
            }
        }

        foreach (['pendaftaran_dibuka', 'pendaftaran_ditutup', 'mulai', 'selesai'] as $kolom) {
            if (array_key_exists($kolom, $ganti)) {
                $event->{$kolom} = $ganti[$kolom];
            }
        }

        if (isset($ganti['syarat'])) {
            $event->setTranslations('syarat', $ganti['syarat']);
        }

        $event->save();

        return $event;
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function isian(array $ganti = []): array
    {
        return array_merge([
            'nama_lengkap' => 'Siti Aminah',
            'email' => 'siti@contoh.test',
            'telepon' => '081200000001',
            'jenis_kelamin' => 'perempuan',
            'program_studi' => 'Hukum Tata Negara',
            'fakultas' => 'Syariah',
            'angkatan' => 2025,
            'instansi' => 'UIN Raden Mas Said',
            'setuju' => '1',
        ], $ganti);
    }

    private function daftarkan(Event $event, array $isian = []): EventRegistration
    {
        return EventRegistration::query()->create([
            ...collect($this->isian($isian))->except('setuju')->all(),
            'event_id' => $event->id,
            'kode_pendaftaran' => app(\App\Services\Pendaftaran::class)->kodePendaftaran($event),
            'status' => EventRegistration::STATUS_MENUNGGU,
        ]);
    }

    /* ===================== Aturan buka/tutup ===================== */

    public function test_pendaftaran_tertutup_saat_kuota_penuh(): void
    {
        $event = $this->event(['kuota' => 1]);
        $this->daftarkan($event);

        $this->assertFalse($event->fresh()->menerimaPendaftaran());
        $this->assertSame(0, $event->fresh()->sisaKuota());
        $this->assertStringContainsString('Kuota sudah penuh', (string) $event->fresh()->alasanTutup());

        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian(['email' => 'kedua@contoh.test']))
            ->assertSessionHasErrors('pendaftaran');

        $this->assertSame(1, $event->pendaftaran()->count());
    }

    public function test_pendaftaran_tertutup_setelah_tanggal_penutupan(): void
    {
        $event = $this->event(['pendaftaran_ditutup' => now()->subDay()]);

        $this->assertFalse($event->menerimaPendaftaran());
        $this->assertStringContainsString('sudah ditutup', (string) $event->alasanTutup());
    }

    public function test_pendaftaran_belum_dibuka_sebelum_tanggal_pembukaan(): void
    {
        $event = $this->event(['pendaftaran_dibuka' => now()->addWeek()]);

        $this->assertFalse($event->menerimaPendaftaran());
        $this->assertStringContainsString('dibuka', (string) $event->alasanTutup());
    }

    public function test_pendaftaran_terbuka_bila_dalam_rentang_dan_kuota_tersedia(): void
    {
        $event = $this->event([
            'kuota' => 10,
            'pendaftaran_dibuka' => now()->subDay(),
            'pendaftaran_ditutup' => now()->addWeek(),
        ]);

        $this->assertTrue($event->menerimaPendaftaran());
        $this->assertNull($event->alasanTutup());
        $this->assertSame(10, $event->sisaKuota());
    }

    public function test_pendaftar_yang_dibatalkan_mengembalikan_kursinya(): void
    {
        $event = $this->event(['kuota' => 1]);
        $peserta = $this->daftarkan($event);

        $this->assertSame(0, $event->fresh()->sisaKuota());

        app(\App\Services\Pendaftaran::class)->batalkan($peserta);

        $this->assertSame(1, $event->fresh()->sisaKuota());
        $this->assertTrue($event->fresh()->menerimaPendaftaran());
    }

    /* ===================== Pendaftaran ganda ===================== */

    public function test_pendaftar_ganda_dengan_email_sama_ditolak(): void
    {
        $event = $this->event();

        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian())->assertRedirect();

        $this->assertSame(1, $event->pendaftaran()->count());

        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian(['nama_lengkap' => 'Nama Berbeda']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $event->pendaftaran()->count());
    }

    public function test_pendaftar_ganda_dengan_ejaan_nama_berbeda_ditolak(): void
    {
        $event = $this->event();

        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian());

        // Nama ditulis berbeda dan email dibedakan hanya pada huruf besar/kecil,
        // tetapi sidik jari datanya sama → tetap dianggap pendaftaran ganda.
        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian([
            'nama_lengkap' => '  SITI   AMINAH ',
            'email' => 'SITI@contoh.test',
        ]))->assertSessionHasErrors('email');

        $this->assertSame(1, $event->pendaftaran()->count());
    }

    /* ===================== Alur pendaftaran ===================== */

    public function test_pendaftaran_menghasilkan_kode_dan_mengirim_kabar(): void
    {
        Notification::fake();

        $event = $this->event();

        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian())->assertRedirect();

        $peserta = $event->pendaftaran()->firstOrFail();

        $this->assertStringStartsWith('MAPA', $peserta->kode_pendaftaran);
        $this->assertSame(EventRegistration::STATUS_MENUNGGU, $peserta->status);
        $this->assertNotNull($peserta->sidik_data);

        Notification::assertSentOnDemand(
            KabarPendaftaran::class,
            fn (KabarPendaftaran $notifikasi, array $saluran, object $notifiable): bool => $notifiable->routes['mail'] === 'siti@contoh.test'
                && $notifikasi->keadaan === KabarPendaftaran::DITERIMA,
        );

        // Halaman sukses menampilkan kodenya.
        $this->get('/pendaftaran/sukses/'.$peserta->kode_pendaftaran)
            ->assertOk()
            ->assertSee($peserta->kode_pendaftaran, false);
    }

    public function test_pendaftaran_menghormati_honeypot(): void
    {
        $event = $this->event();

        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian(['tautan_web' => 'https://spam.example']))
            ->assertSessionHas('galat');

        $this->assertSame(0, $event->pendaftaran()->count());
    }

    public function test_kode_pendaftaran_tidak_mengulang_tahun(): void
    {
        $event = $this->event(['mulai' => '2026-10-17']);

        $kode = app(\App\Services\Pendaftaran::class)->kodePendaftaran($event);

        $this->assertSame('MAPABA-2026-0001', $kode);
        $this->assertSame(
            1,
            substr_count($kode, '2026'),
            'Tahun tidak boleh muncul dua kali — slug sudah memuat tahun, jadi jangan dipakai sebagai awalan.',
        );
    }

    public function test_kode_pendaftaran_berurutan_dan_unik(): void
    {
        $event = $this->event(['mulai' => '2026-10-17']);

        $pertama = $this->daftarkan($event);
        $kedua = $this->daftarkan($event, ['email' => 'kedua@contoh.test', 'telepon' => '081200000002']);

        $this->assertSame('MAPABA-2026-0001', $pertama->kode_pendaftaran);
        $this->assertSame('MAPABA-2026-0002', $kedua->kode_pendaftaran);
    }

    public function test_form_pendaftaran_memerlukan_persetujuan(): void
    {
        $event = $this->event();

        $this->post('/pendaftaran/'.$event->id.'/kirim', collect($this->isian())->except('setuju')->all())
            ->assertSessionHasErrors('setuju');

        $this->assertSame(0, $event->pendaftaran()->count());
    }

    /* ===================== Kolom tambahan ===================== */

    public function test_kolom_tambahan_wajib_divalidasi(): void
    {
        $event = $this->event();

        $kolom = new EventField;
        $kolom->event_id = $event->id;
        $kolom->kunci = 'ukuran_kaos';
        $kolom->tipe = EventField::TIPE_PILIHAN;
        $kolom->pilihan = ['S', 'M', 'L'];
        $kolom->wajib = true;
        $kolom->urutan = 0;
        $kolom->aktif = true;
        $kolom->setTranslations('label', ['id' => 'Ukuran Kaos']);
        $kolom->save();

        // Kosong → ditolak.
        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian())
            ->assertSessionHasErrors('jawaban.'.$kolom->id);

        // Nilai di luar daftar pilihan → ditolak.
        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian([
            'jawaban' => [$kolom->id => 'XXXL'],
        ]))->assertSessionHasErrors('jawaban.'.$kolom->id);

        // Nilai sah → tersimpan.
        $this->post('/pendaftaran/'.$event->id.'/kirim', $this->isian([
            'jawaban' => [$kolom->id => 'L'],
        ]))->assertRedirect();

        $this->assertDatabaseHas('event_registration_answers', [
            'event_field_id' => $kolom->id,
            'nilai' => 'L',
        ]);
    }

    public function test_panitia_tidak_boleh_memakai_nama_kolom_bawaan(): void
    {
        $event = $this->event();
        $sekretaris = $this->pengurus();

        $this->actingAs($sekretaris)
            ->post('/panel/event/'.$event->id.'/kolom', [
                'kunci' => 'email',
                'label' => ['id' => 'Email Palsu'],
                'tipe' => EventField::TIPE_TEKS,
            ])
            ->assertSessionHasErrors('kunci');
    }

    /* ===================== Pengelolaan peserta ===================== */

    public function test_verifikasi_mengubah_status_dan_mengirim_kabar(): void
    {
        Notification::fake();

        $event = $this->event();
        $peserta = $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        $this->actingAs($sekretaris)
            ->post('/panel/peserta/'.$peserta->id.'/verifikasi')
            ->assertSessionHas('sukses');

        $this->assertSame(EventRegistration::STATUS_TERVERIFIKASI, $peserta->fresh()->status);
        $this->assertSame($sekretaris->id, $peserta->fresh()->diverifikasi_oleh);

        Notification::assertSentOnDemand(KabarPendaftaran::class);
    }

    public function test_penolakan_wajib_memuat_alasan(): void
    {
        $event = $this->event();
        $peserta = $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        $this->actingAs($sekretaris)
            ->post('/panel/peserta/'.$peserta->id.'/tolak', ['catatan_panitia' => ''])
            ->assertSessionHasErrors('catatan_panitia');

        $this->assertSame(EventRegistration::STATUS_MENUNGGU, $peserta->fresh()->status);

        $this->actingAs($sekretaris)
            ->post('/panel/peserta/'.$peserta->id.'/tolak', ['catatan_panitia' => 'Data tidak lengkap.'])
            ->assertSessionHas('sukses');

        $this->assertSame(EventRegistration::STATUS_DITOLAK, $peserta->fresh()->status);
        $this->assertSame('Data tidak lengkap.', $peserta->fresh()->catatan_panitia);
    }

    public function test_menandai_hadir_sekaligus_menganggap_terverifikasi(): void
    {
        $event = $this->event();
        $peserta = $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        $this->actingAs($sekretaris)
            ->post('/panel/peserta/'.$peserta->id.'/hadir', ['hadir' => true])
            ->assertSessionHas('sukses');

        $peserta->refresh();

        $this->assertTrue($peserta->hadir);
        $this->assertSame(EventRegistration::STATUS_HADIR, $peserta->status);

        // Membatalkan tanda hadir mengembalikan status ke terverifikasi,
        // bukan menghapus jejak verifikasinya.
        $this->actingAs($sekretaris)
            ->post('/panel/peserta/'.$peserta->id.'/hadir', ['hadir' => false])
            ->assertSessionHas('sukses');

        $this->assertFalse($peserta->fresh()->hadir);
        $this->assertSame(EventRegistration::STATUS_TERVERIFIKASI, $peserta->fresh()->status);
    }

    public function test_ekspor_csv_memuat_kolom_dan_peserta(): void
    {
        $event = $this->event();
        $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        $respons = $this->actingAs($sekretaris)->get('/panel/event/'.$event->id.'/ekspor');

        $respons->assertOk();
        $this->assertStringContainsString('text/csv', (string) $respons->headers->get('content-type'));

        $isi = $respons->streamedContent();

        $this->assertStringContainsString('Kode Pendaftaran', $isi);
        $this->assertStringContainsString('Siti Aminah', $isi);
        $this->assertStringContainsString('sep=;', $isi);
    }

    /* ===================== Promosi ke anggota ===================== */

    public function test_promosi_menjadikan_kader_aktif_tanpa_input_ulang(): void
    {
        Notification::fake();

        $event = $this->event();
        $peserta = $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        app(\App\Services\Pendaftaran::class)->verifikasi($peserta, $sekretaris);

        $this->actingAs($sekretaris)
            ->post('/panel/peserta/'.$peserta->id.'/promosikan')
            ->assertSessionHas('sukses');

        $peserta->refresh();

        $this->assertNotNull($peserta->member_id);

        $anggota = Member::query()->findOrFail($peserta->member_id);

        // Data peserta terbawa apa adanya — tidak ada yang perlu diketik ulang.
        $this->assertSame('Siti Aminah', $anggota->nama_lengkap);
        $this->assertSame('Hukum Tata Negara', $anggota->program_studi);
        $this->assertSame(2025, $anggota->angkatan);
        $this->assertSame(Member::STATUS_AKTIF, $anggota->status);
        $this->assertNotNull($anggota->nomor_anggota);

        // Akun dibuat untuk peserta.
        $this->assertDatabaseHas('users', ['email' => 'siti@contoh.test']);
    }

    public function test_promosi_ditolak_untuk_peserta_yang_belum_lolos(): void
    {
        $event = $this->event();
        $peserta = $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        $this->actingAs($sekretaris)
            ->post('/panel/peserta/'.$peserta->id.'/promosikan')
            ->assertSessionHas('galat');

        $this->assertNull($peserta->fresh()->member_id);
    }

    public function test_promosi_tidak_dapat_diulang(): void
    {
        Notification::fake();

        $event = $this->event();
        $peserta = $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        app(\App\Services\Pendaftaran::class)->verifikasi($peserta, $sekretaris);

        $this->actingAs($sekretaris)->post('/panel/peserta/'.$peserta->id.'/promosikan')->assertSessionHas('sukses');
        $this->actingAs($sekretaris)->post('/panel/peserta/'.$peserta->id.'/promosikan')->assertSessionHas('galat');

        $this->assertSame(1, Member::query()->count());
    }

    /* ===================== Halaman publik & privasi ===================== */

    public function test_alamat_pendek_mapaba_dan_pkd_mengarah_ke_event_yang_dibuka(): void
    {
        $this->event();

        $this->get('/pendaftaran/mapaba')->assertOk()->assertSee('Mapaba 2026', false);
        $this->get('/pendaftaran')->assertOk();

        // Jenis yang belum punya event menampilkan keterangan, bukan galat.
        $this->get('/pendaftaran/pkd')->assertOk()->assertSee('Belum Ada Kegiatan', false);
    }

    public function test_alamat_jenis_yang_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get('/pendaftaran/ngawur')->assertNotFound();
    }

    public function test_arsip_event_lampau_tetap_dapat_dibuka_publik(): void
    {
        $event = $this->event([
            'judul' => ['id' => 'Mapaba 2024'],
            'mulai' => now()->subYear()->toDateString(),
            'pendaftaran_ditutup' => now()->subYear()->toDateString(),
        ]);

        $this->get('/pendaftaran/'.$event->getTranslation('slug', 'id'))
            ->assertOk()
            ->assertSee('Mapaba 2024', false);

        $this->get('/pendaftaran')->assertOk()->assertSee('Mapaba 2024', false);
    }

    public function test_pencarian_status_memerlukan_kode_dan_email_sekaligus(): void
    {
        $event = $this->event();
        $peserta = $this->daftarkan($event);

        // Hanya kode → wajib email, tidak ada data yang terungkap.
        $this->get('/pendaftaran/status?kode='.$peserta->kode_pendaftaran)
            ->assertSessionHasErrors('email');

        // Kode benar tetapi email orang lain → tidak ditemukan.
        $this->get('/pendaftaran/status?kode='.$peserta->kode_pendaftaran.'&email=oranglain@contoh.test')
            ->assertOk()
            ->assertSee('tidak ditemukan', false);

        // Kode + email yang benar → tampil.
        $this->get('/pendaftaran/status?kode='.$peserta->kode_pendaftaran.'&email=siti@contoh.test')
            ->assertOk()
            ->assertSee('Siti Aminah', false);
    }

    public function test_kartu_peserta_hanya_setelah_terverifikasi(): void
    {
        $event = $this->event();
        $peserta = $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        // Belum terverifikasi → dialihkan kembali.
        $this->get('/pendaftaran/kartu?kode='.$peserta->kode_pendaftaran.'&email=siti@contoh.test')
            ->assertRedirect(route('public.pendaftaran.status'));

        app(\App\Services\Pendaftaran::class)->verifikasi($peserta, $sekretaris);

        $this->get('/pendaftaran/kartu?kode='.$peserta->kode_pendaftaran.'&email=siti@contoh.test')
            ->assertOk()
            ->assertSee($peserta->kode_pendaftaran, false)
            ->assertSee('Siti Aminah', false);
    }

    public function test_halaman_panel_event_dan_peserta_terbuka(): void
    {
        $event = $this->event();
        $sekretaris = $this->pengurus();

        $this->actingAs($sekretaris)->get('/panel/event')->assertOk();
        $this->actingAs($sekretaris)->get('/panel/peserta')->assertOk();
        $this->actingAs($sekretaris)->get('/panel/peserta?event='.$event->id)->assertOk();
    }

    public function test_data_peserta_tertutup_bagi_kader_biasa(): void
    {
        $kader = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($kader)->get('/panel/peserta')->assertForbidden();
        $this->actingAs($kader)->get('/panel/event')->assertForbidden();
    }

    public function test_event_yang_sudah_punya_pendaftar_tidak_dapat_dihapus(): void
    {
        $event = $this->event();
        $this->daftarkan($event);
        $sekretaris = $this->pengurus();

        $this->actingAs($sekretaris)
            ->delete('/panel/event/'.$event->id)
            ->assertSessionHas('galat');

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }
}
