<?php

namespace Tests\Feature;

use App\Models\MediaLibrary;
use App\Models\User;
use App\Support\PemantauPenyimpanan;
use App\Support\PemrosesGambar;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\DriverInterface;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

    public function test_unggahan_panel_mengikuti_disk_yang_dikonfigurasi(): void
    {
        /*
         * Sebelumnya controller menuliskan disknya secara tetap ('public'),
         * sehingga `MEDIA_DISK=s3` di produksi tidak berpengaruh apa pun pada
         * unggahan panel: berkasnya tetap masuk ke sistem berkas wadah yang
         * sementara, lalu hilang setiap wadah dinyalakan ulang — sementara
         * pengurus mengira berkasnya sudah aman di penyimpanan permanen.
         */
        Storage::fake('uji-media');
        config([
            'filesystems.disks.uji-media' => ['driver' => 'local', 'root' => storage_path('app/uji-media')],
            'media-library.disk_name' => 'uji-media',
        ]);

        $this->actingAs($this->pengguna('konten_manager'))
            ->post('/panel/media', ['berkas' => [$this->berkasPng()], 'koleksi' => 'gambar'])
            ->assertRedirect();

        $media = MediaLibrary::induk()->getMedia('gambar')->firstOrFail();

        $this->assertSame('uji-media', $media->disk);
        Storage::disk('uji-media')->assertExists($media->getPathRelativeToRoot());
    }

    /**
     * Mesin gambar palsu, supaya keputusan penggantian berkas dapat diuji
     * tanpa bergantung pada ekstensi GD.
     *
     * Wadah pengembangan tidak memiliki GD, sedangkan produksi memilikinya.
     * Kalau seluruh logika disatukan, keputusan itu tidak akan pernah teruji
     * di sini — hanya di produksi, tempat kegagalannya paling mahal.
     */
    private function mesinPalsu(?string $hasil): PemrosesGambar
    {
        return new class($hasil) extends PemrosesGambar
        {
            public bool $dipanggil = false;

            public function __construct(private readonly ?string $hasil) {}

            public function perkecil(string $isi, string $ekstensi): ?string
            {
                $this->dipanggil = true;

                return $this->hasil;
            }
        };
    }

    private function unggah(User $pengguna): void
    {
        $this->actingAs($pengguna)
            ->post('/panel/media', ['berkas' => [$this->berkasPng()], 'koleksi' => 'gambar'])
            ->assertRedirect();
    }

    public function test_gambar_besar_diganti_dengan_hasil_pengecilan(): void
    {
        $palsu = $this->mesinPalsu('gambar-yang-sudah-dikecilkan');
        $this->app->instance(PemrosesGambar::class, $palsu);

        $this->unggah($this->pengguna('konten_manager'));

        $media = MediaLibrary::induk()->getMedia('gambar')->firstOrFail();

        $this->assertTrue($palsu->dipanggil);
        $this->assertSame('gambar-yang-sudah-dikecilkan', Storage::disk('public')->get($media->getPathRelativeToRoot()));

        // Ukuran pada catatan media WAJIB ikut berubah: panel menghitung total
        // pemakaian dari kolom ini, dan peringatan kuota bergantung padanya.
        $this->assertSame(strlen('gambar-yang-sudah-dikecilkan'), (int) $media->fresh()->size);
    }

    public function test_gambar_yang_sudah_kecil_tidak_diganti(): void
    {
        // null berarti "tidak ada yang perlu dikerjakan".
        $this->app->instance(PemrosesGambar::class, $this->mesinPalsu(null));

        $this->unggah($this->pengguna('konten_manager'));

        $media = MediaLibrary::induk()->getMedia('gambar')->firstOrFail();

        $this->assertSame(base64_decode(self::PNG_KECIL), Storage::disk('public')->get($media->getPathRelativeToRoot()));
    }

    public function test_hasil_yang_justru_lebih_besar_tidak_dipakai(): void
    {
        $palsu = $this->mesinPalsu(str_repeat('x', 5000));
        $this->app->instance(PemrosesGambar::class, $palsu);

        $this->unggah($this->pengguna('konten_manager'));

        $media = MediaLibrary::induk()->getMedia('gambar')->firstOrFail();

        $this->assertTrue($palsu->dipanggil);
        $this->assertSame(
            base64_decode(self::PNG_KECIL),
            Storage::disk('public')->get($media->getPathRelativeToRoot()),
            'Berkas asli diganti dengan yang lebih besar — itu menambah pemakaian, bukan menghemat.',
        );
    }

    public function test_gif_tidak_diproses_supaya_animasinya_tidak_hilang(): void
    {
        $palsu = $this->mesinPalsu(null);
        $this->app->instance(PemrosesGambar::class, $palsu);

        $this->actingAs($this->pengguna('konten_manager'))
            ->post('/panel/media', [
                'berkas' => [UploadedFile::fake()->createWithContent(
                    'animasi.gif',
                    base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
                )],
                'koleksi' => 'gambar',
            ])
            ->assertRedirect();

        $this->assertFalse($palsu->dipanggil, 'GIF diproses — animasinya akan hilang.');
    }

    public function test_mesin_gambar_sungguhan_mengecilkan_gambar_besar(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped(
                'Ekstensi GD tidak tersedia di lingkungan ini. Produksi memilikinya, '
                .'jadi pengecilan yang sesungguhnya hanya dapat diuji di sana.'
            );
        }

        $manajer = new \Intervention\Image\ImageManager('gd');
        $besar = $manajer->create(2400, 1800)->fill('#2E3192');
        $isi = $besar->encodeUsingFileExtension('jpg', quality: 95)->toString();

        $hasil = (new PemrosesGambar)->perkecil($isi, 'jpg');

        $this->assertNotNull($hasil, 'Gambar 2400×1800 seharusnya diperkecil.');
        $this->assertLessThan(strlen($isi), strlen($hasil));

        $sesudah = $manajer->decodeBinary($hasil);
        $this->assertLessThanOrEqual(PemrosesGambar::MAKS_SISI, max($sesudah->width(), $sesudah->height()));
    }

    public function test_mesin_gambar_sungguhan_membiarkan_gambar_kecil(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD tidak tersedia di lingkungan ini.');
        }

        $manajer = new \Intervention\Image\ImageManager('gd');
        $kecil = $manajer->create(320, 240)->fill('#FFD100');
        $isi = $kecil->encodeUsingFileExtension('jpg', quality: 95)->toString();

        $this->assertNull(
            (new PemrosesGambar)->perkecil($isi, 'jpg'),
            'Gambar yang sudah kecil tidak boleh disimpan ulang — mutunya turun tanpa hemat yang berarti.',
        );
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

    /* ==================================================================
     | Peringatan jatah penyimpanan
     |
     | Ukuran berkas diubah langsung pada baris media sesudah diunggah,
     | supaya uji ini tidak bergantung pada besar berkas contoh (yang bila
     | berubah kelak akan merusak angka persentase secara membingungkan).
     ================================================================== */

    /**
     * Mengunggah satu berkas lalu menetapkan ukurannya sesuai keinginan uji.
     */
    private function berkasBerukuran(int $byte): void
    {
        $this->actingAs($this->pengguna('konten_manager'))
            ->post('/panel/media', ['berkas' => [$this->berkasPng()], 'koleksi' => 'gambar'])
            ->assertRedirect();

        MediaLibrary::induk()->getMedia('gambar')->firstOrFail()
            ->forceFill(['size' => $byte])
            ->saveQuietly();
    }

    public function test_pemakaian_penyimpanan_dihitung_terhadap_kuota(): void
    {
        config(['penyimpanan.kuota_mb' => 10]);

        $this->berkasBerukuran(5 * 1048576);

        $pemantau = app(PemantauPenyimpanan::class);

        $this->assertSame(5 * 1048576, $pemantau->terpakai());
        $this->assertSame(10 * 1048576, $pemantau->kuota());
        $this->assertSame(50.0, $pemantau->persen());
        $this->assertSame(PemantauPenyimpanan::AMAN, $pemantau->status());
    }

    public function test_ambang_peringatan_terpicu_pada_80_persen(): void
    {
        config(['penyimpanan.kuota_mb' => 10]);

        $this->berkasBerukuran(8 * 1048576); // tepat 80%

        $this->assertSame(80.0, app(PemantauPenyimpanan::class)->persen());
        $this->assertSame(PemantauPenyimpanan::WASPADA, app(PemantauPenyimpanan::class)->status());

        // Satu byte di bawah ambang harus tetap dianggap aman.
        MediaLibrary::induk()->getMedia('gambar')->firstOrFail()
            ->forceFill(['size' => 8 * 1048576 - 1])
            ->saveQuietly();

        $this->assertSame(PemantauPenyimpanan::AMAN, app(PemantauPenyimpanan::class)->status());
    }

    public function test_kuota_yang_tidak_diatur_tidak_membuat_pembagian_nol(): void
    {
        config(['penyimpanan.kuota_mb' => 0]);

        $this->berkasBerukuran(1048576);

        $pemantau = app(PemantauPenyimpanan::class);

        $this->assertNull($pemantau->persen());
        $this->assertSame(PemantauPenyimpanan::AMAN, $pemantau->status());
    }

    public function test_panel_diam_saat_penyimpanan_masih_aman(): void
    {
        config(['penyimpanan.kuota_mb' => 10]);

        $this->berkasBerukuran(1048576); // 10%

        $this->actingAs($this->pengguna('konten_manager'))
            ->get('/panel/media')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Media/Index', false)
                ->where('penyimpanan.status', PemantauPenyimpanan::AMAN));
    }

    public function test_panel_memperingatkan_saat_mendekati_kuota(): void
    {
        config(['penyimpanan.kuota_mb' => 10]);

        $this->berkasBerukuran(9 * 1048576); // 90%

        $this->actingAs($this->pengguna('konten_manager'))
            ->get('/panel/media')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Media/Index', false)
                ->where('penyimpanan.status', PemantauPenyimpanan::WASPADA)
                ->where('penyimpanan.persen', 90)
                ->where('penyimpanan.terpakai', '9,0 MB')
                ->where('penyimpanan.kuota', '10,0 MB'));
    }

    public function test_panel_menandai_kuota_yang_sudah_terlampaui(): void
    {
        config(['penyimpanan.kuota_mb' => 10]);

        $this->berkasBerukuran(11 * 1048576); // 110%

        $this->actingAs($this->pengguna('konten_manager'))
            ->get('/panel/media')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('penyimpanan.status', PemantauPenyimpanan::LEWAT)
                ->where('penyimpanan.persen', 110));
    }

    public function test_kuota_terlampaui_tidak_menolak_unggahan(): void
    {
        /*
         * Perilaku inilah alasan modul ini hanya memberi peringatan. Angka
         * pemakaian dihitung dari tabel media, sedangkan ukuran wadah yang
         * sesungguhnya selalu lebih besar (thumbnail dan berkas di luar
         * Pustaka Media tidak terhitung). Menolak unggahan berdasarkan
         * perkiraan yang bisa salah akan menghentikan pekerjaan pengurus di
         * saat yang paling tidak tepat, demi mencegah tagihan beberapa sen.
         */
        config(['penyimpanan.kuota_mb' => 10]);

        $this->berkasBerukuran(11 * 1048576); // sudah terlampaui

        $this->actingAs($this->pengguna('konten_manager'))
            ->post('/panel/media', ['berkas' => [$this->berkasPng()], 'koleksi' => 'gambar'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('media', 2);
    }

    /* ==================================================================
     | Definisi konversi gambar
     |
     | Uji-uji ini TIDAK menjalankan konversinya (wadah ini tidak punya GD),
     | melainkan memeriksa definisinya: nama manipulasi yang dipakai memang
     | ada di pustakanya, berkas dokumen tidak ikut dikonversi, dan konversinya
     | tidak dikerjakan di dalam permintaan unggah.
     |
     | Sebabnya nyata: `->shrinkOnly()` pernah tertulis di sini padahal metode
     | itu TIDAK ADA pada Spatie\Image\Image. Karena baris itu hanya berjalan
     | di mesin bergd, salah tulisnya baru terlihat di server sebagai modal
     | galat 500 — sesudah berkasnya terunggah.
     ================================================================== */

    /**
     * Model Pustaka Media dengan "mesin gambar" dipaksa ada.
     */
    private function pustakaDenganMesinGambar(): MediaLibrary
    {
        return new class extends MediaLibrary
        {
            protected function adaMesinGambar(): bool
            {
                return true;
            }
        };
    }

    /**
     * Catatan media dalam ingatan, hanya untuk membawa jenis berkasnya.
     */
    private function mediaUji(string $mime): Media
    {
        $media = new Media;
        $media->mime_type = $mime;

        return $media;
    }

    public function test_konversi_thumbnail_didaftarkan_untuk_gambar(): void
    {
        $pustaka = $this->pustakaDenganMesinGambar();
        $pustaka->registerMediaConversions($this->mediaUji('image/jpeg'));

        $this->assertCount(1, $pustaka->mediaConversions);
        $this->assertSame('kecil', $pustaka->mediaConversions[0]->getName());
    }

    public function test_konversi_thumbnail_diantrekan_bukan_dikerjakan_di_permintaan(): void
    {
        /*
         * Konversi berjalan SESUDAH berkas dan baris medianya tersimpan, dan
         * `createDerivedFiles()` di medialibrary TIDAK dibungkus try/catch.
         * Selama dikerjakan di dalam permintaan unggah, kegagalan gambar
         * bentuk apa pun — termasuk kehabisan memori — selalu menjadi modal
         * galat 500 padahal unggahannya berhasil.
         */
        $pustaka = $this->pustakaDenganMesinGambar();
        $pustaka->registerMediaConversions($this->mediaUji('image/png'));

        $this->assertTrue($pustaka->mediaConversions[0]->shouldBeQueued());
    }

    public function test_konversi_thumbnail_tidak_didaftarkan_untuk_dokumen(): void
    {
        /*
         * `PerformConversionAction::execute()` memanggil
         * `ImageGeneratorFactory::forMedia($media)->convert(...)` tanpa
         * memeriksa null, padahal fungsi itu boleh mengembalikan null. Untuk
         * DOCX/XLSX/PPTX (dan PDF yang butuh pustaka tambahan) hasilnya
         * "Call to a member function convert() on null" — 500 juga.
         */
        $pustaka = $this->pustakaDenganMesinGambar();

        $pustaka->registerMediaConversions($this->mediaUji(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ));

        $this->assertSame([], $pustaka->mediaConversions);
    }

    public function test_setiap_manipulasi_konversi_ada_di_pustaka_gambar(): void
    {
        $pustaka = $this->pustakaDenganMesinGambar();
        $pustaka->registerMediaConversions($this->mediaUji('image/jpeg'));

        $manipulasi = [];

        foreach ($pustaka->mediaConversions as $konversi) {
            $manipulasi = [...$manipulasi, ...array_keys($konversi->getManipulations()->toArray())];
        }

        $this->assertNotEmpty($manipulasi, 'Konversi didaftarkan tanpa manipulasi apa pun.');

        foreach ($manipulasi as $nama) {
            $this->assertTrue(
                method_exists(Image::class, $nama),
                "Manipulasi '{$nama}' tidak ada di ".Image::class.' — konversi akan gagal saat dijalankan.',
            );
        }
    }

    public function test_pengandar_gambar_yang_dipakai_adalah_kelas_yang_ada(): void
    {
        /*
         * `CanResolveDriver::resolveDriver()` menolak apa pun yang bukan nama
         * kelas. `new ImageManager('gd')` — yang dulu tertulis di
         * PemrosesGambar — melempar "Argument $driver must be existing class
         * name". Kegagalannya ditangkap pendengar pengecil gambar dan hanya
         * menjadi satu baris WARNING, yang tidak pernah muncul di wadah tanpa
         * GD; pengecilan gambar karena itu TIDAK PERNAH bekerja sekali pun.
         */
        $pengandar = PemrosesGambar::pengandar();

        $this->assertTrue(class_exists($pengandar), "Pengandar gambar '{$pengandar}' bukan kelas yang ada.");
        $this->assertTrue(
            is_subclass_of($pengandar, DriverInterface::class),
            "Pengandar gambar '{$pengandar}' bukan implementasi ".DriverInterface::class.'.',
        );
    }
}
