<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\AchievementCategory;
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
 * Data demo untuk MENCOBA APLIKASI, bukan untuk produksi.
 *
 * Tujuannya satu: setiap peran bisa masuk dan melihat dasbornya dalam keadaan
 * berisi — bukan halaman kosong yang tidak membuktikan apa pun.
 *
 * SEEDER INI MENOLAK BERJALAN DI PRODUKSI. Ia menyetel ulang kata sandi akun
 * yang sudah ada, dan itu tindakan yang tidak boleh terjadi di server sungguhan.
 *
 * Peran yang disiapkan:
 *   superadmin     — ketua@raab.test            (sudah ada)
 *   sekretaris     — sekretaris@raab.test
 *   bendahara      — bendahara@raab.test
 *   konten_manager — konten@raab.test
 *   kader          — kader@raab.test
 *   alumni         — alumni@raab.test
 *
 * Kata sandi seluruh akun demo: RahasiaKader2026
 */
class DemoPeranSeeder extends Seeder
{
    public const KATA_SANDI = 'RahasiaKader2026';

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
        /*
         * Penjaga produksi.
         *
         * Bukan formalitas: seeder ini menimpa kata sandi akun yang sudah ada.
         * Kalau ia berjalan di server sungguhan, seluruh pengurus bisa
         * terkunci dari akunnya sendiri.
         */
        if (app()->environment('production')) {
            $this->command?->error('DemoPeranSeeder menolak berjalan di produksi.');

            return;
        }

        DB::transaction(function (): void {
            $this->siapkanBerkasDemo();
            $this->siapkanAkunSuperadmin();
            $this->siapkanAkunPengurus();
            $this->siapkanAkunAnggota();
            $this->siapkanKategoriPrestasi();
            $this->siapkanSlider();
            $this->siapkanGaleri();
        });

        $this->cetakRingkasan();
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
     * Akun kader & alumni.
     *
     * Memakai anggota yang SUDAH ada supaya dasbornya berisi: iuran, poin,
     * peminjaman, dan pengumuman yang menempel pada anggota itu. Membuat
     * anggota baru hanya akan menghasilkan dashboard kosong.
     */
    private function siapkanAkunAnggota(): void
    {
        $kader = Member::query()->where('status', Member::STATUS_AKTIF)->orderBy('id')->first();
        $alumni = Member::query()->where('status', Member::STATUS_ALUMNI)->orderBy('id')->first();

        if ($kader !== null) {
            $user = $kader->user ?? User::query()->find($kader->user_id);

            if ($user !== null) {
                $this->jadikanAkunDemo($user, 'kader@raab.test', 'Kader Demo');
                $this->command?->info(str_pad('kader', 15).': '.$user->email.'  (anggota: '.$kader->nama_lengkap.')');
            }
        }

        if ($alumni !== null) {
            $user = $alumni->user ?? User::query()->find($alumni->user_id);

            if ($user !== null) {
                $this->jadikanAkunDemo($user, 'alumni@raab.test', 'Alumni Demo');
                $this->command?->info(str_pad('alumni', 15).': '.$user->email.'  (anggota: '.$alumni->nama_lengkap.')');
            }
        }
    }

    /**
     * Siapkan satu akun demo: kata sandi diketahui, email terverifikasi, peran
     * terpasang.
     *
     * Emailnya DIUBAH menjadi alamat demo supaya mudah diingat saat mencoba,
     * dan supaya jelas bahwa akun ini bukan akun sungguhan. Verifikasi email
     * diisi karena panel pengurus menuntutnya — tanpa itu, demo akan berhenti
     * di layar "verifikasi email dulu".
     */
    private function akun(string $email, string $nama, ?string $peran = null): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->name = $user->exists ? $user->name : $nama;
        $user->password = Hash::make(self::KATA_SANDI);
        $user->email_verified_at = now();
        $user->save();

        if ($peran !== null) {
            $user->syncRoles([$peran]);
        }

        return $user;
    }

    private function jadikanAkunDemo(User $user, string $email, string $namaCadangan): void
    {
        // Akun lain yang kebetulan memakai email demo itu dilepas lebih dulu,
        // supaya kolom unik tidak bentrok saat seeder dijalankan dua kali.
        User::query()->where('email', $email)->whereKeyNot($user->getKey())
            ->update(['email' => 'dipindahkan-'.$user->getKey().'-'.$email]);

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'password' => Hash::make(self::KATA_SANDI),
        ])->save();

        if (blank($user->name)) {
            $user->forceFill(['name' => $namaCadangan])->save();
        }
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
        $this->command?->info('Kata sandi seluruh akun demo: '.self::KATA_SANDI);
        $this->command?->line('  superadmin     ketua@raab.test');
        $this->command?->line('  sekretaris     sekretaris@raab.test');
        $this->command?->line('  bendahara      bendahara@raab.test');
        $this->command?->line('  konten manager konten@raab.test');
        $this->command?->line('  kader          kader@raab.test');
        $this->command?->line('  alumni         alumni@raab.test');
    }
}
