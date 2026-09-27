<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Snapshot isi artikel pada satu titik waktu.
 *
 * Setiap perubahan penting (dikirim untuk review, diterbitkan, atau disunting
 * setelah terbit) menyimpan satu revisi. Hanya Article::MAKS_REVISI versi
 * terakhir yang disimpan agar basis data tidak membengkak.
 */
#[Fillable(['article_id', 'user_id', 'judul', 'konten', 'status', 'catatan'])]
class ArticleRevision extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['judul', 'konten'];

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Simpan revisi artikel, lalu pangkas versi lama.
     */
    public static function simpan(Article $artikel, ?int $userId, ?string $catatan = null): self
    {
        /*
         * Terjemahan WAJIB dipasang SEBELUM baris disimpan: kolom `judul` dan
         * `konten` bersifat NOT NULL, sehingga membuat baris lebih dulu (mis.
         * lewat create()) akan gagal karena nilainya masih kosong.
         */
        $revisi = new self;

        $revisi->article_id = $artikel->id;
        $revisi->user_id = $userId;
        $revisi->status = $artikel->status;
        $revisi->catatan = $catatan;

        foreach (['judul', 'konten'] as $kolom) {
            $revisi->setTranslations($kolom, $artikel->getTranslations($kolom));
        }

        $revisi->save();

        // Pangkas: sisakan hanya versi terbaru.
        $berlebih = self::query()
            ->where('article_id', $artikel->id)
            ->orderByDesc('id')
            ->skip(Article::MAKS_REVISI)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($berlebih->isNotEmpty()) {
            self::query()->whereIn('id', $berlebih)->delete();
        }

        return $revisi;
    }
}
