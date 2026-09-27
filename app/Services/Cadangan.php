<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Cadangan basis data — dibuat dan DIPULIHKAN tanpa alat luar.
 *
 * KENAPA TIDAK MEMAKAI `mysqldump`. Perintah itu tidak ada di dalam kontainer
 * aplikasi, dan di hosting murah ia sering juga tidak ada. Cadangan yang
 * pembuatannya bergantung pada alat yang belum tentu tersedia bukan cadangan —
 * ia baru berguna kalau kebetulan alatnya ada. Karena itu seluruh prosesnya
 * ditulis dalam PHP, memakai koneksi basis data yang sudah dipakai aplikasi.
 *
 * KENAPA SATU PERNYATAAN PER BARIS. Nilai teks bisa memuat baris baru (isi
 * artikel, keterangan, notulen). Kalau baris baru ditulis apa adanya, satu
 * pernyataan akan terpecah menjadi beberapa baris dan pemulihannya mustahil
 * dilakukan dengan aman. Karena itu baris baru SELALU diubah menjadi bentuk
 * yang tidak mengandung baris baru, dengan cara yang berbeda menurut jenis
 * basis datanya:
 *
 *   - MySQL  : `\n` (MySQL membaca garis miring terbalik sebagai pelarian)
 *   - SQLite : digabung dengan `char(10)`
 *
 * Perbedaan ini bukan hiasan: SQLite TIDAK mengenal `\'` sebagai pelarian, ia
 * memakai penggandaan tanda kutip (`''`). Menulis satu bentuk untuk keduanya
 * akan menghasilkan berkas cadangan yang tampak benar tetapi gagal dipulihkan.
 *
 * PEMULIHAN MENGHAPUS ISI TABEL SEBELUM MENGISINYA. Itu memang arti memulihkan.
 * Karena itu perintahnya menuntut penegasan, dan halaman panelnya hanya bisa
 * MENGUNDUH — tidak ada tombol "pulihkan" di antarmuka, supaya tidak ada yang
 * bisa menghapus data organisasi karena salah klik.
 */
class Cadangan
{
    /** Folder cadangan, di dalam penyimpanan privat (tidak dilayani web). */
    public const FOLDER = 'cadangan';

    /** Berapa berkas cadangan terbaru yang disimpan. */
    public const SIMPAN_BERKAS = 14;

    private string $driver;

    public function __construct()
    {
        $this->driver = DB::connection()->getDriverName();
    }

    private function sqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /* ------------------------------------------------------------------ */
    /* Membuat                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Buat berkas cadangan baru.
     *
     * @return array{berkas: string, tabel: int, baris: int, ukuran: int}
     */
    public function buat(): array
    {
        $tabel = $this->daftarTabel();
        $nama = 'cadangan-'.now()->format('Y-m-d_His').'.sql';
        $baris = 0;

        $keluaran = fopen('php://temp', 'w+b');

        fwrite($keluaran, $this->kepala($tabel));

        foreach ($tabel as $namaTabel) {
            fwrite($keluaran, "\n-- Tabel: {$namaTabel}\n");
            fwrite($keluaran, "DELETE FROM {$this->bungkus($namaTabel)};\n");

            DB::table($namaTabel)->orderBy($this->kunciUrut($namaTabel))->chunk(500, function ($potongan) use ($keluaran, $namaTabel, &$baris): void {
                foreach ($potongan as $catatan) {
                    fwrite($keluaran, $this->pernyataanSisip($namaTabel, (array) $catatan)."\n");
                    $baris++;
                }
            });
        }

        fwrite($keluaran, "\n".$this->penutup()."\n");

        rewind($keluaran);
        $isi = stream_get_contents($keluaran);
        fclose($keluaran);

        $this->disk()->put(self::FOLDER.'/'.$nama, $isi);

        Log::info('Cadangan basis data dibuat.', ['berkas' => $nama, 'tabel' => count($tabel), 'baris' => $baris]);

        return [
            'berkas' => $nama,
            'tabel' => count($tabel),
            'baris' => $baris,
            'ukuran' => strlen($isi),
        ];
    }

    /**
     * @param  array<int, string>  $tabel
     */
    private function kepala(array $tabel): string
    {
        return implode("\n", [
            '-- Cadangan basis data PMII RAAB',
            '-- Dibuat   : '.now()->toDateTimeString(),
            '-- Aplikasi : '.(config('app.name') ?: 'PMII RAAB'),
            '-- Pengandar: '.$this->driver,
            '-- Tabel    : '.implode(', ', $tabel),
            '--',
            '-- PERHATIAN: berkas ini memuat SELURUH isi basis data, termasuk data pribadi',
            '-- anggota dan hash kata sandi. Simpan di tempat yang aman, jangan dibagikan.',
            '--',
            '-- Memulihkan: php artisan cadangan:pulihkan',
            '-- (isi tabel yang ada dihapus lebih dulu, lalu diisi dari berkas ini)',
            '',
            '-- Kunci pemeriksaan kunci asing dimatikan selama pemulihan supaya urutan',
            '-- tabel tidak menjadi persoalan. Dinyalakan kembali di akhir berkas.',
            $this->matikanKunciAsing(),
        ]);
    }

    private function penutup(): string
    {
        return $this->nyalakanKunciAsing();
    }

    private function matikanKunciAsing(): string
    {
        return $this->sqlite() ? 'PRAGMA foreign_keys = OFF;' : 'SET FOREIGN_KEY_CHECKS=0;';
    }

    private function nyalakanKunciAsing(): string
    {
        return $this->sqlite() ? 'PRAGMA foreign_keys = ON;' : 'SET FOREIGN_KEY_CHECKS=1;';
    }

    /**
     * @return array<int, string>
     */
    public function daftarTabel(): array
    {
        $tabel = [];

        foreach (Schema::getTableListing() as $nama) {
            /*
             * Awalan skema dibuang LEBIH DULU. SQLite mengembalikan nama
             * seperti `main.migrations` dan `main.sqlite_sequence`, sehingga
             * memeriksa nama apa adanya akan meloloskan keduanya — dan
             * `sqlite_sequence` menolak ditulisi, yang membuat pemulihan gagal
             * justru pada tabel internal basis data.
             */
            $nama = str_contains($nama, '.') ? substr($nama, strpos($nama, '.') + 1) : $nama;

            // `migrations` tidak dicadangkan: isinya milik proses migrasi, dan
            // memulihkannya justru bisa membuat migrasi berikutnya terlewat.
            // Tabel internal SQLite juga bukan isi basis data yang perlu
            // dipulihkan, dan sebagiannya tidak bisa ditulisi sama sekali.
            if ($nama === 'migrations' || str_starts_with($nama, 'sqlite_')) {
                continue;
            }

            $tabel[] = $nama;
        }

        return $tabel;
    }

    /**
     * Kunci untuk mengurutkan. Tabel tanpa kolom `id` diurutkan apa adanya,
     * karena `orderBy` pada kolom yang tidak ada akan menggagalkan cadangan
     * hanya karena urutannya tidak sempurna.
     */
    private function kunciUrut(string $tabel): string
    {
        return Schema::hasColumn($tabel, 'id') ? 'id' : (Schema::getColumnListing($tabel)[0] ?? '1');
    }

    /**
     * @param  array<string, mixed>  $catatan
     */
    private function pernyataanSisip(string $tabel, array $catatan): string
    {
        $kolom = array_map(fn (string $k): string => $this->bungkus($k), array_keys($catatan));
        $nilai = array_map(fn ($v): string => $this->kutip($v), array_values($catatan));

        return 'INSERT INTO '.$this->bungkus($tabel).' ('.implode(', ', $kolom).') VALUES ('.implode(', ', $nilai).');';
    }

    private function bungkus(string $nama): string
    {
        // SQLite memakai tanda kutip ganda, MySQL memakai garis miring terbalik.
        return $this->sqlite() ? '"'.$nama.'"' : '`'.$nama.'`';
    }

    /**
     * Ubah satu nilai menjadi bentuk SQL yang aman DAN tidak mengandung baris baru.
     */
    private function kutip(mixed $nilai): string
    {
        if ($nilai === null) {
            return 'NULL';
        }

        if (is_bool($nilai)) {
            return $nilai ? '1' : '0';
        }

        if (is_int($nilai) || is_float($nilai)) {
            return (string) $nilai;
        }

        $teks = (string) $nilai;

        if (! $this->sqlite()) {
            return "'".str_replace(
                ['\\', "'", "\r", "\n"],
                ['\\\\', "\\'", '\\r', '\\n'],
                $teks,
            )."'";
        }

        // SQLite: tanda kutip digandakan, dan baris baru disandikan lewat
        // penggabungan `char()` — bukan pelarian garis miring terbalik.
        $bagian = [];

        foreach (preg_split('/(\r\n|\r|\n)/', $teks, -1, PREG_SPLIT_DELIM_CAPTURE) as $potongan) {
            $bagian[] = match ($potongan) {
                "\r\n" => 'char(13) || char(10)',
                "\r" => 'char(13)',
                "\n" => 'char(10)',
                default => "'".str_replace("'", "''", $potongan)."'",
            };
        }

        return $bagian === [] ? "''" : implode(' || ', $bagian);
    }

    /* ------------------------------------------------------------------ */
    /* Memulihkan                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Pulihkan dari sebuah berkas cadangan.
     *
     * @return array{tabel: int, baris: int, per_tabel: array<string, int>}
     */
    public function pulihkan(string $nama): array
    {
        if (! $this->ada($nama)) {
            throw new \RuntimeException('Berkas cadangan tidak ditemukan: '.$nama);
        }

        [$sebelum, $pernyataan, $sesudah] = $this->bacaBerkas($this->jalur($nama));

        /*
         * Pengaturan kunci asing HARUS dijalankan di luar transaksi.
         *
         * SQLite mengabaikan perubahan `PRAGMA foreign_keys` selama transaksi
         * berlangsung. Kalau dijalankan di dalam, urutan tabel kembali menjadi
         * persoalan dan pemulihan gagal di tabel yang berelasi — tepat pada
         * saat berkas cadangan sedang dibutuhkan.
         */
        foreach ($sebelum as $sql) {
            DB::statement($sql);
        }

        $perTabel = [];

        DB::beginTransaction();

        /*
         * SQLite: tunda pemeriksaan kunci asing sampai COMMIT.
         *
         * `PRAGMA foreign_keys` TIDAK BISA diubah selama transaksi berlangsung,
         * dan pemulihan SELALU berjalan di dalam transaksi. Tanpa penundaan ini,
         * baris akan diperiksa satu per satu saat disisipkan — padahal tabel
         * yang dirujuknya bisa saja baru terisi beberapa pernyataan kemudian.
         * Urutan abjad tabel tidak ada hubungannya dengan urutan ketergantungan,
         * jadi tanpa penundaan ini pemulihan gagal pada data yang sebenarnya
         * utuh.
         */
        if ($this->sqlite()) {
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }

        try {
            foreach ($pernyataan as $sql) {
                if (preg_match('/^INSERT INTO\s+[`"]?([A-Za-z0-9_]+)[`"]?/i', $sql, $cocok)) {
                    $perTabel[$cocok[1]] = ($perTabel[$cocok[1]] ?? 0) + 1;
                }

                DB::statement($sql);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($sesudah as $sql) {
                DB::statement($sql);
            }

            Log::error('Pemulihan cadangan gagal.', ['berkas' => $nama, 'galat' => $e->getMessage()]);

            throw $e;
        }

        foreach ($sesudah as $sql) {
            DB::statement($sql);
        }

        Log::warning('Cadangan dipulihkan — isi tabel diganti.', ['berkas' => $nama, 'pernyataan' => count($pernyataan)]);

        return [
            'tabel' => count($perTabel),
            'baris' => array_sum($perTabel),
            'per_tabel' => $perTabel,
        ];
    }

    /**
     * Baca berkas cadangan dan pisahkan pernyataannya menurut waktu jalan.
     *
     * @return array{0: array<int, string>, 1: array<int, string>, 2: array<int, string>}
     */
    private function bacaBerkas(string $jalur): array
    {
        $sebelum = [];
        $pernyataan = [];
        $sesudah = [];

        $aliran = $this->disk()->readStream($jalur);

        if ($aliran === null) {
            throw new \RuntimeException('Berkas cadangan tidak dapat dibaca: '.$jalur);
        }

        while (($baris = fgets($aliran)) !== false) {
            $baris = trim($baris);

            if ($baris === '' || str_starts_with($baris, '--')) {
                continue;
            }

            // Setiap pernyataan berada pada SATU baris — lihat penjelasan di
            // kepala kelas tentang baris baru yang disandikan.
            $sql = rtrim($baris, ';');

            if (str_starts_with($sql, 'PRAGMA foreign_keys') || str_starts_with($sql, 'SET FOREIGN_KEY_CHECKS')) {
                $menyalakan = str_contains($sql, '= ON') || str_contains($sql, '=1') || str_contains($sql, '= 1');

                /*
                 * Ditulis dengan if/else, BUKAN `($kondisi ? $a : $b)[] = ...`.
                 * PHP menolak ekspresi sementara sebagai sasaran pengisian
                 * ("Cannot use temporary expression in write context"), dan
                 * galatnya baru muncul saat baris itu dijalankan — artinya baru
                 * ketahuan ketika berkas cadangan sudah ada di tangan.
                 */
                if ($menyalakan) {
                    $sesudah[] = $sql;
                } else {
                    $sebelum[] = $sql;
                }

                continue;
            }

            $pernyataan[] = $sql;
        }

        fclose($aliran);

        return [$sebelum, $pernyataan, $sesudah];
    }

    /* ------------------------------------------------------------------ */
    /* Daftar, hapus, bersihkan                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array{nama: string, ukuran: int, ukuran_teks: string, dibuat: string}>
     */
    public function daftar(): array
    {
        $hasil = [];

        foreach ($this->disk()->files(self::FOLDER) as $jalur) {
            $nama = basename($jalur);

            if (! str_ends_with($nama, '.sql')) {
                continue;
            }

            $ukuran = (int) $this->disk()->size($jalur);

            $hasil[] = [
                'nama' => $nama,
                'ukuran' => $ukuran,
                'ukuran_teks' => $this->ukuranTerbaca($ukuran),
                'dibuat' => date('d F Y, H:i', (int) $this->disk()->lastModified($jalur)),
            ];
        }

        // Terbaru lebih dulu: nama berkas sudah memuat tanggal & jam.
        usort($hasil, fn (array $a, array $b): int => strcmp($b['nama'], $a['nama']));

        return $hasil;
    }

    public function jalur(string $nama): string
    {
        return self::FOLDER.'/'.basename($nama);
    }

    public function ada(string $nama): bool
    {
        return $this->disk()->exists($this->jalur($nama));
    }

    public function hapus(string $nama): bool
    {
        if (! $this->ada($nama)) {
            return false;
        }

        $this->disk()->delete($this->jalur($nama));

        return true;
    }

    /**
     * Sisakan sejumlah berkas terbaru, hapus sisanya.
     *
     * Cadangan menumpuk dengan cepat, dan penyimpanan gratis hampir selalu
     * terbatas. Membersihkan yang lama adalah bagian dari membuat cadangan,
     * bukan pekerjaan terpisah yang bisa dilupakan.
     */
    public function bersihkan(int $simpan = self::SIMPAN_BERKAS): int
    {
        $daftar = $this->daftar();
        $dihapus = 0;

        foreach (array_slice($daftar, $simpan) as $berkas) {
            if ($this->hapus($berkas['nama'])) {
                $dihapus++;
            }
        }

        return $dihapus;
    }

    private function ukuranTerbaca(int $bita): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $satuan) {
            if ($bita < 1024 ** ($i + 1)) {
                return round($bita / (1024 ** $i), $i === 0 ? 0 : 1).' '.$satuan;
            }
        }

        return round($bita / (1024 ** 3), 1).' GB';
    }
}
