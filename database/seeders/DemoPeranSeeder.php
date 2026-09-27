<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Models\AlumniProfile;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Member;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data demo: akun tiap peran beserta isi pendukungnya.
 *
 * Tujuannya satu: setiap peran bisa masuk dan melihat dasbornya dalam keadaan
 * berisi — bukan halaman kosong yang tidak membuktikan apa pun.
 *
 * DI PRODUKSI, SEEDER INI HANYA BERJALAN BILA DIMINTA
 * ---------------------------------------------------
 * Dulu ia menolak produksi mentah-mentah. Penolakan itu benar pada masanya:
 * seeder ini menimpa kata sandi akun yang sudah ada, sehingga menjalankannya
 * di server sungguhan akan mengunci pengurus dari akunnya sendiri.
 *
 * Yang salah bukan larangannya, melainkan sebabnya. Penimpaan itu sekarang
 * dihapus — kata sandi hanya dipasang saat akunnya BARU dibuat — dan
 * larangannya diganti dua syarat yang harus dipenuhi dengan sadar:
 *
 *   1. APP_JALANKAN_SEED_DEMO=true
 *   2. SEED_DEMO_PASSWORD terisi (kata sandi bawaan di kode TIDAK dipakai
 *      di produksi, karena ia tertulis di repositori)
 *
 * Tanpa keduanya, produksi aman: tidak ada yang berjalan sendiri.
 *
 * PERINGATAN YANG BERLAKU SELAMA SAKELARNYA MENYALA
 * Semua akun demo memakai SATU kata sandi bersama, dan alamatnya berpola
 * `@raab.test` yang mudah ditebak. Siapa pun yang tahu kata sandi itu bisa
 * masuk sebagai sekretaris atau bendahara. Matikan sakelarnya setelah masa uji
 * coba, lalu ganti kata sandi akun-akun itu lewat panel.
 *
 * Peran yang disiapkan:
 *   superadmin     — ketua@raab.test            (sudah ada)
 *   sekretaris     — sekretaris@raab.test
 *   bendahara      — bendahara@raab.test
 *   konten_manager — konten@raab.test
 *   kader          — kader@raab.test
 *   alumni         — alumni@raab.test
 */
class DemoPeranSeeder extends Seeder
{
    /** Kata sandi bawaan untuk pengembangan. Produksi wajib memakai SEED_DEMO_PASSWORD. */
    public const KATA_SANDI = 'RahasiaKader2026';

    /** Nama sakelar yang harus dinyalakan agar seeder ini mau berjalan di produksi. */
    private const SAKLAR = 'APP_JALANKAN_SEED_DEMO';

    /** Kata sandi yang dipakai pada sekali jalan. */
    private ?string $kataSandi = null;

    /**
     * Akun pengurus.
     *
     * @var array<int, array{email: string, nama: string, peran: string}>
     */
    private const PENGURUS = [
        [
            'email' => 'sekretaris@raab.test',
            'nama' => 'Ahmad Fauzi',
            'peran' => 'sekretaris',
        ],
        [
            'email' => 'bendahara@raab.test',
            'nama' => 'Dewi Anggraini',
            'peran' => 'bendahara',
        ],
        [
            'email' => 'konten@raab.test',
            'nama' => 'Rangga Pratama',
            'peran' => 'konten_manager',
        ],
    ];

    public function run(): void
    {
        if (! $this->bolehBerjalan()) {
            return;
        }

        $this->kataSandi = $this->pilihKataSandi();

        if ($this->kataSandi === null) {
            return;
        }

        DB::transaction(function (): void {
            $this->siapkanBerkasDemo();
            $this->siapkanAkunSuperadmin();
            $this->siapkanAkunPengurus();
            $this->siapkanAnggotaDemo();
            $this->siapkanKategoriPrestasi();
            $this->siapkanSlider();
            $this->siapkanGaleri();
        });

        $this->cetakRingkasan();
    }

    /**
     * Apakah seeder ini boleh berjalan sekarang.
     *
     * Di luar produksi selalu boleh — pengembangan butuh datanya. Di produksi
     * hanya boleh bila sakelarnya dinyalakan dengan sadar.
     */
    private function bolehBerjalan(): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        /*
         * filter_var(), BUKAN `=== 'true'`.
         *
         * env() tidak mengembalikan nilai apa adanya: 'true' dan 'false'
         * diubahnya menjadi boolean sungguhan. Jadi perbandingan
         * `env(SAKLAR) === 'true'` SELALU salah — sakelarnya tidak akan pernah
         * terbaca meski sudah diisi dengan benar, di produksi maupun di
         * pengujian, dan seeder diam-diam menolak berjalan.
         *
         * filter_var() menerima keduanya: boolean true dan string 'true'.
         */
        if (filter_var(env(self::SAKLAR), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $this->command?->error(
            'DemoPeranSeeder TIDAK berjalan di produksi. '.
            'Isi '.self::SAKLAR.'=true bila memang ingin menyalakan akun demo untuk masa uji coba.'
        );

        return false;
    }

    /**
     * Kata sandi akun demo.
     *
     * Di luar produksi dipakai nilai bawaan di kode supaya pengembangan dan
     * pengujian tidak perlu menyiapkan apa pun. Di produksi nilai itu SENGAJA
     * tidak dipakai: ia tertulis di repositori, jadi memakainya sama dengan
     * mengumumkan kata sandi akun sekretaris dan bendahara.
     */
    private function pilihKataSandi(): ?string
    {
        $dariEnv = trim((string) env('SEED_DEMO_PASSWORD', ''));

        if ($dariEnv !== '') {
            return $dariEnv;
        }

        if (app()->environment('production')) {
            $this->command?->error(
                'SEED_DEMO_PASSWORD KOSONG — akun demo TIDAK dibuat. '.
                'Kata sandi bawaan di kode tidak dipakai di produksi.'
            );

            return null;
        }

        return self::KATA_SANDI;
    }

    /* ------------------------------------------------------------------ */
    /* Akun                                                                */
    /* ------------------------------------------------------------------ */

    private function siapkanAkunSuperadmin(): void
    {
        $ketua = $this->akun('ketua@raab.test', 'Ketua Rayon', 'superadmin');

        $this->command?->info('Superadmin : '.$ketua->email);
    }

    private function siapkanAkunPengurus(): void
    {
        foreach (self::PENGURUS as $data) {
            $user = $this->akun($data['email'], $data['nama'], $data['peran']);

            $this->command?->info(str_pad($data['peran'], 15).': '.$user->email);
        }
    }

    /**
     * Anggota demo untuk akun kader & alumni.
     *
     * Dibuat oleh seeder ini SENDIRI — tidak lagi meminjam anggota yang sudah
     * ada. Versi sebelumnya mengambil anggota mana pun yang ditemukan lebih
     * dulu, dan itu berbahaya di server sungguhan: anggota pertama yang
     * ditemukan bisa saja kader betulan, lalu alamat email akunnya diganti
     * menjadi alamat demo dan kata sandinya ditimpa. Seeder mengambil alih
     * akun orang lain.
     *
     * Konsekuensinya diakui: dashboard kader demo tidak berisi iuran atau
     * peminjaman, karena data itu memang milik anggota sungguhan. Yang
     * dibuktikan di sini adalah bahwa PERANNYA dapat masuk dan membuka
     * dasbornya — bukan bahwa setiap fitur sudah terisi.
     */
    private function siapkanAnggotaDemo(): void
    {
        $this->pastikanAnggota('kader@raab.test', 'Kader Demo', Member::STATUS_AKTIF);
        $this->pastikanAnggota('alumni@raab.test', 'Alumni Demo', Member::STATUS_ALUMNI);
    }

    private function pastikanAnggota(string $email, string $nama, string $status): void
    {
        $user = $this->akun($email, $nama);

        if (Member::query()->where('user_id', $user->id)->exists()) {
            $this->command?->info(str_pad($status, 15).': '.$email.'  (anggota sudah ada)');

            return;
        }

        $anggota = new Member;
        $anggota->user_id = $user->id;
        $anggota->nomor_anggota = 'DEMO-'.mb_strtoupper(Str::slug($nama));
        $anggota->jalur = $status === Member::STATUS_ALUMNI ? Member::JALUR_ALUMNI : Member::JALUR_KADER;
        $anggota->status = $status;
        $anggota->nama_lengkap = $nama;
        $anggota->jenis_kelamin = 'laki_laki';
        $anggota->fakultas = 'Ushuluddin dan Dakwah';
        $anggota->program_studi = 'Komunikasi dan Penyiaran Islam';
        $anggota->angkatan = 2022;
        $anggota->save();

        if ($status === Member::STATUS_ALUMNI) {
            $profil = new AlumniProfile;
            $profil->member_id = $anggota->id;
            $profil->tahun_lulus = 2026;
            $profil->bidang = 'Jurnalistik';
            $profil->kota_domisili = 'Sukoharjo';
            $profil->instansi = 'Contoh Instansi';
            $profil->kontak_publik = ['instansi' => true, 'kota_domisili' => true];
            $profil->save();
        }

        $this->command?->info(str_pad($status, 15).': '.$email.'  (anggota: '.$anggota->nama_lengkap.')');
    }

    /**
     * Siapkan satu akun demo: kata sandi diketahui, email terverifikasi, peran
     * terpasang.
     *
     * Verifikasi email diisi karena panel pengurus menuntutnya — tanpa itu,
     * demo akan berhenti di layar "verifikasi email dulu".
     */
    private function akun(string $email, string $nama, ?string $peran = null): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->name = $user->exists ? $user->name : $nama;
        $user->email_verified_at = $user->email_verified_at ?? now();

        /*
         * Kata sandi HANYA dipasang saat akunnya baru.
         *
         * Inilah yang dulu membuat seeder ini berbahaya: setiap kali
         * dijalankan, kata sandi pengurus dikembalikan ke nilai yang tertulis
         * di repositori — tanpa pemberitahuan, dan tanpa cara mengembalikannya
         * bila nilai barunya sudah terlupa.
         */
        if (! $user->exists) {
            $user->password = Hash::make($this->kataSandi);
        }

        $user->save();

        if ($peran !== null) {
            $user->syncRoles([$peran]);
        }

        return $user;
    }

    /* ------------------------------------------------------------------ */
    /* Isi pendukung                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * @var array<int, array{kode: string, nama: string, keterangan: string}>
     */
    private const KATEGORI_PRESTASI = [
        ['kode' => 'akademik', 'nama' => 'Akademik', 'keterangan' => 'Prestasi bidang akademik & keilmuan.'],
        ['kode' => 'non_akademik', 'nama' => 'Non-Akademik', 'keterangan' => 'Olahraga, seni, dan minat khusus.'],
        ['kode' => 'organisasi', 'nama' => 'Organisasi', 'keterangan' => 'Kepengurusan dan kepemimpinan.'],
        ['kode' => 'sosial', 'nama' => 'Sosial & Pengabdian', 'keterangan' => 'Pengabdian masyarakat dan kegiatan sosial.'],
    ];

    private function siapkanKategoriPrestasi(): void
    {
        foreach (self::KATEGORI_PRESTASI as $urutan => $data) {
            AchievementCategory::query()->updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'nama' => ['id' => $data['nama'], 'en' => $data['nama']],
                    'keterangan' => ['id' => $data['keterangan'], 'en' => $data['keterangan']],
                    'urutan' => $urutan + 1,
                    'aktif' => true,
                ],
            );
        }

        // Prestasi yang sudah ada ditautkan ke kategori pertama supaya panelnya
        // tidak menampilkan baris tanpa kategori.
        $kategori = AchievementCategory::query()->orderBy('urutan')->first();

        if ($kategori !== null) {
            Achievement::query()->whereNull('achievement_category_id')
                ->update(['achievement_category_id' => $kategori->id]);
        }
    }

    /**
     * Slider beranda.
     *
     * Sengaja TANPA gambar: berkasnya ditulis langsung ke penyimpanan, dan
     * gambar tanpa isi yang benar hanya akan tampil sebagai kotak rusak.
     * Halaman beranda sudah punya tampilan cadangan bila slidenya tidak
     * bergambar, jadi yang diuji di sini adalah teks dan tombolnya.
     */
    private function siapkanSlider(): void
    {
        if (Slider::query()->exists()) {
            return;
        }

        $data = [
            [
                'judul' => ['id' => 'Bergerak dalam Ilmu, Tumbuh dalam Aksi', 'en' => 'Moving in Knowledge, Growing in Action'],
                'subjudul' => ['id' => 'Rumah bagi kader yang berani berpikir, menulis, dan bergerak.', 'en' => 'A home for cadres who dare to think, write, and move.'],
                'label_tombol' => ['id' => 'Kenali Kami', 'en' => 'Get to Know Us'],
                'tautan_tombol' => '/sejarah',
            ],
            [
                'judul' => ['id' => 'Mapaba 2026 Segera Dibuka', 'en' => 'Mapaba 2026 Is Opening Soon'],
                'subjudul' => ['id' => 'Pendaftaran calon anggota baru dibuka untuk seluruh mahasiswa.', 'en' => 'Registration for new members is open to all students.'],
                'label_tombol' => ['id' => 'Daftar Sekarang', 'en' => 'Register Now'],
                'tautan_tombol' => '/pendaftaran',
            ],
        ];

        foreach ($data as $urutan => $baris) {
            Slider::query()->create($baris + [
                'urutan' => $urutan + 1,
                'aktif' => true,
            ]);
        }
    }

    /**
     * Galeri foto.
     *
     * Gambarnya memakai berkas SVG contoh yang ditulis seeder ini sendiri ke
     * penyimpanan publik. Alasannya: wadah pengembangan ini tidak punya
     * ekstensi gambar, jadi berkas JPEG/PNG tidak bisa dibuat di sini — dan
     * galeri berisi kotak rusak tidak membuktikan apa pun.
     */
    private function siapkanGaleri(): void
    {
        if (Gallery::query()->exists()) {
            return;
        }

        $album = [
            [
                'judul' => ['id' => 'Mapaba 2026', 'en' => 'Mapaba 2026'],
                'deskripsi' => ['id' => 'Dokumentasi penerimaan anggota baru.', 'en' => 'Documentation of the new member intake.'],
                'lokasi' => 'Kampus UIN Raden Mas Said',
                'jumlah' => 4,
            ],
            [
                'judul' => ['id' => 'Diskusi Publik Bulanan', 'en' => 'Monthly Public Discussion'],
                'deskripsi' => ['id' => 'Forum terbuka bersama LSO keilmuan.', 'en' => 'An open forum with the academic LSO.'],
                'lokasi' => 'Sekretariat Rayon',
                'jumlah' => 3,
            ],
        ];

        foreach ($album as $urutanAlbum => $data) {
            $galeri = Gallery::query()->create([
                'judul' => $data['judul'],
                'slug' => ['id' => Str::slug($data['judul']['id']), 'en' => Str::slug($data['judul']['en'])],
                'deskripsi' => $data['deskripsi'],
                'tanggal' => now()->subDays(($urutanAlbum + 1) * 7)->toDateString(),
                'lokasi' => $data['lokasi'],
                'publik' => true,
                'urutan' => $urutanAlbum + 1,
            ]);

            for ($i = 1; $i <= $data['jumlah']; $i++) {
                GalleryItem::query()->create([
                    'gallery_id' => $galeri->id,
                    'url' => '/storage/demo/'.$this->namaBerkasContoh($urutanAlbum, $i),
                    'keterangan' => ['id' => $data['judul']['id'].' — foto '.$i, 'en' => $data['judul']['en'].' — photo '.$i],
                    'urutan' => $i,
                ]);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Berkas contoh                                                       */
    /* ------------------------------------------------------------------ */

    private const WARNA_CONTOH = [
        ['#2E3192', '#FFD100'],
        ['#FF6B6B', '#0B0B0B'],
        ['#1B75BB', '#FFFDF5'],
        ['#FFD100', '#2E3192'],
    ];

    /**
     * Tulis berkas SVG contoh ke `storage/app/public/demo/`.
     *
     * Hanya ditulis bila belum ada, sehingga menjalankan seeder dua kali tidak
     * menimpa apa pun.
     */
    private function siapkanBerkasDemo(): void
    {
        $folder = storage_path('app/public/demo');

        if (! File::isDirectory($folder)) {
            File::makeDirectory($folder, 0o755, true);
        }

        foreach ([0, 1] as $album) {
            foreach ([1, 2, 3, 4] as $nomor) {
                $jalur = $folder.'/'.$this->namaBerkasContoh($album, $nomor);

                if (File::exists($jalur)) {
                    continue;
                }

                File::put($jalur, $this->svgContoh($album + 1, $nomor));
            }
        }
    }

    private function namaBerkasContoh(int $album, int $nomor): string
    {
        return 'contoh-album-'.($album + 1).'-foto-'.$nomor.'.svg';
    }

    /**
     * Gambar contoh bergaya blok warna, tanpa teks yang menyesatkan.
     */
    private function svgContoh(int $album, int $nomor): string
    {
        [$latar, $aksen] = self::WARNA_CONTOH[($album + $nomor) % count(self::WARNA_CONTOH)];

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600" role="img" aria-label="Gambar contoh">
          <rect width="800" height="600" fill="{$latar}"/>
          <rect x="40" y="40" width="720" height="520" fill="none" stroke="{$aksen}" stroke-width="8"/>
          <circle cx="400" cy="270" r="110" fill="{$aksen}" opacity="0.9"/>
          <rect x="300" y="430" width="200" height="26" fill="{$aksen}"/>
        </svg>
        SVG;
    }

    /* ------------------------------------------------------------------ */

    private function cetakRingkasan(): void
    {
        $this->command?->newLine();
        $this->command?->info($this->kataSandi === self::KATA_SANDI
            ? 'Kata sandi seluruh akun demo: '.self::KATA_SANDI
            : 'Kata sandi seluruh akun demo: nilai SEED_DEMO_PASSWORD (sengaja tidak dicetak).');
        $this->command?->line('  superadmin     ketua@raab.test');
        $this->command?->line('  sekretaris     sekretaris@raab.test');
        $this->command?->line('  bendahara      bendahara@raab.test');
        $this->command?->line('  konten manager konten@raab.test');
        $this->command?->line('  kader          kader@raab.test');
        $this->command?->line('  alumni         alumni@raab.test');
        $this->command?->line('');
        $this->command?->line('Akun ini memakai SATU kata sandi bersama. Matikan '.self::SAKLAR.' dan ganti kata sandinya setelah masa uji coba.');
    }
}
