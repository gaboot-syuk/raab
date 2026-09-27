<?php

namespace App\Services;

use App\Support\Pengaturan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Penerjemah otomatis Indonesia → Inggris untuk isi artikel.
 *
 * MENDukung dua penyedia layanan:
 *  - deepl  (api-free.deepl.com / api.deepl.com) — mendukung penanganan HTML
 *  - google (translation.googleapis.com, Cloud Translation v2)
 *  - none   (bawaan) — tidak menerjemahkan apa pun
 *
 * Tanpa kunci API, `tersedia()` bernilai false dan antarmuka panel akan
 * mengatakannya terus terang — bukan diam-diam gagal.
 *
 * GLOSARIUM
 * Istilah organisasi (PMII, Mapaba, PKD, Rayon Ali Ahmad Baktsir, …) dilindungi
 * dengan menggantinya menjadi penanda sementara sebelum dikirim, lalu
 * dikembalikan setelah terjemahan diterima. Tanpa ini, mesin penerjemah akan
 * menerjemahkan nama lembaga — hasilnya sering memalukan.
 */
class Penerjemah
{
    public const DRIVER_NONE = 'none';
    public const DRIVER_DEEPL = 'deepl';
    public const DRIVER_GOOGLE = 'google';

    /**
     * @var array<string, string>
     */
    public const DRIVER = [
        self::DRIVER_NONE => 'Tidak menerjemahkan (manual)',
        self::DRIVER_DEEPL => 'DeepL',
        self::DRIVER_GOOGLE => 'Google Cloud Translation',
    ];

    /**
     * Istilah yang TIDAK boleh diterjemahkan.
     *
     * @var array<int, string>
     */
    private const GLOSARIUM = [
        'PMII Rayon Ali Ahmad Baktsir',
        'Rayon Ali Ahmad Baktsir',
        'Komisariat Raden Mas Said',
        'Lembaga Semi Otonom',
        'Pengurus Rayon',
        'Kader Aktif',
        'Mapaba',
        'PKD',
        'Mabinra',
        'PMII',
        'RAAB',
        'LSO',
        'Harokatuna',
        'Albiruni',
        'Mutasi',
        'LDR',
        'MJT',
        'Sukoharjo',
        'UIN Raden Mas Said',
    ];

    public function driver(): string
    {
        return (string) Pengaturan::teks('penerjemah_driver', self::DRIVER_NONE);
    }

    /**
     * Kunci API diambil dari berkas .env, BUKAN dari basis data.
     *
     * Alasannya: tabel site_settings dimuat seluruhnya oleh Pengaturan::semua()
     * dan dipakai juga oleh halaman publik. Menyimpan rahasia di sana membuatnya
     * ikut terbawa ke setiap tampilan — satu kelalaian cetak variabel sudah cukup
     * untuk membocorkannya.
     */
    public function kunci(): string
    {
        return (string) config('services.penerjemah.kunci', '');
    }

    /**
     * Apakah penerjemahan otomatis siap dipakai?
     */
    public function tersedia(): bool
    {
        return $this->driver() !== self::DRIVER_NONE && filled($this->kunci());
    }

    /**
     * Alasan penerjemahan tidak tersedia — untuk ditampilkan di panel.
     */
    public function alasanTidakTersedia(): ?string
    {
        if ($this->driver() === self::DRIVER_NONE) {
            return 'Adaptor penerjemah masih disetel ke "Tidak menerjemahkan". Pilih penyedia pada Pengaturan Situs → Terjemahan.';
        }

        if (blank($this->kunci())) {
            return 'Kunci API penerjemah belum diisi. Tambahkan PENERJEMAH_KUNCI pada berkas .env server, lalu bersihkan cache konfigurasi.';
        }

        return null;
    }

    /**
     * Terjemahkan HTML dari bahasa Indonesia ke Inggris.
     *
     * Mengembalikan null bila penerjemah tidak tersedia atau layanan menolak —
     * pemanggil wajib memeriksa hasilnya, bukan menganggap selalu berhasil.
     */
    public function terjemahkan(string $html, string $dari = 'ID', string $ke = 'EN'): ?string
    {
        if (! $this->tersedia() || blank(trim(strip_tags($html)))) {
            return null;
        }

        [$dilindungi, $peta] = $this->lindungiIstilah($html);

        $hasil = match ($this->driver()) {
            self::DRIVER_DEEPL => $this->lewatDeepL($dilindungi, $dari, $ke),
            self::DRIVER_GOOGLE => $this->lewatGoogle($dilindungi, $dari, $ke),
            default => null,
        };

        return $hasil === null ? null : $this->kembalikanIstilah($hasil, $peta);
    }

    /* ------------------------------------------------------------------ */
    /* Penyedia                                                            */
    /* ------------------------------------------------------------------ */

    private function lewatDeepL(string $teks, string $dari, string $ke): ?string
    {
        // Kunci berakhiran ":fx" menandakan akun DeepL API Free → titik akhir berbeda.
        $titikAkhir = Str::endsWith($this->kunci(), ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        try {
            $respons = Http::asForm()
                ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->kunci()])
                ->timeout(30)
                ->post($titikAkhir, [
                    'text' => $teks,
                    'source_lang' => strtoupper($dari),
                    'target_lang' => strtoupper($ke),
                    // Wajib: agar tag HTML tidak dianggap teks biasa.
                    'tag_handling' => 'html',
                ]);

            if (! $respons->successful()) {
                Log::warning('Penerjemah DeepL menolak permintaan', ['status' => $respons->status(), 'body' => $respons->body()]);

                return null;
            }

            return $respons->json('translations.0.text');
        } catch (\Throwable $galat) {
            Log::warning('Penerjemah DeepL gagal: '.$galat->getMessage());

            return null;
        }
    }

    private function lewatGoogle(string $teks, string $dari, string $ke): ?string
    {
        try {
            $respons = Http::timeout(30)->post(
                'https://translation.googleapis.com/language/translate/v2?key='.urlencode($this->kunci()),
                [
                    'q' => $teks,
                    'source' => strtolower($dari),
                    'target' => strtolower($ke),
                    'format' => 'html',
                ],
            );

            if (! $respons->successful()) {
                Log::warning('Penerjemah Google menolak permintaan', ['status' => $respons->status(), 'body' => $respons->body()]);

                return null;
            }

            // Google mengembalikan HTML-escape pada hasilnya.
            return html_entity_decode((string) $respons->json('data.translations.0.translatedText'));
        } catch (\Throwable $galat) {
            Log::warning('Penerjemah Google gagal: '.$galat->getMessage());

            return null;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Glosarium                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Ganti istilah organisasi dengan penanda agar tidak diterjemahkan.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function lindungiIstilah(string $html): array
    {
        $peta = [];
        $hasil = $html;

        // Istilah terpanjang didahulukan agar "Rayon Ali Ahmad Baktsir" tertangkap
        // sebelum "RAAB" yang merupakan bagian darinya.
        $istilah = self::GLOSARIUM;
        usort($istilah, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($istilah as $urutan => $satu) {
            $penanda = 'XXTERM'.$urutan.'XX';

            if (str_contains($hasil, $satu)) {
                $hasil = str_replace($satu, $penanda, $hasil);
                $peta[$penanda] = $satu;
            }
        }

        return [$hasil, $peta];
    }

    /**
     * @param  array<string, string>  $peta
     */
    private function kembalikanIstilah(string $html, array $peta): string
    {
        return str_replace(array_keys($peta), array_values($peta), $html);
    }
}
