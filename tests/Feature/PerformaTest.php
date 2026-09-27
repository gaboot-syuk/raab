<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SocialLink;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 9E — performa.
 *
 * Yang dijaga di sini bukan "halaman cepat" (kecepatan bergantung pada server),
 * melainkan JUMLAH QUERY: satu halaman publik tidak boleh menembak basis data
 * puluhan kali untuk hal yang sama.
 *
 * Penyakit yang dicari adalah N+1 — mengambil daftar, lalu satu query lagi per
 * barisnya. Di laptop pengembang, dua puluh baris berjalan mulus. Di server
 * gratis dengan basis data jauh, seratus baris membuat halaman berhenti
 * merespons. Karena itu batasnya diuji dengan ANGKA, bukan dengan perasaan.
 */
class PerformaTest extends TestCase
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

    /**
     * Hitung query yang dijalankan saat membuka sebuah halaman.
     *
     * @return array{jumlah: int, kembar: array<string, int>, semua: array<int, string>}
     */
    private function hitung(string $jalur): array
    {
        DB::enableQueryLog();

        $this->get($jalur)->assertOk();

        $semua = array_map(
            fn (array $q): string => preg_replace('/\s+/', ' ', (string) ($q['query'] ?? $q['raw_query'] ?? '')),
            DB::getQueryLog()
        );

        DB::disableQueryLog();
        DB::flushQueryLog();

        $kembar = [];

        foreach (array_count_values($semua) as $kueri => $jumlah) {
            if ($jumlah > 3) {
                $kembar[$kueri] = $jumlah;
            }
        }

        return ['jumlah' => count($semua), 'kembar' => $kembar, 'semua' => $semua];
    }

    public function test_halaman_publik_tidak_menembak_basis_data_terlalu_sering(): void
    {
        /*
         * Batasnya ditulis dengan sisa sedikit di atas angka nyata, bukan
         * longgar. Batas yang longgar tidak menjaga apa pun: satu N+1 baru bisa
         * masuk tanpa membuat uji ini gagal, dan uji ini berhenti berguna
         * persis saat ia paling dibutuhkan.
         */
        $anggaran = [
            '/' => 20,
            '/publikasi' => 15,
            '/anggota' => 15,
            '/perpustakaan' => 12,
            '/inventaris' => 12,
            '/galeri' => 12,
            '/kontak' => 10,
            '/aspirasi' => 10,
        ];

        $laporan = [];

        foreach ($anggaran as $jalur => $batas) {
            $hasil = $this->hitung($jalur);
            $laporan[] = sprintf('%-16s %3d query (batas %d)', $jalur, $hasil['jumlah'], $batas);

            $this->assertLessThanOrEqual(
                $batas,
                $hasil['jumlah'],
                "Halaman {$jalur} memakai {$hasil['jumlah']} query, melebihi batas {$batas}."
            );
        }

        fwrite(STDERR, "\n=== Jumlah query per halaman ===\n".implode("\n", $laporan)."\n");
    }

    public function test_tidak_ada_query_yang_diulang_terlalu_banyak(): void
    {
        foreach (['/', '/publikasi', '/anggota', '/perpustakaan', '/inventaris', '/galeri'] as $jalur) {
            $hasil = $this->hitung($jalur);

            foreach ($hasil['kembar'] as $kueri => $jumlah) {
                $this->fail(
                    "Halaman {$jalur} menjalankan query yang sama {$jumlah} kali — tanda N+1:\n{$kueri}"
                );
            }
        }

        $this->assertTrue(true);
    }

    /* ------------------------------------------------------------------ */
    /* Cache halaman statis                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Kunjungan pertama membayar harganya, kunjungan berikutnya tidak.
     *
     * Itu perbedaan antara "ada cache" dan "cache-nya bekerja": yang diuji
     * adalah kunjungan DINGIN (cache kosong) versus kunjungan HANGAT, bukan
     * dua kunjungan yang dua-duanya sudah hangat.
     */
    public function test_halaman_statis_hanya_dibaca_dari_basis_data_sekali(): void
    {
        Cache::flush();

        $dingin = $this->hitung('/sejarah');
        $hangat = $this->hitung('/sejarah');

        $this->assertGreaterThanOrEqual(
            1,
            $dingin['jumlah'],
            'Kunjungan pertama tidak menyentuh basis data sama sekali — cache tampaknya sudah terisi sebelum uji berjalan.'
        );

        $this->assertSame(
            0,
            $hangat['jumlah'],
            "Kunjungan kedua masih menembak basis data.\nQuery:\n- ".implode("\n- ", $hangat['semua'])
        );
    }

    /**
     * Perubahan halaman langsung terlihat di situs publik.
     *
     * Pengurus menyimpan perbaikan, lalu melihat isi yang lama di situs, dan
     * menyimpulkan penyimpanannya gagal. Uji ini memastikan perubahan langsung
     * terlihat.
     */
    public function test_perubahan_halaman_langsung_terlihat_di_situs_publik(): void
    {
        // Kunjungan pertama mengisi cache dengan isi yang lama.
        $this->get('/sejarah')->assertOk();

        $pengurus = User::factory()->create(['email_verified_at' => now()]);
        $pengurus->assignRole('konten_manager');

        $halaman = Page::query()->where('kunci', Page::TIPE_SEJARAH)->firstOrFail();

        $this->actingAs($pengurus)->put('/panel/halaman/'.$halaman->id, [
            'judul' => ['id' => 'Sejarah Baru Rayon Ali Ahmad Baktsir'],
            'slug' => ['id' => 'sejarah', 'en' => 'sejarah'],
            'ringkasan' => ['id' => 'Ringkasan baru.'],
            'konten' => ['id' => '<p>Isi sejarah yang baru saja disunting.</p>'],
            'status' => 'terbit',
        ])->assertRedirect();

        $this->get('/sejarah')
            ->assertOk()
            ->assertSee('Sejarah Baru Rayon Ali Ahmad Baktsir', false)
            ->assertSee('Isi sejarah yang baru saja disunting', false);
    }

    /* ------------------------------------------------------------------ */
    /* Cache dengan pengandar yang sama seperti produksi                   */
    /* ------------------------------------------------------------------ */

    /**
     * Uji ini ada karena pengujian MEMBOHONGI kami.
     *
     * Pengujian memakai pengandar cache `array`, yang tidak membatasi kelas apa
     * pun saat mengembalikan nilai. Produksi memakai pengandar `database`, dan
     * pengandar itu MEMBATASI kelas yang boleh dikembalikan: model Eloquent
     * yang tersimpan akan kembali sebagai `__PHP_Incomplete_Class`, lalu
     * halaman gagal dengan galat tipe.
     *
     * Akibatnya, seluruh rangkaian uji hijau sementara beranda dan halaman
     * statis di server mengembalikan 500. Uji ini memakai pengandar yang sama
     * dengan produksi supaya hal itu tidak bisa terulang tanpa ketahuan.
     */
    public function test_cache_utuh_saat_memakai_pengandar_yang_sama_dengan_produksi(): void
    {
        config(['cache.default' => 'database']);
        Cache::store('database')->flush();

        // Halaman statis: dibaca lewat model yang di-cache.
        $this->get('/sejarah')->assertOk();

        $halaman = Page::berdasarkanKunci(Page::TIPE_SEJARAH);

        $this->assertInstanceOf(Page::class, $halaman);
        $this->assertIsString($halaman->judul);
        $this->assertNotSame('', $halaman->judul);

        // Tautan media sosial: dibaca lewat koleksi yang di-cache, dan dirender
        // di kaki SETIAP halaman publik.
        $sosmed = SocialLink::aktifTerCache();

        $this->assertInstanceOf(SocialLink::class, $sosmed->first());
        $this->assertIsString($sosmed->first()->platform);

        // Kunjungan berikutnya membaca dari cache, bukan dari basis data.
        $this->get('/sejarah')->assertOk();
    }

    /**
     * Satu entri cache harus melayani kedua bahasa.
     *
     * Yang disimpan adalah atribut mentah, sehingga nilai terjemahannya masih
     * utuh. Kalau yang tersimpan sudah berupa hasil jadi, labelnya akan
     * terkunci pada bahasa saat cache dibuat — dan halaman Inggris menampilkan
     * label Indonesia.
     *
     * Nilainya dibaca SAAT bahasanya masih berlaku. Judul diselesaikan pada
     * saat dibaca, bukan saat diambil dari cache; menunda pembacaannya sampai
     * setelah bahasa berganti akan mengukur bahasa yang terakhir, bukan yang
     * dimaksud.
     */
    public function test_cache_halaman_melayani_kedua_bahasa(): void
    {
        config(['cache.default' => 'database']);
        Cache::store('database')->flush();

        $halaman = Page::query()->where('kunci', Page::TIPE_SEJARAH)->firstOrFail();
        $halaman->setTranslation('judul', 'en', 'History of the Rayon');
        $halaman->save();

        Page::lupakan(Page::TIPE_SEJARAH);

        app()->setLocale('id');
        $dariCache = Page::berdasarkanKunci(Page::TIPE_SEJARAH);
        $judulIndonesia = $dariCache->judul;

        app()->setLocale('en');
        $judulInggris = Page::berdasarkanKunci(Page::TIPE_SEJARAH)->judul;

        $this->assertSame('History of the Rayon', $judulInggris);
        $this->assertSame('Sejarah', $judulIndonesia);
    }
}
