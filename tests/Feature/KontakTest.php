<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Formulir "Kontak Rayon" terbuka untuk umum, sehingga perlu dijaga dari
 * spam (honeypot) dan penyalahgunaan (pembatasan laju di sisi rute).
 */
class KontakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataSah(array $ganti = []): array
    {
        return array_merge([
            'nama' => 'Sekretaris Komisariat UMS',
            'email' => 'komisariat@example.test',
            'telepon' => '081234567890',
            'asal' => 'PMII Komisariat UMS',
            'jenis' => 'kerjasama',
            'subjek' => 'Ajakan kolaborasi kajian',
            'pesan' => 'Kami mengundang rekan-rekan untuk mengikuti kajian bersama akhir bulan ini.',
            'setuju' => '1',
        ], $ganti);
    }

    public function test_pesan_yang_sah_tersimpan(): void
    {
        $this->post('/kontak', $this->dataSah())
            ->assertRedirect(route('public.kontak'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('contact_messages', 1);

        $pesan = ContactMessage::query()->first();
        $this->assertSame(ContactMessage::STATUS_BARU, $pesan->status);
        $this->assertSame('kerjasama', $pesan->jenis);
    }

    public function test_honeypot_menolak_kiriman_bot(): void
    {
        // Kolom "website" tersembunyi dari manusia; bila terisi, kiriman ditolak.
        $this->post('/kontak', $this->dataSah(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_pesan_terlalu_pendek_ditolak(): void
    {
        $this->post('/kontak', $this->dataSah(['pesan' => 'halo']))
            ->assertSessionHasErrors('pesan');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_persetujuan_wajib_dicentang(): void
    {
        $data = $this->dataSah();
        unset($data['setuju']);

        $this->post('/kontak', $data)->assertSessionHasErrors('setuju');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_jenis_pesan_di_luar_daftar_ditolak(): void
    {
        $this->post('/kontak', $this->dataSah(['jenis' => 'penipuan']))
            ->assertSessionHasErrors('jenis');
    }
}
