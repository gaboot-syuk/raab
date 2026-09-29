<?php

namespace App\Listeners;

use App\Support\PemrosesGambar;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Mengecilkan gambar tepat setelah berkasnya tersimpan.
 *
 * KENAPA INI ADA
 *
 * Foto dari ponsel hari ini lazimnya 4–6 MB, padahal yang dibutuhkan halaman
 * web jarang lebih dari 1600 piksel. Foto 5 MB yang sama, setelah diperkecil,
 * menjadi sekitar 300 KB — dan itu bukan penghematan kecil: jatah 10 GB
 * Cloudflare R2 menampung sekitar 33.000 berkas pada ukuran itu, tetapi hanya
 * sekitar 2.000 berkas pada ukuran aslinya.
 *
 * Pengecilan dilakukan lewat PERISTIWA, bukan di setiap tempat unggah.
 *
 * Medialibrary memancarkan peristiwa ini setelah berkas benar-benar tersimpan,
 * jadi satu pendengar melayani SELURUH jalur unggah yang ada hari ini —
 * pustaka media, sertifikat prestasi, bukti iuran — dan setiap jalur yang
 * ditambahkan kelak, tanpa perlu ada yang ingat memanggilnya.
 *
 * KEGAGALAN DI SINI TIDAK BOLEH MENGGAGALKAN UNGGAHAN
 *
 * Berkasnya sudah tersimpan dan sah; yang gagal hanyalah penghematannya.
 * Membatalkan unggahan pengurus hanya karena mesin gambar bermasalah adalah
 * pertukaran yang buruk.
 */
class KecilkanGambar
{
    /**
     * Jenis yang diproses.
     *
     * GIF dikecualikan dengan sengaja: menyimpannya ulang lewat GD akan
     * menghilangkan animasinya. Berkas yang tidak diproses tetap tersimpan
     * utuh — hanya ukurannya tidak berkurang.
     *
     * @var array<int, string>
     */
    private const DIPROSES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly PemrosesGambar $pemroses) {}

    public function handle(MediaHasBeenAddedEvent $peristiwa): void
    {
        $media = $peristiwa->media;

        if (! in_array($media->mime_type, self::DIPROSES, true)) {
            return;
        }

        try {
            $disk = Storage::disk($media->disk);
            $jalur = $media->getPathRelativeToRoot();
            $isi = $disk->get($jalur);

            if (! is_string($isi) || $isi === '') {
                return;
            }

            $baru = $this->pemroses->perkecil($isi, (string) $media->extension);

            /*
             * Dua penjagaan sekaligus:
             *  - null  : memang tidak ada yang perlu dikerjakan.
             *  - lebih besar : menggantinya tidak menghemat apa pun, dan
             *    justru menambah pemakaian jatah penyimpanan.
             */
            if ($baru === null || strlen($baru) >= strlen($isi)) {
                return;
            }

            $disk->put($jalur, $baru);

            /*
             * Ukuran pada catatan media ikut diperbarui.
             *
             * Panel menghitung total pemakaian dari kolom ini
             * (`Media::sum('size')`). Tanpa pembaruan, angka itu melaporkan
             * ukuran SEBELUM pengecilan — dan peringatan kuota akan berbunyi
             * jauh lebih awal daripada kenyataan.
             */
            $media->forceFill(['size' => strlen($baru)])->saveQuietly();
        } catch (Throwable $galat) {
            Log::warning(
                'Gambar media #'.$media->id.' gagal dikecilkan: '.$galat->getMessage(),
                ['media_id' => $media->id],
            );
        }
    }
}
