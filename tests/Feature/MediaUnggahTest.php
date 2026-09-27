<?php

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pustaka Media: izin unggah, penyimpanan berkas, dan pembersihan berkas fisik.
 *
 * Catatan: berkas uji memakai PNG 1×1 piksel yang ditulis apa adanya, sehingga
 * tidak bergantung pada ekstensi GD (yang tidak tersedia di sandbox).
 */
class MediaUnggahTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1×1 piksel transparan. */
    private const PNG_KECIL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
    }

    private function pengguna(string $peran): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    private function berkasPng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'poster.png',
            base64_decode(self::PNG_KECIL),
        );
    }

    public function test_konten_manager_dapat_mengunggah_gambar(): void
    {
        $konten = $this->pengguna('konten_manager');

        $this->actingAs($konten)
            ->post('/panel/media', [
                'berkas' => [$this->berkasPng()],
                'koleksi' => 'gambar',
                'alt' => 'Poster kegiatan',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('media', 1);

        $media = MediaLibrary::induk()->getMedia('gambar')->first();
        $this->assertNotNull($media);
        $this->assertSame('gambar', $media->collection_name);

        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_pengguna_tanpa_izin_tidak_dapat_mengunggah(): void
    {
        // Superadmin memegang seluruh izin, jadi diuji memakai pengguna tanpa peran.
        $biasa = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($biasa)
            ->post('/panel/media', [
                'berkas' => [$this->berkasPng()],
                'koleksi' => 'gambar',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_jenis_berkas_terlarang_ditolak(): void
    {
        $konten = $this->pengguna('konten_manager');

        // Berkas teks biasa tidak termasuk daftar jenis yang diizinkan.
        $this->actingAs($konten)
            ->post('/panel/media', [
                'berkas' => [UploadedFile::fake()->createWithContent('catatan.txt', 'halo')],
                'koleksi' => 'dokumen',
            ])
            ->assertSessionHasErrors('berkas.0');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_menghapus_berkas_ikut_menghapus_berkas_fisik(): void
    {
        $konten = $this->pengguna('konten_manager');

        $this->actingAs($konten)->post('/panel/media', [
            'berkas' => [$this->berkasPng()],
            'koleksi' => 'gambar',
        ]);

        $media = MediaLibrary::induk()->getMedia('gambar')->firstOrFail();
        $jalur = $media->getPathRelativeToRoot();

        Storage::disk('public')->assertExists($jalur);

        $this->actingAs($konten)
            ->delete("/panel/media/{$media->id}")
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($jalur);
    }

    public function test_keterangan_berkas_dapat_diubah(): void
    {
        $konten = $this->pengguna('konten_manager');

        $this->actingAs($konten)->post('/panel/media', [
            'berkas' => [$this->berkasPng()],
            'koleksi' => 'gambar',
        ]);

        $media = MediaLibrary::induk()->getMedia('gambar')->firstOrFail();

        $this->actingAs($konten)->patch("/panel/media/{$media->id}", [
            'name' => 'Poster Mapaba 2026',
            'alt' => 'Poster kegiatan Mapaba',
            'collection_name' => 'dokumen',
        ])->assertSessionHas('sukses');

        $media->refresh();
        $this->assertSame('Poster Mapaba 2026', $media->name);
        $this->assertSame('Poster kegiatan Mapaba', $media->getCustomProperty('alt'));
        $this->assertSame('dokumen', $media->collection_name);
    }
}
