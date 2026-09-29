<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pemantauan pemakaian penyimpanan media terhadap jatah penyedia.
 *
 * Angka yang dilaporkan adalah jumlah kolom `size` pada tabel `media`, yaitu
 * ukuran BERKAS ASLI yang dikelola Pustaka Media. Karena itu angka ini adalah
 * BATAS BAWAH dari ukuran wadah yang sesungguhnya:
 *
 *   - berkas di luar Pustaka Media (mis. bukti pembayaran pada disk privat)
 *     tidak ikut terhitung;
 *   - berkas turunan (thumbnail hasil konversi) juga tidak terhitung, padahal
 *     setiap konversi menyimpan berkas tambahan di disk.
 *
 * Untuk sebuah peringatan dini, batas bawah justru pilihan yang tepat: ia
 * tidak pernah membuat sisa ruang tampak lebih lega daripada kenyataannya.
 *
 * Yang TIDAK boleh dilakukan adalah menjadikannya pagar yang menolak
 * unggahan. Angka ini bisa keliru ke arah mana pun, dan menolak unggahan
 * pengurus di tengah dokumentasi kegiatan jauh lebih merugikan daripada
 * kelebihan tagihan yang hendak dicegahnya.
 */
class PemantauPenyimpanan
{
    /** Pemakaian masih jauh dari jatah; panel tidak menampilkan peringatan. */
    public const AMAN = 'aman';

    /** Pemakaian sudah melewati ambang peringatan, tetapi jatah belum habis. */
    public const WASPADA = 'waspada';

    /** Jatah sudah habis; kelebihannya mulai dihitung berbayar. */
    public const LEWAT = 'lewat';

    /**
     * Hasil hitung yang disimpan agar SUM atas tabel media tidak dijalankan
     * berulang kali dalam satu permintaan (beberapa accessor memakainya).
     */
    private ?int $terpakai = null;

    /**
     * Total byte berkas asli yang tercatat di Pustaka Media.
     */
    public function terpakai(): int
    {
        return $this->terpakai ??= (int) Media::query()->sum('size');
    }

    /**
     * Jatah penyimpanan dalam byte. Nol berarti jatah tidak diatur.
     */
    public function kuota(): int
    {
        return max(0, (int) config('penyimpanan.kuota_mb', 0)) * 1048576;
    }

    /**
     * Pemakaian sebagai persentase jatah, atau null bila jatah tidak diatur.
     *
     * Sengaja mengembalikan angka apa adanya (bukan dibulatkan) supaya
     * perbandingan ambang tidak terpengaruh pembulatan; pemakaian untuk
     * tampilan dibulatkan ke bawah di sisi penyajian.
     */
    public function persen(): ?float
    {
        $kuota = $this->kuota();

        if ($kuota <= 0) {
            return null;
        }

        return $this->terpakai() / $kuota * 100;
    }

    /**
     * Keadaan pemakaian saat ini: aman, waspada, atau lewat.
     */
    public function status(): string
    {
        $persen = $this->persen();

        // Tanpa jatah yang diatur, tidak ada yang bisa diperingatkan.
        if ($persen === null) {
            return self::AMAN;
        }

        if ($persen >= 100) {
            return self::LEWAT;
        }

        $ambang = (float) config('penyimpanan.ambang_peringatan', 0.8) * 100;

        return $persen >= $ambang ? self::WASPADA : self::AMAN;
    }
}
