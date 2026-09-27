<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\Penerjemah;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Terjemahkan isi artikel dari bahasa Indonesia ke Inggris lewat antrean.
 *
 * Dijalankan di antrean agar penyimpanan artikel tidak tertahan menunggu
 * layanan penerjemah. Bila penerjemah tidak dikonfigurasi, job berhenti dengan
 * tenang — artikel tetap tersimpan apa adanya tanpa versi Inggris.
 *
 * Versi yang sudah ada TIDAK ditimpa kecuali $paksa = true (tombol
 * "Terjemahkan ulang" di panel).
 */
class TerjemahkanArtikel implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Jangan diulang berkali-kali: kegagalan penerjemah biasanya karena kuota
     * atau kunci salah, bukan gangguan sesaat.
     */
    public int $tries = 2;

    public function __construct(
        public readonly int $articleId,
        public readonly bool $paksa = false,
    ) {}

    public function handle(Penerjemah $penerjemah): void
    {
        $artikel = Article::query()->find($this->articleId);

        if (! $artikel || ! $penerjemah->tersedia()) {
            return;
        }

        $adaPerubahan = false;

        foreach (['judul', 'ringkasan', 'konten'] as $kolom) {
            $sumber = $artikel->getTranslation($kolom, 'id', false);
            $sasaran = $artikel->getTranslation($kolom, 'en', false);

            if (blank($sumber)) {
                continue;
            }

            if (! $this->paksa && filled($sasaran)) {
                continue;
            }

            $hasil = $penerjemah->terjemahkan((string) $sumber);

            if (filled($hasil)) {
                $artikel->setTranslation($kolom, 'en', $hasil);
                $adaPerubahan = true;
            }
        }

        // Waktu baca dihitung ulang bila isi versi Indonesia berubah.
        if ($adaPerubahan) {
            $artikel->waktu_baca_menit = $artikel->hitungWaktuBaca();
            $artikel->save();

            activity()
                ->performedOn($artikel)
                ->withProperties(['driver' => $penerjemah->driver()])
                ->log('Terjemahan otomatis Indonesia → Inggris dijalankan');
        }
    }
}
