<?php

use App\Http\Controllers\Anggota\DasborController as DasborAnggotaController;
use App\Http\Controllers\Anggota\HibahController as HibahAnggotaController;
use App\Http\Controllers\Anggota\IuranController as IuranAnggotaController;
use App\Http\Controllers\Anggota\PustakaController as PustakaAnggotaController;
use App\Http\Controllers\Anggota\KaryaController;
use App\Http\Controllers\Anggota\KartuController as KartuAnggotaController;
use App\Http\Controllers\Anggota\KegiatanController as KegiatanAnggotaController;
use App\Http\Controllers\Anggota\KontribusiController as KontribusiAnggotaController;
use App\Http\Controllers\Anggota\PresensiController as PresensiAnggotaController;
use App\Http\Controllers\Anggota\PrestasiController as PrestasiAnggotaController;
use App\Http\Controllers\Panel\AnggaranController;
use App\Http\Controllers\Panel\AspirasiController as AspirasiPanelController;
use App\Http\Controllers\Panel\ArtikelController;
use App\Http\Controllers\Panel\EventController;
use App\Http\Controllers\Panel\HibahController;
use App\Http\Controllers\Panel\InventarisController as InventarisPanelController;
use App\Http\Controllers\Panel\IuranController;
use App\Http\Controllers\Panel\KeuanganController;
use App\Http\Controllers\Panel\KeuanganKategoriController;
use App\Http\Controllers\Panel\LaporanKeuanganController;
use App\Http\Controllers\Panel\PeminjamanController;
use App\Http\Controllers\Panel\TransaksiController;
use App\Http\Controllers\Panel\PerpustakaanController as PerpustakaanPanelController;
use App\Http\Controllers\Panel\PesertaController;
use App\Http\Controllers\Panel\Organisasi\GaleriController;
use App\Http\Controllers\Panel\Organisasi\JabatanController;
use App\Http\Controllers\Panel\Organisasi\MentorController;
use App\Http\Controllers\Panel\Organisasi\PenugasanController;
use App\Http\Controllers\Panel\Organisasi\PeriodeController;
use App\Http\Controllers\Panel\Organisasi\UnitController;
use App\Http\Controllers\Panel\DasborController;
use App\Http\Controllers\Panel\HalamanController;
use App\Http\Controllers\Panel\KategoriController;
use App\Http\Controllers\Panel\KeanggotaanController;
use App\Http\Controllers\Panel\KegiatanController;
use App\Http\Controllers\Panel\KontribusiController;
use App\Http\Controllers\Panel\PresensiController as PresensiPanelController;
use App\Http\Controllers\Panel\PrestasiController as PrestasiPanelController;
use App\Http\Controllers\Panel\MediaController;
use App\Http\Controllers\Panel\PengaturanController;
use App\Http\Controllers\Panel\PenggunaController;
use App\Http\Controllers\Panel\PeranController;
use App\Http\Controllers\Panel\PesanController;
use App\Http\Controllers\Panel\SliderController;
use App\Http\Controllers\Panel\VerifikasiController;
use App\Http\Controllers\Public\BerandaController;
use App\Http\Controllers\Public\DirektoriController;
use App\Http\Controllers\Public\ManifestController;
use App\Http\Controllers\Public\PendaftaranController;
use App\Http\Controllers\Public\AspirasiController as AspirasiPublikController;
use App\Http\Controllers\Public\GaleriController as GaleriPublikController;
use App\Http\Controllers\Public\KaderController;
use App\Http\Controllers\Public\KartuKaderController;
use App\Http\Controllers\Public\KatalogInventarisController;
use App\Http\Controllers\Public\PerpustakaanController;
use App\Http\Controllers\Public\LsoController;
use App\Http\Controllers\Public\StrukturController;
use App\Http\Controllers\Public\HalamanController as HalamanPublikController;
use App\Http\Controllers\Public\LayananController;
use App\Http\Controllers\Public\PublikasiController;
use App\Http\Controllers\Public\PrestasiController as PrestasiPublikController;
use App\Http\Controllers\Public\PengumumanController as PengumumanPublikController;
use App\Http\Controllers\Public\ArsipController as ArsipPublikController;
use App\Http\Controllers\Internal\HealthController;
use App\Http\Controllers\Internal\SchedulerController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Panel\PengumumanController as PengumumanPanelController;
use App\Http\Controllers\Panel\ArsipController as ArsipPanelController;
use App\Http\Controllers\Anggota\PengumumanController as PengumumanAnggotaController;
use App\Http\Controllers\Anggota\ArsipController as ArsipAnggotaController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\Panel\LaporanController;
use App\Http\Controllers\Panel\CadanganController;
use App\Http\Controllers\Panel\DiagnostikController;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/*
|--------------------------------------------------------------------------
| Rute Publik — dwibahasa
|--------------------------------------------------------------------------
| Indonesia dirender tanpa prefiks, Inggris memakai prefiks /en.
| Halaman publik memakai Blade (server-rendered) agar ramah SEO
| dan tetap berfungsi tanpa JavaScript.
*/
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    // 'localeSessionRedirect' sengaja TIDAK dipakai: middleware itu mengalihkan
    // "/" ke "/en" bila sesi terakhir berbahasa Inggris, sehingga halaman
    // Indonesia (bahasa utama) tidak dapat dibuka langsung pada alamat "/".
    'middleware' => ['localizationRedirect', 'localeViewPath'],
], function () {
    Route::get('/', [BerandaController::class, 'index'])->name('public.beranda');

    // Halaman statis yang dikelola Konten Manager
    Route::get('/sejarah', [HalamanPublikController::class, 'sejarah'])->name('public.sejarah');
    Route::get('/visi-misi', [HalamanPublikController::class, 'visiMisi'])->name('public.visi-misi');
    Route::get('/sambutan', [HalamanPublikController::class, 'sambutan'])->name('public.sambutan');

    // Layanan: kontak, lokasi sekretariat, dan media sosial
    Route::get('/kontak', [LayananController::class, 'kontak'])->name('public.kontak');
    Route::get('/lokasi', [LayananController::class, 'lokasi'])->name('public.lokasi');
    Route::get('/media-sosial', [LayananController::class, 'mediaSosial'])->name('public.media-sosial');

    /*
     * Alias pendaftaran.
     *
     * Rute pendaftaran sebenarnya ditangani Fortify pada /register (beserta
     * proses kirim formulirnya). /daftar disediakan sebagai alamat resmi yang
     * dipakai tautan di situs, agar seragam dengan istilah berbahasa Indonesia.
     */
    Route::redirect('/daftar', '/register', 301)->name('public.daftar');

    /*
     * Formulir kontak dibatasi 5 kiriman per menit per IP untuk menahan spam.
     * Honeypot ditangani di KontakRequest.
     */
    Route::post('/kontak', [LayananController::class, 'kirimKontak'])
        ->middleware('throttle:5,1')
        ->name('public.kontak.kirim');

    // Direktori publik anggota & alumni.
    // Hanya kolom yang diizinkan pemilik data yang ditampilkan — lihat
    // App\Models\Member::bolehTampil().
    Route::get('/anggota', [DirektoriController::class, 'anggota'])->name('public.anggota');
    Route::get('/alumni', [DirektoriController::class, 'alumni'])->name('public.alumni');

    /*
     * Organisasi: bagan struktur, Biro & LSO, galeri, dan profil kader.
     * Semua halaman ini hanya membaca; tidak ada satu pun yang dapat mengubah
     * data kepengurusan dari sisi publik.
     */
    Route::get('/struktur', StrukturController::class)->name('public.struktur');
    Route::get('/lso', [LsoController::class, 'daftar'])->name('public.lso');
    Route::get('/lso/{slug}', [LsoController::class, 'detail'])->name('public.lso.detail');
    Route::get('/galeri', [GaleriPublikController::class, 'daftar'])->name('public.galeri');
    Route::get('/galeri/{slug}', [GaleriPublikController::class, 'detail'])->name('public.galeri.detail');
    Route::get('/prestasi/kader/{slug}', KaderController::class)->name('public.prestasi.kader');

    /*
     * Prestasi kader — hanya yang SUDAH terverifikasi DAN diizinkan pemiliknya.
     */
    Route::get('/prestasi', PrestasiPublikController::class)->name('public.prestasi');

    /*
     * Aspirasi: papan publik, formulir, dan pelacakan.
     *
     * Honeypot ditangani di controller; throttle menahan kiriman beruntun.
     * Pelacakan juga dibatasi karena nomor tiket berurutan — tanpa batas,
     * nomor tiket bisa dicoba satu per satu.
     */
    Route::get('/aspirasi', [AspirasiPublikController::class, 'index'])->name('public.aspirasi');
    Route::post('/aspirasi', [AspirasiPublikController::class, 'kirim'])
        ->middleware('throttle:5,1')
        ->name('public.aspirasi.kirim');
    Route::post('/aspirasi/lacak', [AspirasiPublikController::class, 'lacak'])
        ->middleware('throttle:10,1')
        ->name('public.aspirasi.lacak');

    /*
     * Pengumuman & arsip dokumen yang memang dibuka untuk umum.
     *
     * Halaman /pengumuman hanya memuat pengumuman bertipe publik yang sudah
     * berlaku; /arsip hanya memuat dokumen yang audiensnya menyertakan "umum".
     */
    Route::get('/pengumuman', [PengumumanPublikController::class, 'index'])->name('public.pengumuman');
    Route::get('/arsip', [ArsipPublikController::class, 'index'])->name('public.arsip');

    /*
     * Jalur unduhan dokumen arsip — berlaku untuk TAMU MAUPUN ANGGOTA.
     *
     * Berkasnya ada di disk privat, jadi ini satu-satunya pintu keluarnya dan
     * pemeriksaan audiens di dalamnya tidak bisa dilewati dengan membuka
     * alamat berkas secara langsung. Anggota memakai tautan yang sama.
     */
    Route::get('/arsip/{dokumen}/unduh', [ArsipPublikController::class, 'unduh'])
        ->middleware('throttle:60,1')
        ->name('arsip.unduh');

    /*
     * Peta situs. DI LUAR grup lokalisasi di bawah ini sengaja: alamatnya
     * harus satu dan sama untuk semua bahasa (`/sitemap.xml`), karena di dalam
     * berkasnya sendiri tiap halaman sudah didaftarkan beserta padanan
     * bahasanya lewat `xhtml:link`.
     */
    /*
     * Verifikasi kartu kader — publik, tanpa login.
     *
     * Throttle dipasang bukan karena token 48 karakter bisa ditebak, melainkan
     * supaya halaman ini tidak bisa dijadikan sasaran pembanjiran permintaan
     * yang membebani basis data.
     */
    Route::get('/verifikasi-kader/{token}', KartuKaderController::class)
        ->middleware('throttle:60,1')
        ->name('public.verifikasi-kader');

    /*
     * Inventaris & perpustakaan — HANYA BACA.
     * Barang bertanda `is_public` saja yang tampil di /inventaris.
     */
    Route::get('/inventaris', KatalogInventarisController::class)->name('public.inventaris');
    Route::get('/perpustakaan', [PerpustakaanController::class, 'index'])->name('public.perpustakaan');
    Route::get('/perpustakaan/{slug}', [PerpustakaanController::class, 'detail'])->name('public.perpustakaan.detail');

    /*
     * Pendaftaran event Mapaba & PKD — tanpa akun.
     *
     * URUTAN WAJIB: /status dan /sukses didaftarkan SEBELUM pola /{jenis} dan
     * /{slug}, kalau tidak keduanya akan tertangkap sebagai slug event.
     */
    Route::get('/pendaftaran', [PendaftaranController::class, 'index'])->name('public.pendaftaran');
    Route::get('/pendaftaran/status', [PendaftaranController::class, 'status'])->name('public.pendaftaran.status');
    Route::get('/pendaftaran/sukses/{kode}', [PendaftaranController::class, 'sukses'])->name('public.pendaftaran.sukses');
    Route::get('/pendaftaran/kartu', [PendaftaranController::class, 'kartu'])->name('public.pendaftaran.kartu');
    Route::get('/pendaftaran/{jenis}', [PendaftaranController::class, 'jenis'])
        ->whereIn('jenis', array_keys(\App\Models\Event::JENIS))
        ->name('public.pendaftaran.jenis');
    Route::get('/pendaftaran/{slug}', [PendaftaranController::class, 'detail'])->name('public.pendaftaran.detail');

    // Honeypot ditangani di controller; throttle menahan kiriman beruntun.
    Route::post('/pendaftaran/{event}/kirim', [PendaftaranController::class, 'kirim'])
        ->middleware('throttle:5,1')
        ->name('public.pendaftaran.kirim');

    /*
     * Publikasi: daftar gabungan, halaman per tipe, dan halaman baca.
     * Hanya artikel berstatus "terbit" yang tampil — draf tidak mungkin bocor.
     */
    Route::get('/publikasi', [PublikasiController::class, 'index'])->name('public.publikasi');
    Route::get('/publikasi/{tipe}', [PublikasiController::class, 'perTipe'])
        ->whereIn('tipe', ['berita', 'opini', 'kajian', 'esai', 'sastra'])
        ->name('public.publikasi.tipe');
    Route::get('/publikasi/{tipe}/{slug}', [PublikasiController::class, 'detail'])
        ->whereIn('tipe', ['berita', 'opini', 'kajian', 'esai', 'sastra'])
        ->name('public.publikasi.detail');
});

/*
 * Peta situs — DI LUAR grup lokalisasi di atas, dan memang harus begitu.
 *
 * Alamatnya wajib satu dan sama untuk semua bahasa. Selama rute ini berada di
 * dalam grup berprefiks bahasa, tersedia DUA peta situs yang isinya identik
 * (/sitemap.xml dan /en/sitemap.xml). Bagi mesin pencari itu dua berkas
 * berbeda, bukan satu berkas dengan dua bahasa — dan dua berkas yang sama
 * isinya justru melemahkan keduanya.
 *
 * Padanan bahasanya sudah didaftarkan di DALAM berkas lewat `xhtml:link`,
 * jadi tidak perlu alamat terpisah.
 */
Route::get('/sitemap.xml', SitemapController::class)->name('public.sitemap');

/*
 * Pemicu penjadwal — untuk hosting TANPA cron.
 *
 * Di LUAR grup lokalisasi (alamatnya harus satu, bukan /en/internal/...),
 * dan tanpa autentikasi: yang memanggilnya adalah layanan penjadwal, bukan
 * manusia. Perlindungannya ada pada token di dalam alamat, dan pada
 * perbandingan `hash_equals` di dalam pengendalinya.
 *
 * Throttle dipasang supaya alamat ini tidak bisa dijadikan sasaran pembanjiran
 * permintaan — setiap panggilan yang sah menjalankan pekerjaan nyata.
 */
Route::get('/internal/scheduler/{token}', SchedulerController::class)
    ->middleware('throttle:12,1')
    ->name('internal.scheduler');

/*
 * Manifest aplikasi web — untuk memasang situs sebagai pintasan layar utama.
 *
 * Di LUAR grup lokalisasi, sama seperti dua alamat di bawah ini: peramban
 * mencari manifestnya di SATU alamat tetap yang ditulis di <link rel="manifest">.
 * Kalau ia ikut berprefiks bahasa, halaman Indonesia akan menunjuk manifest
 * yang tidak ada — dan kegagalannya senyap, karena peramban hanya berhenti
 * menawarkan pemasangan.
 */
Route::get('/manifest.webmanifest', ManifestController::class)
    ->name('public.manifest');

/*
 * Titik pemeriksaan kesehatan — untuk pemantau uptime & penjaga tetap bangun.
 *
 * Di LUAR grup lokalisasi, sama seperti pemicu penjadwal di atas: alamatnya
 * harus SATU, bukan /en/health. Layanan pemantau tidak tahu apa-apa soal
 * bahasa, dan alamat yang bercabang membuat pendaftarannya mudah salah.
 *
 * Tanpa autentikasi — yang memanggilnya bukan manusia. Jawabannya juga tidak
 * memuat rincian apa pun yang berguna bagi penyerang.
 *
 * Throttle dipasang supaya alamat ini tidak bisa dijadikan sasaran pembanjiran:
 * setiap panggilan menyentuh basis data, dan itu tidak gratis.
 */
Route::get('/health', HealthController::class)
    ->middleware('throttle:30,1')
    ->name('internal.health');

/*
|--------------------------------------------------------------------------
| Rute Panel Pengurus (Inertia + Vue)
|--------------------------------------------------------------------------
| Area panel tidak memakai prefiks bahasa — antarmuka panel berbahasa
| Indonesia saja (lihat docs/10-lokalisasi-bilingual.md).
| Middleware 'verified' memastikan email sudah diverifikasi lebih dulu.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/panel', [DasborController::class, 'index'])->name('panel');

    // Halaman statis — hanya untuk pemegang izin pages.manage
    Route::middleware('permission:pages.manage')->group(function () {
        Route::get('/panel/halaman', [HalamanController::class, 'index'])->name('panel.halaman');
        Route::get('/panel/halaman/{page}', [HalamanController::class, 'sunting'])->name('panel.halaman.sunting');
        Route::put('/panel/halaman/{page}', [HalamanController::class, 'perbarui'])->name('panel.halaman.perbarui');
    });

    // Pengaturan situs — hanya untuk pemegang izin settings.site-manage
    Route::middleware('permission:settings.site-manage')->group(function () {
        Route::get('/panel/pengaturan', [PengaturanController::class, 'index'])->name('panel.pengaturan');
        Route::put('/panel/pengaturan', [PengaturanController::class, 'perbarui'])->name('panel.pengaturan.perbarui');
    });

    // Pesan masuk dari formulir kontak publik
    Route::middleware('permission:messages.view')->group(function () {
        Route::get('/panel/pesan', [PesanController::class, 'index'])->name('panel.pesan');
    });

    Route::middleware('permission:messages.reply')->group(function () {
        Route::patch('/panel/pesan/{pesan}', [PesanController::class, 'perbarui'])->name('panel.pesan.perbarui');
        Route::delete('/panel/pesan/{pesan}', [PesanController::class, 'hapus'])->name('panel.pesan.hapus');
    });

    // Pustaka media — melihat & mengunduh untuk banyak peran, mengubah hanya Konten Manager
    Route::middleware('permission:media.view')->group(function () {
        Route::get('/panel/media', [MediaController::class, 'index'])->name('panel.media');
    });

    Route::middleware('permission:media.upload')->group(function () {
        Route::post('/panel/media', [MediaController::class, 'unggah'])->name('panel.media.unggah');
    });

    Route::middleware('permission:media.update')->group(function () {
        Route::patch('/panel/media/{media}', [MediaController::class, 'perbarui'])->name('panel.media.perbarui');
    });

    Route::middleware('permission:media.delete')->group(function () {
        Route::delete('/panel/media/{media}', [MediaController::class, 'hapus'])->name('panel.media.hapus');
    });

    // Slider beranda
    Route::middleware('permission:sliders.manage')->group(function () {
        Route::get('/panel/slider', [SliderController::class, 'index'])->name('panel.slider');
        Route::post('/panel/slider', [SliderController::class, 'simpan'])->name('panel.slider.simpan');
        Route::put('/panel/slider/urutan', [SliderController::class, 'urutkan'])->name('panel.slider.urutkan');
        Route::put('/panel/slider/{slider}', [SliderController::class, 'perbarui'])->name('panel.slider.perbarui');
        Route::delete('/panel/slider/{slider}', [SliderController::class, 'hapus'])->name('panel.slider.hapus');
    });

    // Akun pengurus & peran — hanya Superadmin
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/panel/pengguna', [PenggunaController::class, 'index'])->name('panel.pengguna');
    });

    Route::middleware('permission:users.create')->group(function () {
        Route::post('/panel/pengguna', [PenggunaController::class, 'simpan'])->name('panel.pengguna.simpan');
    });

    Route::middleware('permission:users.update')->group(function () {
        Route::put('/panel/pengguna/{pengguna}', [PenggunaController::class, 'perbarui'])->name('panel.pengguna.perbarui');
    });

    Route::middleware('permission:users.delete')->group(function () {
        Route::delete('/panel/pengguna/{pengguna}', [PenggunaController::class, 'hapus'])->name('panel.pengguna.hapus');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/panel/peran', [PeranController::class, 'index'])->name('panel.peran');
        Route::put('/panel/peran/{peran}', [PeranController::class, 'perbarui'])->name('panel.peran.perbarui');
    });

    // Antrean verifikasi pendaftar — Sekretaris
    Route::middleware('permission:verifications.view')->group(function () {
        Route::get('/panel/verifikasi', [VerifikasiController::class, 'index'])->name('panel.verifikasi');
        Route::get('/panel/verifikasi/{pengajuan}', [VerifikasiController::class, 'detail'])->name('panel.verifikasi.detail');
    });

    Route::middleware('permission:verifications.approve')->group(function () {
        Route::post('/panel/verifikasi/{pengajuan}/setujui', [VerifikasiController::class, 'setujui'])->name('panel.verifikasi.setujui');
    });

    Route::middleware('permission:verifications.reject')->group(function () {
        Route::post('/panel/verifikasi/{pengajuan}/tolak', [VerifikasiController::class, 'tolak'])->name('panel.verifikasi.tolak');
    });

    Route::middleware('permission:verifications.request-revision')->group(function () {
        Route::post('/panel/verifikasi/{pengajuan}/perbaikan', [VerifikasiController::class, 'mintaPerbaikan'])->name('panel.verifikasi.perbaikan');
    });

    // Keanggotaan — daftar anggota, perubahan status, kartu
    Route::middleware('permission:members.view')->group(function () {
        Route::get('/panel/keanggotaan', [KeanggotaanController::class, 'index'])->name('panel.keanggotaan');
        Route::get('/panel/keanggotaan/{anggota}', [KeanggotaanController::class, 'detail'])->name('panel.keanggotaan.detail');
    });

    Route::middleware('permission:members.change-status')->group(function () {
        Route::patch('/panel/keanggotaan/{anggota}/status', [KeanggotaanController::class, 'ubahStatus'])->name('panel.keanggotaan.status');
        Route::post('/panel/keanggotaan/alumni-massal', [KeanggotaanController::class, 'massalAlumni'])->name('panel.keanggotaan.alumni-massal');
    });

    Route::middleware('permission:member-cards.issue')->group(function () {
        Route::post('/panel/keanggotaan/{anggota}/kartu', [KeanggotaanController::class, 'terbitkanKartu'])->name('panel.keanggotaan.kartu');
    });

    /*
     * Artikel — dipakai penulis (kader) maupun pengelola konten.
     * Urutan penting: rute tetap (/baru, /kategori-artikel) harus didaftarkan
     * SEBELUM rute berpola /{artikel}, agar tidak tertukar.
     */
    Route::middleware('permission:articles.view')->group(function () {
        Route::get('/panel/artikel', [ArtikelController::class, 'index'])->name('panel.artikel');
        Route::get('/panel/statistik-publikasi', [ArtikelController::class, 'statistik'])->name('panel.artikel.statistik');
    });

    Route::middleware('permission:articles.create')->group(function () {
        Route::get('/panel/artikel/baru', [ArtikelController::class, 'baru'])->name('panel.artikel.baru');
        Route::post('/panel/artikel', [ArtikelController::class, 'simpan'])->name('panel.artikel.simpan');
    });

    Route::middleware('permission:article-categories.manage')->group(function () {
        Route::get('/panel/kategori-artikel', [KategoriController::class, 'index'])->name('panel.artikel.kategori');
        Route::post('/panel/kategori-artikel', [KategoriController::class, 'simpanKategori'])->name('panel.artikel.kategori.simpan');
        Route::put('/panel/kategori-artikel/{kategori}', [KategoriController::class, 'perbaruiKategori'])->name('panel.artikel.kategori.perbarui');
        Route::delete('/panel/kategori-artikel/{kategori}', [KategoriController::class, 'hapusKategori'])->name('panel.artikel.kategori.hapus');
        Route::delete('/panel/tag-artikel/{tag}', [KategoriController::class, 'hapusTag'])->name('panel.artikel.tag.hapus');
    });

    Route::middleware('permission:articles.view')->group(function () {
        Route::get('/panel/artikel/{artikel}', [ArtikelController::class, 'sunting'])->name('panel.artikel.sunting');
    });

    Route::middleware('permission:articles.update')->group(function () {
        Route::put('/panel/artikel/{artikel}', [ArtikelController::class, 'perbarui'])->name('panel.artikel.perbarui');
        Route::post('/panel/artikel/{artikel}/kirim', [ArtikelController::class, 'kirimReview'])->name('panel.artikel.kirim');
    });

    Route::middleware('permission:articles.review')->group(function () {
        Route::post('/panel/artikel/{artikel}/revisi', [ArtikelController::class, 'mintaRevisi'])->name('panel.artikel.revisi');
        Route::post('/panel/artikel/{artikel}/tolak', [ArtikelController::class, 'tolak'])->name('panel.artikel.tolak');
        Route::post('/panel/artikel/{artikel}/tarik', [ArtikelController::class, 'tarikKembali'])->name('panel.artikel.tarik');
    });

    Route::middleware('permission:articles.publish')->group(function () {
        Route::post('/panel/artikel/{artikel}/terbitkan', [ArtikelController::class, 'terbitkan'])->name('panel.artikel.terbitkan');
    });

    /*
     * Berita acara diterbitkan lewat rute TERPISAH dengan izin tersendiri.
     * Alasannya: Sekretaris berhak menerbitkan berita acara, tetapi TIDAK boleh
     * menerbitkan artikel biasa (itu wewenang Konten Manager setelah review).
     */
    Route::middleware('permission:articles.publish-berita-acara')->group(function () {
        Route::post('/panel/artikel/{artikel}/terbitkan-berita-acara', [ArtikelController::class, 'terbitkanBeritaAcara'])
            ->name('panel.artikel.terbitkan-berita-acara');
    });

    Route::middleware('permission:articles.feature')->group(function () {
        Route::post('/panel/artikel/{artikel}/unggulan', [ArtikelController::class, 'unggulan'])->name('panel.artikel.unggulan');
    });

    Route::middleware('permission:translations.manage')->group(function () {
        Route::post('/panel/artikel/{artikel}/terjemahkan', [ArtikelController::class, 'terjemahkan'])->name('panel.artikel.terjemahkan');
    });

    // Berita acara dapat diunduh sebagai PDF (dokumen administratif).
    Route::middleware('permission:articles.view')->group(function () {
        Route::get('/panel/artikel/{artikel}/pdf', [ArtikelController::class, 'unduhPdf'])->name('panel.artikel.pdf');
    });

    Route::middleware('permission:articles.delete')->group(function () {
        Route::delete('/panel/artikel/{artikel}', [ArtikelController::class, 'hapus'])->name('panel.artikel.hapus');
    });
});

/*
|--------------------------------------------------------------------------
| Panel: Organisasi (Fase 4)
|--------------------------------------------------------------------------
| Periode kepengurusan hanya boleh diubah Superadmin (izin `periods.create`
| dan kawan-kawan). Sekretaris mengelola jabatan, penugasan, dan unit.
| Galeri & agenda unit dipegang Konten Manager.
*/
Route::middleware(['auth', 'verified'])->prefix('panel/organisasi')->name('panel.organisasi.')->group(function () {
    Route::middleware('permission:periods.view')->group(function () {
        Route::get('/periode', [PeriodeController::class, 'index'])->name('periode');
    });

    Route::middleware('permission:periods.create')->group(function () {
        Route::post('/periode', [PeriodeController::class, 'simpan'])->name('periode.simpan');
    });

    Route::middleware('permission:periods.update')->group(function () {
        Route::put('/periode/{periode}', [PeriodeController::class, 'perbarui'])->name('periode.perbarui');
    });

    Route::middleware('permission:periods.activate')->group(function () {
        Route::post('/periode/{periode}/aktifkan', [PeriodeController::class, 'aktifkan'])->name('periode.aktifkan');
    });

    Route::middleware('permission:periods.delete')->group(function () {
        Route::delete('/periode/{periode}', [PeriodeController::class, 'hapus'])->name('periode.hapus');
    });

    Route::middleware('permission:positions.view')->group(function () {
        Route::get('/jabatan', [JabatanController::class, 'index'])->name('jabatan');
    });

    Route::middleware('permission:positions.manage')->group(function () {
        Route::post('/jabatan', [JabatanController::class, 'simpan'])->name('jabatan.simpan');
        Route::put('/jabatan/{jabatan}', [JabatanController::class, 'perbarui'])->name('jabatan.perbarui');
        Route::delete('/jabatan/{jabatan}', [JabatanController::class, 'hapus'])->name('jabatan.hapus');
    });

    Route::middleware('permission:assignments.manage')->group(function () {
        Route::get('/penugasan', [PenugasanController::class, 'index'])->name('penugasan');
        Route::post('/penugasan', [PenugasanController::class, 'simpan'])->name('penugasan.simpan');
        Route::put('/penugasan/{penugasan}', [PenugasanController::class, 'perbarui'])->name('penugasan.perbarui');
        Route::delete('/penugasan/{penugasan}', [PenugasanController::class, 'hapus'])->name('penugasan.hapus');
    });

    Route::middleware('permission:units.view')->group(function () {
        Route::get('/unit', [UnitController::class, 'index'])->name('unit');

        // Kelola SATU unit: pengurus, anggota, dan agenda (khusus LSO).
        Route::get('/unit/{unit}', [UnitController::class, 'detail'])->name('unit.detail');
    });

    Route::middleware('permission:units.create')->group(function () {
        Route::post('/unit', [UnitController::class, 'simpan'])->name('unit.simpan');
    });

    /*
     * Keanggotaan unit diatur dari halaman kelola unit.
     *
     * Izinnya `members.update`, BUKAN `units.update`: yang berubah adalah data
     * anggota, dan Sekretaris sudah memegang izin itu. Memakai izin unit akan
     * membuat Sekretaris bisa menyunting unit tetapi tidak bisa mengisinya.
     */
    Route::middleware('permission:members.update')->group(function () {
        Route::post('/unit/{unit}/anggota', [UnitController::class, 'tambahAnggota'])->name('unit.anggota.tambah');
        Route::delete('/unit/{unit}/anggota/{anggota}', [UnitController::class, 'lepasAnggota'])->name('unit.anggota.lepas');
    });

    Route::middleware('permission:units.update')->group(function () {
        Route::put('/unit/{unit}', [UnitController::class, 'perbarui'])->name('unit.perbarui');
    });

    Route::middleware('permission:units.delete')->group(function () {
        Route::delete('/unit/{unit}', [UnitController::class, 'hapus'])->name('unit.hapus');
    });

    Route::middleware('permission:galleries.manage')->group(function () {
        Route::get('/galeri', [GaleriController::class, 'index'])->name('galeri');
        Route::post('/galeri', [GaleriController::class, 'simpanAlbum'])->name('galeri.simpan');
        Route::put('/galeri/{album}', [GaleriController::class, 'perbaruiAlbum'])->name('galeri.perbarui');
        Route::delete('/galeri/{album}', [GaleriController::class, 'hapusAlbum'])->name('galeri.hapus');
        Route::post('/galeri/{album}/foto', [GaleriController::class, 'simpanFoto'])->name('galeri.foto.simpan');
        Route::delete('/galeri/foto/{foto}', [GaleriController::class, 'hapusFoto'])->name('galeri.foto.hapus');
    });

    Route::middleware('permission:unit-agendas.manage')->group(function () {
        Route::post('/agenda', [GaleriController::class, 'simpanAgenda'])->name('agenda.simpan');
        Route::put('/agenda/{agenda}', [GaleriController::class, 'perbaruiAgenda'])->name('agenda.perbarui');
        Route::delete('/agenda/{agenda}', [GaleriController::class, 'hapusAgenda'])->name('agenda.hapus');
    });

    Route::middleware('permission:mentors.view')->group(function () {
        Route::get('/mentor', [MentorController::class, 'index'])->name('mentor');
    });
});
/*
|--------------------------------------------------------------------------
| Panel: Event Mapaba & PKD (Fase 5)
|--------------------------------------------------------------------------
| Sekretaris mengelola event beserta pesertanya; Konten Manager tidak, karena
| data pendaftar memuat informasi pribadi.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:events.view')->group(function () {
        Route::get('/panel/event', [EventController::class, 'index'])->name('panel.event');
    });

    Route::middleware('permission:events.create')->group(function () {
        Route::post('/panel/event', [EventController::class, 'simpan'])->name('panel.event.simpan');
    });

    Route::middleware('permission:events.update')->group(function () {
        Route::put('/panel/event/{event}', [EventController::class, 'perbarui'])->name('panel.event.perbarui');
        Route::post('/panel/event/{event}/kolom', [EventController::class, 'simpanKolom'])->name('panel.event.kolom.simpan');
        Route::put('/panel/event/kolom/{kolom}', [EventController::class, 'perbaruiKolom'])->name('panel.event.kolom.perbarui');
        Route::delete('/panel/event/kolom/{kolom}', [EventController::class, 'hapusKolom'])->name('panel.event.kolom.hapus');
    });

    Route::middleware('permission:events.delete')->group(function () {
        Route::delete('/panel/event/{event}', [EventController::class, 'hapus'])->name('panel.event.hapus');
    });

    Route::middleware('permission:registrations.view')->group(function () {
        Route::get('/panel/peserta', [PesertaController::class, 'index'])->name('panel.peserta');
        Route::get('/panel/peserta/{peserta}/kartu', [PesertaController::class, 'kartu'])->name('panel.peserta.kartu');
    });

    Route::middleware('permission:registrations.verify')->group(function () {
        Route::post('/panel/peserta/{peserta}/verifikasi', [PesertaController::class, 'verifikasi'])->name('panel.peserta.verifikasi');
        Route::post('/panel/peserta/{peserta}/catatan', [PesertaController::class, 'catatan'])->name('panel.peserta.catatan');
        Route::post('/panel/peserta/{peserta}/batalkan', [PesertaController::class, 'batalkan'])->name('panel.peserta.batalkan');
    });

    Route::middleware('permission:registrations.reject')->group(function () {
        Route::post('/panel/peserta/{peserta}/tolak', [PesertaController::class, 'tolak'])->name('panel.peserta.tolak');
    });

    Route::middleware('permission:registrations.mark-attendance')->group(function () {
        Route::post('/panel/peserta/{peserta}/hadir', [PesertaController::class, 'hadir'])->name('panel.peserta.hadir');
    });

    Route::middleware('permission:registrations.export')->group(function () {
        Route::get('/panel/event/{event}/ekspor', [PesertaController::class, 'ekspor'])->name('panel.event.ekspor');
    });

    Route::middleware('permission:events.promote-to-member')->group(function () {
        Route::post('/panel/peserta/{peserta}/promosikan', [PesertaController::class, 'promosikan'])->name('panel.peserta.promosikan');
    });
});

/*
|--------------------------------------------------------------------------
| Panel: Kegiatan & Presensi (Fase 8)
|--------------------------------------------------------------------------
| Mempersiapkan kegiatan (Sekretaris) dan mencatat kehadiran (panitia) adalah
| dua pekerjaan berbeda, jadi izinnya juga dipisah: `activities.*` untuk
| menyusun agenda, `attendances.*` untuk menyentuh daftar hadir.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:activities.view')->group(function () {
        Route::get('/panel/kegiatan', [KegiatanController::class, 'index'])->name('panel.kegiatan');
    });

    Route::middleware('permission:attendances.view')->group(function () {
        Route::get('/panel/kegiatan/{kegiatan}/qr', [KegiatanController::class, 'qr'])->name('panel.kegiatan.qr');
        Route::get('/panel/presensi', [PresensiPanelController::class, 'index'])->name('panel.presensi');
        Route::get('/panel/presensi/{kegiatan}/ekspor', [PresensiPanelController::class, 'ekspor'])->name('panel.presensi.ekspor');
    });

    Route::middleware('permission:activities.manage')->group(function () {
        Route::post('/panel/kegiatan', [KegiatanController::class, 'simpan'])->name('panel.kegiatan.simpan');
        Route::put('/panel/kegiatan/{kegiatan}', [KegiatanController::class, 'perbarui'])->name('panel.kegiatan.perbarui');
    });

    Route::middleware('permission:attendances.open-qr')->group(function () {
        Route::post('/panel/kegiatan/{kegiatan}/buka', [KegiatanController::class, 'buka'])->name('panel.kegiatan.buka');
        Route::post('/panel/kegiatan/{kegiatan}/putar-qr', [KegiatanController::class, 'putarQr'])->name('panel.kegiatan.putar-qr');
    });

    Route::middleware('permission:attendances.manage')->group(function () {
        Route::post('/panel/kegiatan/{kegiatan}/tutup', [KegiatanController::class, 'tutup'])->name('panel.kegiatan.tutup');
        Route::post('/panel/kegiatan/{kegiatan}/batalkan', [KegiatanController::class, 'batalkan'])->name('panel.kegiatan.batalkan');
        Route::post('/panel/presensi/{kegiatan}/anggota/{anggota}', [PresensiPanelController::class, 'catat'])->name('panel.presensi.catat');
        Route::post('/panel/presensi/{kegiatan}/massal', [PresensiPanelController::class, 'catatMassal'])->name('panel.presensi.massal');
        Route::post('/panel/presensi/{kegiatan}/sisa', [PresensiPanelController::class, 'tandaiSisa'])->name('panel.presensi.sisa');
    });

    /*
     * Poin kontribusi.
     *
     * Melihat buku besar dan MENYENTUH poin adalah dua wewenang berbeda:
     * penyesuaian manual mengubah papan peringkat, jadi izinnya dipisah.
     */
    Route::middleware('permission:points.view')->group(function () {
        Route::get('/panel/kontribusi', [KontribusiController::class, 'index'])->name('panel.kontribusi');
        Route::get('/panel/kontribusi/ekspor', [KontribusiController::class, 'ekspor'])->name('panel.kontribusi.ekspor');
    });

    Route::middleware('permission:points.adjust')->group(function () {
        Route::post('/panel/kontribusi/penyesuaian', [KontribusiController::class, 'sesuaikan'])->name('panel.kontribusi.sesuaikan');
        Route::post('/panel/kontribusi/{poin}/batalkan', [KontribusiController::class, 'batalkan'])->name('panel.kontribusi.batalkan');
        Route::post('/panel/kontribusi/kegiatan/{kegiatan}/sinkronkan', [KontribusiController::class, 'sinkronkan'])->name('panel.kontribusi.sinkronkan');
    });

    /*
     * Prestasi kader.
     *
     * URUTAN PENTING: rute `kategori` didaftarkan SEBELUM `{prestasi}`, kalau
     * tidak DELETE /panel/prestasi/kategori akan tertangkap sebagai hapus
     * prestasi dengan id "kategori".
     */
    Route::middleware('permission:achievement-categories.manage')->group(function () {
        Route::post('/panel/prestasi/kategori', [PrestasiPanelController::class, 'simpanKategori'])->name('panel.prestasi.kategori.simpan');
        Route::put('/panel/prestasi/kategori/{kategori}', [PrestasiPanelController::class, 'perbaruiKategori'])->name('panel.prestasi.kategori.perbarui');
        Route::delete('/panel/prestasi/kategori/{kategori}', [PrestasiPanelController::class, 'hapusKategori'])->name('panel.prestasi.kategori.hapus');
    });

    Route::middleware('permission:achievements.view')->group(function () {
        Route::get('/panel/prestasi', [PrestasiPanelController::class, 'index'])->name('panel.prestasi');
        Route::get('/panel/prestasi/ekspor', [PrestasiPanelController::class, 'ekspor'])->name('panel.prestasi.ekspor');
    });

    Route::middleware('permission:achievements.verify')->group(function () {
        Route::post('/panel/prestasi/{prestasi}/verifikasi', [PrestasiPanelController::class, 'verifikasi'])->name('panel.prestasi.verifikasi');
    });

    Route::middleware('permission:achievements.reject')->group(function () {
        Route::post('/panel/prestasi/{prestasi}/tolak', [PrestasiPanelController::class, 'tolak'])->name('panel.prestasi.tolak');
    });

    Route::middleware('permission:achievements.feature')->group(function () {
        Route::post('/panel/prestasi/{prestasi}/unggulan', [PrestasiPanelController::class, 'unggulan'])->name('panel.prestasi.unggulan');
    });

    // Hapus hanya berlaku untuk pengajuan yang BELUM diperiksa.
    Route::middleware('permission:achievements.delete')->group(function () {
        Route::delete('/panel/prestasi/{prestasi}', [PrestasiPanelController::class, 'hapus'])->name('panel.prestasi.hapus');
    });

    /*
     * Aspirasi.
     *
     * Izinnya dipisah per tindakan: membaca, menjawab, menutup, menayangkan,
     * dan — yang paling penting — MELIHAT IDENTITAS pengirim. Seorang pengurus
     * boleh perlu membaca isi aspirasi tanpa perlu tahu siapa pengirimnya.
     */
    Route::middleware('permission:aspirations.view')->group(function () {
        Route::get('/panel/aspirasi', [AspirasiPanelController::class, 'index'])->name('panel.aspirasi');
        Route::get('/panel/aspirasi/ekspor', [AspirasiPanelController::class, 'ekspor'])->name('panel.aspirasi.ekspor');
        Route::post('/panel/aspirasi/{aspirasi}/baca', [AspirasiPanelController::class, 'baca'])->name('panel.aspirasi.baca');
    });

    Route::middleware('permission:aspirations.reply')->group(function () {
        Route::post('/panel/aspirasi/{aspirasi}/tanggapi', [AspirasiPanelController::class, 'tanggapi'])->name('panel.aspirasi.tanggapi');
    });

    Route::middleware('permission:aspirations.close')->group(function () {
        Route::post('/panel/aspirasi/{aspirasi}/tutup', [AspirasiPanelController::class, 'tutup'])->name('panel.aspirasi.tutup');
    });

    Route::middleware('permission:aspirations.publish')->group(function () {
        Route::post('/panel/aspirasi/{aspirasi}/tampil', [AspirasiPanelController::class, 'tampil'])->name('panel.aspirasi.tampil');
    });

    /*
     * Pengumuman & arsip dokumen.
     *
     * Membaca dan mengubah dipisah: Bendahara memegang `documents.view` tanpa
     * `documents.manage` karena ia perlu membaca AD/ART dan notulen rapat yang
     * menyangkut anggaran, tetapi tidak perlu mengubah arsipnya.
     */
    Route::middleware('permission:announcements.view')->group(function () {
        Route::get('/panel/pengumuman', [PengumumanPanelController::class, 'index'])->name('panel.pengumuman');
        Route::get('/panel/pengumuman/ekspor', [PengumumanPanelController::class, 'ekspor'])->name('panel.pengumuman.ekspor');
    });

    Route::middleware('permission:announcements.manage')->group(function () {
        Route::post('/panel/pengumuman', [PengumumanPanelController::class, 'simpan'])->name('panel.pengumuman.simpan');
        Route::post('/panel/pengumuman/{pengumuman}', [PengumumanPanelController::class, 'perbarui'])->name('panel.pengumuman.perbarui');
        Route::post('/panel/pengumuman/{pengumuman}/sematkan', [PengumumanPanelController::class, 'sematkan'])->name('panel.pengumuman.sematkan');
        Route::delete('/panel/pengumuman/{pengumuman}', [PengumumanPanelController::class, 'hapus'])->name('panel.pengumuman.hapus');
    });

    Route::middleware('permission:documents.view')->group(function () {
        Route::get('/panel/arsip', [ArsipPanelController::class, 'index'])->name('panel.arsip');
        Route::get('/panel/arsip/ekspor', [ArsipPanelController::class, 'ekspor'])->name('panel.arsip.ekspor');
    });

    Route::middleware('permission:documents.manage')->group(function () {
        Route::post('/panel/arsip', [ArsipPanelController::class, 'simpan'])->name('panel.arsip.simpan');
        Route::post('/panel/arsip/{dokumen}', [ArsipPanelController::class, 'perbarui'])->name('panel.arsip.perbarui');
        Route::delete('/panel/arsip/{dokumen}', [ArsipPanelController::class, 'hapus'])->name('panel.arsip.hapus');
    });

    /*
     * Laporan lintas modul.
     *
     * Izin datanya diperiksa DI DALAM pengendali juga, bukan hanya di sini:
     * sebuah laporan tidak boleh menjadi pintu belakang menuju data yang di
     * modul aslinya sengaja dibatasi.
     */
    Route::middleware('permission:reports.generate')->group(function () {
        Route::get('/panel/laporan', [LaporanController::class, 'index'])->name('panel.laporan');
    });

    Route::middleware('permission:reports.export')->group(function () {
        Route::get('/panel/laporan/{jenis}/ekspor', [LaporanController::class, 'ekspor'])->name('panel.laporan.ekspor');
    });

    /*
     * Cadangan basis data — KHUSUS SUPERADMIN.
     *
     * Dibatasi lewat peran, bukan izin tersendiri, karena berkasnya memuat
     * SELURUH isi basis data: data pribadi anggota, bukti pembayaran, dan hash
     * kata sandi. Tidak ada peran lain yang pantas menyentuhnya.
     */
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/panel/cadangan', [CadanganController::class, 'index'])->name('panel.cadangan');
        Route::post('/panel/cadangan', [CadanganController::class, 'buat'])->name('panel.cadangan.buat');
        Route::get('/panel/cadangan/{berkas}/unduh', [CadanganController::class, 'unduh'])->name('panel.cadangan.unduh');
        Route::delete('/panel/cadangan/{berkas}', [CadanganController::class, 'hapus'])->name('panel.cadangan.hapus');
    });

    /*
     * Diagnostik — juga khusus Superadmin.
     *
     * Yang ditampilkan hanyalah NAMA pengandar dan status terkonfigurasi;
     * tidak ada kata sandi SMTP atau kunci aplikasi di dalamnya.
     */
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/panel/diagnostik', [DiagnostikController::class, 'index'])->name('panel.diagnostik');
    });
});

/*
|--------------------------------------------------------------------------
| Panel: Inventaris & Perpustakaan (Fase 6)
|--------------------------------------------------------------------------
| Jumlah aset tidak dapat disunting langsung — hanya lewat mutasi — karena
| setiap perubahan stok harus punya jejak dan penanggung jawab.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:inventory.items.view')->group(function () {
        Route::get('/panel/inventaris', [InventarisPanelController::class, 'index'])->name('panel.inventaris');
        Route::get('/panel/inventaris/kategori', [InventarisPanelController::class, 'index'])->name('panel.inventaris.kategori');
    });

    Route::middleware('permission:inventory.categories.manage')->group(function () {
        Route::post('/panel/inventaris/kategori', [InventarisPanelController::class, 'simpanKategori'])->name('panel.inventaris.kategori.simpan');
        Route::put('/panel/inventaris/kategori/{kategori}', [InventarisPanelController::class, 'perbaruiKategori'])->name('panel.inventaris.kategori.perbarui');
        Route::delete('/panel/inventaris/kategori/{kategori}', [InventarisPanelController::class, 'hapusKategori'])->name('panel.inventaris.kategori.hapus');
    });

    Route::middleware('permission:inventory.items.manage')->group(function () {
        Route::post('/panel/inventaris', [InventarisPanelController::class, 'simpan'])->name('panel.inventaris.simpan');
        Route::put('/panel/inventaris/{aset}', [InventarisPanelController::class, 'perbarui'])->name('panel.inventaris.perbarui');
        Route::delete('/panel/inventaris/{aset}', [InventarisPanelController::class, 'hapus'])->name('panel.inventaris.hapus');
    });

    Route::middleware('permission:inventory.movements.manage')->group(function () {
        Route::post('/panel/inventaris/{aset}/mutasi', [InventarisPanelController::class, 'catatMutasi'])->name('panel.inventaris.mutasi');
    });

    Route::middleware('permission:library.books.view')->group(function () {
        Route::get('/panel/perpustakaan', [PerpustakaanPanelController::class, 'index'])->name('panel.perpustakaan');
    });

    Route::middleware('permission:library.books.manage')->group(function () {
        Route::post('/panel/perpustakaan', [PerpustakaanPanelController::class, 'simpan'])->name('panel.perpustakaan.simpan');
        Route::put('/panel/perpustakaan/{buku}', [PerpustakaanPanelController::class, 'perbarui'])->name('panel.perpustakaan.perbarui');
        Route::delete('/panel/perpustakaan/{buku}', [PerpustakaanPanelController::class, 'hapus'])->name('panel.perpustakaan.hapus');
    });

    Route::middleware('permission:library.copies.manage')->group(function () {
        Route::post('/panel/perpustakaan/{buku}/eksemplar', [PerpustakaanPanelController::class, 'simpanEksemplar'])->name('panel.perpustakaan.eksemplar.simpan');
        Route::put('/panel/perpustakaan/eksemplar/{eksemplar}', [PerpustakaanPanelController::class, 'perbaruiEksemplar'])->name('panel.perpustakaan.eksemplar.perbarui');
        Route::delete('/panel/perpustakaan/eksemplar/{eksemplar}', [PerpustakaanPanelController::class, 'hapusEksemplar'])->name('panel.perpustakaan.eksemplar.hapus');
    });

    Route::middleware('permission:loans.view')->group(function () {
        Route::get('/panel/peminjaman', [PeminjamanController::class, 'index'])->name('panel.peminjaman');
    });

    Route::middleware('permission:loans.request-for-others')->group(function () {
        Route::post('/panel/peminjaman/eksternal', [PeminjamanController::class, 'simpanEksternal'])->name('panel.peminjaman.eksternal');
    });

    Route::middleware('permission:loans.approve')->group(function () {
        Route::post('/panel/peminjaman/{pinjaman}/setujui', [PeminjamanController::class, 'setujui'])->name('panel.peminjaman.setujui');
    });

    Route::middleware('permission:loans.reject')->group(function () {
        Route::post('/panel/peminjaman/{pinjaman}/tolak', [PeminjamanController::class, 'tolak'])->name('panel.peminjaman.tolak');
    });

    Route::middleware('permission:loans.handover')->group(function () {
        Route::post('/panel/peminjaman/{pinjaman}/serahkan', [PeminjamanController::class, 'serahkan'])->name('panel.peminjaman.serahkan');
    });

    Route::middleware('permission:loans.receive-return')->group(function () {
        Route::post('/panel/peminjaman/{pinjaman}/kembalikan', [PeminjamanController::class, 'kembalikan'])->name('panel.peminjaman.kembalikan');
    });

    Route::middleware('permission:loans.extend-approve')->group(function () {
        Route::post('/panel/peminjaman/perpanjangan/{perpanjangan}/setujui', [PeminjamanController::class, 'setujuiPerpanjangan'])->name('panel.peminjaman.perpanjangan.setujui');
        Route::post('/panel/peminjaman/perpanjangan/{perpanjangan}/tolak', [PeminjamanController::class, 'tolakPerpanjangan'])->name('panel.peminjaman.perpanjangan.tolak');
    });

    Route::middleware('permission:loans.manage')->group(function () {
        Route::post('/panel/peminjaman/{pinjaman}/batalkan', [PeminjamanController::class, 'batalkan'])->name('panel.peminjaman.batalkan');
    });

    /*
    |----------------------------------------------------------------------
    | Fase 7 — Keuangan (internal)
    |----------------------------------------------------------------------
    | Seluruh halaman keuangan INTERNAL: tidak ada satu pun rute publik.
    | Hanya Bendahara dan Superadmin yang memegang izin di bawah ini, jadi
    | role lain tidak akan menemukan pintunya sama sekali.
    |
    | Tidak ada rute DELETE untuk transaksi — pembatalan lewat void beralasan.
    */
    Route::middleware('permission:finance.transactions.view')->group(function () {
        Route::get('/panel/keuangan', [KeuanganController::class, 'index'])->name('panel.keuangan');
        Route::get('/panel/keuangan/transaksi', [TransaksiController::class, 'index'])->name('panel.keuangan.transaksi');
        Route::get('/panel/keuangan/akun', [KeuanganController::class, 'akun'])->name('panel.keuangan.akun');
    });

    Route::middleware('permission:finance.categories.manage')->group(function () {
        Route::get('/panel/keuangan/kategori', [KeuanganKategoriController::class, 'index'])->name('panel.keuangan.kategori');
        Route::post('/panel/keuangan/kategori', [KeuanganKategoriController::class, 'simpan'])->name('panel.keuangan.kategori.simpan');
        Route::put('/panel/keuangan/kategori/{kategori}', [KeuanganKategoriController::class, 'perbarui'])->name('panel.keuangan.kategori.perbarui');
        Route::delete('/panel/keuangan/kategori/{kategori}', [KeuanganKategoriController::class, 'hapus'])->name('panel.keuangan.kategori.hapus');
    });

    Route::middleware('permission:finance.accounts.manage')->group(function () {
        Route::post('/panel/keuangan/akun', [KeuanganController::class, 'simpanAkun'])->name('panel.keuangan.akun.simpan');
        Route::put('/panel/keuangan/akun/{akun}', [KeuanganController::class, 'perbaruiAkun'])->name('panel.keuangan.akun.perbarui');
        Route::delete('/panel/keuangan/akun/{akun}', [KeuanganController::class, 'hapusAkun'])->name('panel.keuangan.akun.hapus');
    });

    Route::middleware('permission:finance.transactions.manage')->group(function () {
        Route::post('/panel/keuangan/transaksi', [TransaksiController::class, 'simpan'])->name('panel.keuangan.transaksi.simpan');
    });

    Route::middleware('permission:finance.transactions.verify')->group(function () {
        Route::post('/panel/keuangan/transaksi/{transaksi}/konfirmasi', [TransaksiController::class, 'konfirmasi'])->name('panel.keuangan.transaksi.konfirmasi');
    });

    // Void adalah wewenang tersendiri: mengoreksi saldo yang sudah tercatat.
    Route::middleware('permission:finance.void')->group(function () {
        Route::post('/panel/keuangan/transaksi/{transaksi}/void', [TransaksiController::class, 'void'])->name('panel.keuangan.transaksi.void');
    });

    Route::middleware('permission:dues.manage')->group(function () {
        Route::get('/panel/keuangan/iuran', [IuranController::class, 'index'])->name('panel.keuangan.iuran');
        Route::post('/panel/keuangan/iuran/kategori', [IuranController::class, 'simpanKategori'])->name('panel.keuangan.iuran.kategori.simpan');
        Route::put('/panel/keuangan/iuran/kategori/{kategori}', [IuranController::class, 'perbaruiKategori'])->name('panel.keuangan.iuran.kategori.perbarui');
        Route::post('/panel/keuangan/iuran/kategori/{kategori}/terbitkan', [IuranController::class, 'terbitkan'])->name('panel.keuangan.iuran.terbitkan');
        Route::post('/panel/keuangan/iuran/kategori/{kategori}/ingatkan', [IuranController::class, 'ingatkan'])->name('panel.keuangan.iuran.ingatkan');
        Route::post('/panel/keuangan/iuran/tagihan/{tagihan}/tunai', [IuranController::class, 'catatTunai'])->name('panel.keuangan.iuran.tunai');
        Route::post('/panel/keuangan/iuran/tagihan/{tagihan}/bebaskan', [IuranController::class, 'bebaskan'])->name('panel.keuangan.iuran.bebaskan');
    });

    Route::middleware('permission:dues.payments.verify')->group(function () {
        Route::post('/panel/keuangan/iuran/pembayaran/{pembayaran}/verifikasi', [IuranController::class, 'verifikasi'])->name('panel.keuangan.iuran.verifikasi');
        Route::post('/panel/keuangan/iuran/pembayaran/{pembayaran}/tolak', [IuranController::class, 'tolakPembayaran'])->name('panel.keuangan.iuran.tolak');
    });

    Route::middleware('permission:budgets.manage')->group(function () {
        Route::get('/panel/keuangan/anggaran', [AnggaranController::class, 'index'])->name('panel.keuangan.anggaran');
        Route::post('/panel/keuangan/anggaran', [AnggaranController::class, 'simpan'])->name('panel.keuangan.anggaran.simpan');
        Route::put('/panel/keuangan/anggaran/{anggaran}', [AnggaranController::class, 'perbarui'])->name('panel.keuangan.anggaran.perbarui');
        Route::delete('/panel/keuangan/anggaran/{anggaran}', [AnggaranController::class, 'hapus'])->name('panel.keuangan.anggaran.hapus');
    });

    Route::middleware('permission:finance.reports.view')->group(function () {
        Route::get('/panel/keuangan/laporan', [LaporanKeuanganController::class, 'index'])->name('panel.keuangan.laporan');
    });

    Route::middleware('permission:finance.export')->group(function () {
        Route::get('/panel/keuangan/laporan/ekspor', [LaporanKeuanganController::class, 'ekspor'])->name('panel.keuangan.laporan.ekspor');
    });

    Route::middleware('permission:donations.view')->group(function () {
        Route::get('/panel/keuangan/hibah', [HibahController::class, 'index'])->name('panel.keuangan.hibah');
    });

    Route::middleware('permission:donations.manage')->group(function () {
        Route::post('/panel/keuangan/hibah', [HibahController::class, 'simpan'])->name('panel.keuangan.hibah.simpan');
        Route::post('/panel/keuangan/hibah/{hibah}/setujui', [HibahController::class, 'setujui'])->name('panel.keuangan.hibah.setujui');
        Route::post('/panel/keuangan/hibah/{hibah}/tolak', [HibahController::class, 'tolak'])->name('panel.keuangan.hibah.tolak');
        Route::post('/panel/keuangan/hibah/{hibah}/terima', [HibahController::class, 'terima'])->name('panel.keuangan.hibah.terima');
        Route::post('/panel/keuangan/hibah/{hibah}/batalkan', [HibahController::class, 'batalkan'])->name('panel.keuangan.hibah.batalkan');
    });

    Route::middleware('permission:donations.export')->group(function () {
        Route::get('/panel/keuangan/hibah/ekspor', [HibahController::class, 'ekspor'])->name('panel.keuangan.hibah.ekspor');
    });
});
/*
|--------------------------------------------------------------------------
| Area Anggota (kader & alumni)
|--------------------------------------------------------------------------
| Bukan area pengurus, sehingga tidak memerlukan izin apa pun — cukup sudah
| masuk dan sudah memverifikasi email. Halaman ini memakai Inertia + Vue
| karena bersifat interaktif (formulir profil, pengaturan privasi).
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dasbor', [DasborAnggotaController::class, 'index'])->name('anggota.dasbor');

    Route::get('/kegiatan', [KegiatanAnggotaController::class, 'index'])->name('anggota.kegiatan');
    Route::post('/kegiatan/{kegiatan}/rsvp', [KegiatanAnggotaController::class, 'rsvp'])->name('anggota.kegiatan.rsvp');

    /*
     * Poin kontribusi milik sendiri.
     *
     * Tanpa izin Spatie: yang menentukan hanya kepemilikan datanya. Papan
     * peringkat lengkap tidak ditampilkan di area anggota — nama kader beserta
     * keaktifannya adalah data internal rayon.
     */
    Route::get('/kontribusi', [KontribusiAnggotaController::class, 'index'])->name('anggota.kontribusi');

    /*
     * Prestasi saya.
     *
     * Alamatnya `/prestasi-saya`, bukan `/prestasi`, supaya tidak bertabrakan
     * dengan halaman publik `/prestasi`.
     */
    Route::get('/prestasi-saya', [PrestasiAnggotaController::class, 'index'])->name('anggota.prestasi');
    Route::post('/prestasi-saya', [PrestasiAnggotaController::class, 'ajukan'])->name('anggota.prestasi.ajukan');
    Route::put('/prestasi-saya/{prestasi}', [PrestasiAnggotaController::class, 'perbarui'])->name('anggota.prestasi.perbarui');
    Route::post('/prestasi-saya/{prestasi}/tampil', [PrestasiAnggotaController::class, 'tampil'])->name('anggota.prestasi.tampil');
    Route::delete('/prestasi-saya/{prestasi}', [PrestasiAnggotaController::class, 'hapus'])->name('anggota.prestasi.hapus');

    /*
     * Pengumuman & arsip di area anggota.
     *
     * Alamatnya diberi akhiran "-saya"/"-internal" supaya tidak bertabrakan
     * dengan halaman publik `/pengumuman` dan `/arsip`.
     */
    Route::get('/pengumuman-internal', [PengumumanAnggotaController::class, 'index'])->name('anggota.pengumuman');
    Route::get('/arsip-internal', [ArsipAnggotaController::class, 'index'])->name('anggota.arsip');

    /*
     * Pusat notifikasi.
     *
     * SATU alamat untuk pengurus maupun kader — halamannya memilih sendiri
     * kerangka panel atau kerangka area anggota. Notifikasi adalah milik orang,
     * bukan milik peran, dan memaksanya masuk ke salah satu kerangka hanya akan
     * membuat pengurus yang sekaligus kader melihatnya dua tempat.
     */
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::post('/notifikasi/preferensi', [NotifikasiController::class, 'preferensi'])->name('notifikasi.preferensi');
    Route::post('/notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'baca'])->name('notifikasi.baca');

    /*
     * Pemindaian QR presensi.
     *
     * Tautan ini yang dibuka kamera ponsel saat kader memindai kode di
     * proyektor. Halaman hasilnya berdiri sendiri (bukan Inertia) supaya bisa
     * dibuka cepat dari aplikasi kamera. Throttle menahan percobaan menebak
     * token; tokennya sendiri 48 karakter acak.
     */
    Route::get('/presensi/scan/{token}', [PresensiAnggotaController::class, 'scan'])
        ->middleware('throttle:30,1')
        ->name('anggota.presensi.scan');

    /*
     * Kartu kader digital milik sendiri.
     *
     * Tanpa izin Spatie — yang menentukan hanya kepemilikan kartu itu sendiri,
     * dan halaman ini tidak menampilkan data anggota lain.
     */
    Route::get('/kartu-kader', KartuAnggotaController::class)->name('anggota.kartu');

    /*
     * Pustaka untuk kader & alumni.
     *
     * Tanpa izin Spatie — yang menentukan adalah kepemilikan pinjaman itu
     * sendiri. Pinjaman selalu dicatat atas nama anggota milik pengguna yang
     * sedang masuk, jadi tidak ada yang dapat meminjam atas nama orang lain.
     */
    Route::get('/pustaka', [PustakaAnggotaController::class, 'index'])->name('anggota.pustaka');
    Route::post('/pustaka/pinjam', [PustakaAnggotaController::class, 'pinjam'])->name('anggota.pustaka.pinjam');
    Route::post('/pustaka/antri', [PustakaAnggotaController::class, 'antri'])->name('anggota.pustaka.antri');
    Route::post('/pustaka/{pinjaman}/perpanjang', [PustakaAnggotaController::class, 'perpanjang'])->name('anggota.pustaka.perpanjang');
    Route::post('/pustaka/{pinjaman}/batalkan', [PustakaAnggotaController::class, 'batalkan'])->name('anggota.pustaka.batalkan');
    Route::get('/profil', [DasborAnggotaController::class, 'profil'])->name('anggota.profil');
    Route::put('/profil', [DasborAnggotaController::class, 'perbaruiProfil'])->name('anggota.profil.perbarui');

    /*
     * Hibah & dukungan alumni.
     *
     * Tanpa izin Spatie: yang menentukan adalah kepemilikan pengajuan itu
     * sendiri. Halaman ini hanya MEMBUAT pengajuan — penerimaan dan
     * pencatatannya tetap di tangan Bendahara.
     */
    Route::get('/hibah', [HibahAnggotaController::class, 'index'])->name('anggota.hibah');
    Route::post('/hibah', [HibahAnggotaController::class, 'ajukan'])->name('anggota.hibah.ajukan');
    Route::post('/hibah/{hibah}/batalkan', [HibahAnggotaController::class, 'batalkan'])->name('anggota.hibah.batalkan');

    /*
     * Iuran saya.
     *
     * Tanpa izin Spatie: yang menentukan adalah kepemilikan tagihannya sendiri.
     * Anggota hanya boleh MENGIRIM bukti; yang mengubah saldo kas adalah
     * verifikasi Bendahara.
     */
    Route::get('/iuran', [IuranAnggotaController::class, 'index'])->name('anggota.iuran');
    Route::post('/iuran/{tagihan}/bukti', [IuranAnggotaController::class, 'kirimBukti'])->name('anggota.iuran.bukti');

    /*
     * Submisi karya oleh anggota.
     * Tidak memakai izin Spatie karena kader/alumni tidak memegang peran;
     * syaratnya adalah status keanggotaan yang sudah diverifikasi (lihat
     * KaryaController::pastikanAnggota).
     */
    Route::get('/karya', [KaryaController::class, 'index'])->name('anggota.karya');
    Route::get('/karya/baru', [KaryaController::class, 'baru'])->name('anggota.karya.baru');
    Route::post('/karya', [KaryaController::class, 'simpan'])->name('anggota.karya.simpan');
    Route::get('/karya/{karya}', [KaryaController::class, 'sunting'])->name('anggota.karya.sunting');
    Route::put('/karya/{karya}', [KaryaController::class, 'perbarui'])->name('anggota.karya.perbarui');
    Route::post('/karya/{karya}/kirim', [KaryaController::class, 'kirim'])->name('anggota.karya.kirim');
});

