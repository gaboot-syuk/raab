<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\Redaksi\KabarArtikel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alur redaksi artikel.
 *
 * Dikumpulkan di satu kelas agar setiap perpindahan status selalu disertai:
 *  - penyimpanan revisi (agar naskah lama tidak hilang),
 *  - pencatatan reviewer & waktunya,
 *  - pemberitahuan email ke penulis.
 *
 * Berita acara TIDAK melewati alur ini — Sekretaris menerbitkannya langsung
 * (lihat terbitkanLangsung()).
 */
class Redaksi
{
    public function __construct(private Kontribusi $kontribusi) {}

    /**
     * Penulis mengirim karyanya untuk ditinjau.
     */
    public function kirimReview(Article $artikel, User $oleh): Article
    {
        return DB::transaction(function () use ($artikel, $oleh): Article {
            $artikel->forceFill([
                'status' => Article::STATUS_MENUNGGU,
                'catatan_review' => null,
            ])->save();

            ArticleRevision::simpan($artikel, $oleh->id, 'Dikirim untuk review');

            $this->kabariPengelola($artikel, new KabarArtikel(KabarArtikel::DITUGASKAN, $artikel));

            return $artikel->refresh();
        });
    }

    /**
     * Konten Manager menerbitkan artikel.
     */
    public function terbitkan(Article $artikel, User $oleh): Article
    {
        return DB::transaction(function () use ($artikel, $oleh): Article {
            // Terbit sekarang, atau mengikuti jadwal yang sudah ditetapkan.
            $kapan = $artikel->dijadwalkan_pada && $artikel->dijadwalkan_pada->isFuture()
                ? $artikel->dijadwalkan_pada
                : now();

            $artikel->forceFill([
                'status' => Article::STATUS_TERBIT,
                'terbit_pada' => $artikel->terbit_pada ?? $kapan,
                'reviewer_id' => $oleh->id,
                'direview_pada' => now(),
                'catatan_review' => null,
                'waktu_baca_menit' => $artikel->hitungWaktuBaca(),
            ])->save();

            ArticleRevision::simpan($artikel, $oleh->id, 'Diterbitkan');

            $artikel->penulis?->notify(new KabarArtikel(KabarArtikel::TERBIT, $artikel));

            /*
             * Poin kontribusi untuk penulisnya.
             *
             * HANYA di sini, TIDAK di `terbitkanLangsung()`. Berita acara adalah
             * dokumen resmi yang memang tugas Sekretaris, bukan karya yang
             * diperlombakan — kalau ikut berpoin, satu berita acara rapat
             * nilainya sama dengan satu karya tulis.
             *
             * Pemberiannya idempoten lewat `sidik`, jadi menerbitkan ulang
             * artikel yang sama tidak menggandakan poinnya.
             */
            $this->kontribusi->dariArtikel($artikel, $oleh);

            // Terjemahkan otomatis bila adaptor siap dan versi Inggris masih kosong.
            $this->jadwalkanTerjemahan($artikel);

            return $artikel->refresh();
        });
    }

    /**
     * Penerbitan langsung tanpa review — hanya untuk berita acara.
     */
    public function terbitkanLangsung(Article $artikel, User $oleh): Article
    {
        return $this->terbitkan($artikel, $oleh);
    }

    /**
     * Minta penulis memperbaiki naskahnya.
     */
    public function mintaRevisi(Article $artikel, User $oleh, string $catatan): Article
    {
        return DB::transaction(function () use ($artikel, $oleh, $catatan): Article {
            $artikel->forceFill([
                'status' => Article::STATUS_REVISI,
                'catatan_review' => $catatan,
                'reviewer_id' => $oleh->id,
                'direview_pada' => now(),
            ])->save();

            ArticleRevision::simpan($artikel, $oleh->id, 'Diminta revisi');

            $artikel->penulis?->notify(new KabarArtikel(KabarArtikel::REVISI, $artikel, $catatan));

            return $artikel->refresh();
        });
    }

    /**
     * Tolak artikel dengan alasan.
     */
    public function tolak(Article $artikel, User $oleh, string $catatan): Article
    {
        return DB::transaction(function () use ($artikel, $oleh, $catatan): Article {
            $artikel->forceFill([
                'status' => Article::STATUS_DITOLAK,
                'catatan_review' => $catatan,
                'reviewer_id' => $oleh->id,
                'direview_pada' => now(),
            ])->save();

            ArticleRevision::simpan($artikel, $oleh->id, 'Ditolak');

            $artikel->penulis?->notify(new KabarArtikel(KabarArtikel::DITOLAK, $artikel, $catatan));

            return $artikel->refresh();
        });
    }

    /**
     * Tarik kembali artikel yang sudah terbit menjadi draf.
     */
    public function tarikKembali(Article $artikel, User $oleh): Article
    {
        return DB::transaction(function () use ($artikel, $oleh): Article {
            $artikel->forceFill([
                'status' => Article::STATUS_DRAF,
                'terbit_pada' => null,
            ])->save();

            ArticleRevision::simpan($artikel, $oleh->id, 'Ditarik kembali menjadi draf');

            return $artikel->refresh();
        });
    }

    /**
     * Simpan (buat/perbarui) artikel beserta kategori-tag-nya.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpan(Article $artikel, array $data, User $oleh): Article
    {
        return DB::transaction(function () use ($artikel, $data, $oleh): Article {
            $baru = ! $artikel->exists;

            foreach (['judul', 'slug', 'ringkasan', 'konten', 'seo_judul', 'seo_deskripsi'] as $kolom) {
                $nilai = array_filter(
                    $data[$kolom] ?? [],
                    fn ($isi) => is_string($isi) && trim($isi) !== '',
                );

                $artikel->setTranslations($kolom, $nilai);
            }

            $artikel->fill([
                'tipe' => $data['tipe'],
                'kategori_id' => $data['kategori_id'] ?? null,
                'cover_media_id' => $data['cover_media_id'] ?? null,
                'dijadwalkan_pada' => $data['dijadwalkan_pada'] ?? null,
                'unggulan' => (bool) ($data['unggulan'] ?? false),
                'nomor_dokumen' => $data['nomor_dokumen'] ?? null,
                'tanggal_agenda' => $data['tanggal_agenda'] ?? null,
                'agenda' => $data['agenda'] ?? null,
                'keputusan' => $data['keputusan'] ?? null,
                'penandatangan' => $data['penandatangan'] ?? null,
                'jabatan_penandatangan' => $data['jabatan_penandatangan'] ?? null,
            ]);

            // Slug otomatis bila kosong, dan dijamin unik.
            $this->pastikanSlug($artikel);

            if ($baru) {
                $artikel->user_id = $data['user_id'] ?? $oleh->id;
                $artikel->status = $data['status'] ?? Article::STATUS_DRAF;
            } else {
                $artikel->status = $data['status'] ?? $artikel->status;
            }

            $artikel->waktu_baca_menit = $artikel->hitungWaktuBaca();
            $artikel->save();

            $this->sinkronkanTag($artikel, $data['tag'] ?? []);
            ArticleRevision::simpan($artikel, $oleh->id, $baru ? 'Dibuat' : 'Disunting');

            return $artikel->refresh();
        });
    }

    /**
     * Pastikan slug versi Indonesia & Inggris terisi dan tidak bertabrakan.
     */
    private function pastikanSlug(Article $artikel): void
    {
        foreach (['id', 'en'] as $locale) {
            $judul = $artikel->getTranslation('judul', $locale, false);
            $slug = $artikel->getTranslation('slug', $locale, false);

            if (blank($slug) && filled($judul)) {
                $slug = Article::buatSlug((string) $judul);
            }

            if (blank($slug)) {
                continue;
            }

            $dasar = $slug;
            $urutan = 1;

            while ($this->slugDipakai($artikel, $locale, $slug)) {
                $urutan++;
                $slug = $dasar.'-'.$urutan;
            }

            $artikel->setTranslation('slug', $locale, $slug);
        }
    }

    private function slugDipakai(Article $artikel, string $locale, string $slug): bool
    {
        return Article::query()
            ->where('id', '!=', $artikel->id ?? 0)
            ->where("slug->{$locale}", $slug)
            ->exists();
    }

    /**
     * Ubah daftar nama tag menjadi relasi tag (membuat tag baru bila perlu).
     *
     * @param  array<int, string>|string  $daftar
     */
    private function sinkronkanTag(Article $artikel, array|string $daftar): void
    {
        $nama = collect(is_array($daftar) ? $daftar : explode(',', $daftar))
            ->map(fn ($butir): string => trim((string) $butir))
            ->filter()
            ->unique()
            ->values();

        $id = $nama->map(function (string $satu): int {
            /*
             * CATATAN: firstOrCreate() TIDAK dapat dipakai dengan kunci JSON
             * (mis. ['nama->id' => ...]) — kolomnya bukan kolom nyata sehingga
             * baris baru tersimpan dengan nama NULL. Karena itu pencarian dan
             * pembuatan dilakukan terpisah.
             */
            $tag = Tag::query()->where('nama->id', $satu)->first();

            if (! $tag) {
                $tag = new Tag;
                $tag->dipakai = 0;
            }

            $tag->setTranslation('nama', 'id', $satu);

            if (blank($tag->getTranslation('nama', 'en', false))) {
                $tag->setTranslation('nama', 'en', $satu);
            }

            if (blank($tag->getTranslation('slug', 'id', false))) {
                $tag->setTranslation('slug', 'id', Str::slug($satu));
                $tag->setTranslation('slug', 'en', Str::slug($satu));
            }

            $tag->save();

            return $tag->id;
        })->all();

        $artikel->tags()->sync($id);

        // Perbarui penghitung pemakaian agar daftar tag populer akurat.
        Tag::query()->each(function (Tag $tag): void {
            $tag->forceFill(['dipakai' => $tag->artikel()->count()])->save();
        });
    }

    /**
     * Kirim artikel ke antrean penerjemahan bila adaptornya siap.
     *
     * Bila belum dikonfigurasi, tidak ada yang terjadi — artikel tetap terbit
     * apa adanya, dan panel menandainya "belum diterjemahkan".
     */
    private function jadwalkanTerjemahan(Article $artikel): void
    {
        $penerjemah = app(\App\Services\Penerjemah::class);

        if (! $penerjemah->tersedia()) {
            return;
        }

        if ($artikel->kelengkapanTerjemahan() === 100) {
            return;
        }

        \App\Jobs\TerjemahkanArtikel::dispatch($artikel->id);
    }

    /**
     * Beri tahu pengelola konten bahwa ada artikel menunggu review.
     */
    private function kabariPengelola(Article $artikel, KabarArtikel $notifikasi): void
    {
        User::query()
            ->permission('articles.review')
            ->get()
            ->each(fn (User $pengurus) => $pengurus->notify($notifikasi));
    }
}
