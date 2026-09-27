<?php

namespace App\Models\Concerns;

/**
 * Menyelesaikan kolom terjemahan saat model diubah menjadi larik atau JSON.
 *
 * MASALAH YANG DISELESAIKAN.
 *
 * spatie/laravel-translatable menyimpan satu kolom berisi SELURUH bahasa:
 * `{"id":"Kegiatan","en":"Activities"}`. Mengambil `$model->nama` sudah benar —
 * yang keluar adalah teks untuk bahasa yang sedang aktif.
 *
 * Tetapi saat model diubah menjadi larik atau JSON, yang keluar adalah peta
 * bahasanya mentah. Panel pengurus mengirim model ke Inertia, Inertia
 * mengubahnya menjadi JSON, dan yang sampai ke layar adalah:
 *
 *     { "id": "Kegiatan", "en": "Activities" }
 *
 * Persis itu yang terjadi pada filter Kategori di halaman Publikasi: penyaring
 * yang seharusnya berbunyi "Kegiatan" menampilkan JSON. Pengurus tidak bisa
 * memakainya, dan tampilannya terlihat rusak.
 *
 * Karena itu trait ini menutup celah tersebut: setiap kolom terjemahan yang ADA
 * pada model diselesaikan ke bahasa yang sedang aktif sebelum diserialisasi.
 *
 * HANYA kolom yang memang ada yang diselesaikan. Model yang diambil dengan
 * sebagian kolom (mis. `get(['id','nama'])`) tidak akan tiba-tiba mendapat
 * kunci tambahan berisi null — perubahan bentuk data seperti itu bisa memecahkan
 * hal lain tanpa ketahuan.
 */
trait TerjemahanSaatSerialisasi
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $atribut = parent::toArray();

        foreach ($this->getTranslatableAttributes() as $nama) {
            if (! array_key_exists($nama, $atribut)) {
                continue;
            }

            $atribut[$nama] = $this->getTranslation($nama, app()->getLocale());
        }

        return $atribut;
    }
}
