<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ArtikelRequest;
use App\Jobs\TerjemahkanArtikel;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use App\Models\User;
use App\Services\Penerjemah;
use App\Services\Redaksi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pengelolaan artikel: menulis, mengirim untuk review, menerbitkan.
 *
 * Halaman ini dipakai bersama oleh dua kelompok:
 *  - Kader (penulis) — membuat artikel & mengirimnya untuk review.
 *  - Konten Manager   — menyunting semua artikel, memberi catatan review,
 *                       menerbitkan, menjadwalkan, dan mengelola kategori.
 *
 * Tombol yang tampil disesuaikan izin: penulis melihat "Kirim untuk Review",
 * pengelola melihat "Terbitkan / Minta Revisi / Tolak".
 */
class ArtikelController extends Controller
{
    public function __construct(
        private readonly Redaksi $redaksi,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $bolehKelola = $user?->can('articles.review') ?? false;

        $saring = [
            'status' => $request->string('status')->toString() ?: 'semua',
            'tipe' => $request->string('tipe')->toString() ?: 'semua',
            'kategori' => $request->string('kategori')->toString() ?: 'semua',
            'cari' => trim($request->string('cari')->toString()),
            'terjemahan' => $request->string('terjemahan')->toString(),
        ];

        $daftar = Article::query()
            ->with(['kategori:id,nama', 'penulis:id,name'])
            ->when(! $bolehKelola, fn ($q) => $q->where('user_id', $user?->id))
            ->when($saring['status'] !== 'semua', fn ($q) => $q->where('status', $saring['status']))
            ->when($saring['tipe'] !== 'semua', fn ($q) => $q->where('tipe', $saring['tipe']))
            ->when($saring['kategori'] !== 'semua', fn ($q) => $q->where('kategori_id', (int) $saring['kategori']))
            ->when($saring['terjemahan'] === 'belum', fn ($q) => $q->belumDiterjemahkan())
            ->when($saring['cari'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('judul->id', 'like', "%{$saring['cari']}%")
                    ->orWhere('judul->en', 'like', "%{$saring['cari']}%"),
            ))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Article $artikel): array => $this->ringkas($artikel));

        return Inertia::render('Panel/Artikel/Index', [
            'daftar' => $daftar,
            'saring' => $saring,
            'bolehKelola' => $bolehKelola,
            'bolehTerbitkanBeritaAcara' => $user?->can('articles.publish-berita-acara') ?? false,
            'pilihanTipe' => Article::TIPE,
            'pilihanStatus' => Article::STATUS,
            'jumlah' => [
                'semua' => $this->hitung($bolehKelola, $user, null),
                'draf' => $this->hitung($bolehKelola, $user, Article::STATUS_DRAF),
                'menunggu_review' => $this->hitung($bolehKelola, $user, Article::STATUS_MENUNGGU),
                'perlu_revisi' => $this->hitung($bolehKelola, $user, Article::STATUS_REVISI),
                'terbit' => $this->hitung($bolehKelola, $user, Article::STATUS_TERBIT),
                'ditolak' => $this->hitung($bolehKelola, $user, Article::STATUS_DITOLAK),
            ],
            'jumlahBelumTerjemah' => Article::query()
                ->when(! $bolehKelola, fn ($q) => $q->where('user_id', $user?->id))
                ->belumDiterjemahkan()
                ->count(),
            'daftarKategori' => ArticleCategory::query()->aktif()->get(['id', 'nama']),
        ]);
    }

    public function baru(): Response
    {
        return Inertia::render('Panel/Artikel/Sunting', [
            'artikel' => null,
            'pilihanTipe' => Article::TIPE,
            'daftarKategori' => ArticleCategory::query()->aktif()->get(['id', 'nama']),
            'pilihanGambar' => $this->pilihanGambar(),
            'tagPopuler' => Tag::query()->populer()->limit(20)->get(['id', 'nama']),
        ]);
    }

    public function sunting(Article $artikel): Response
    {
        abort_unless(
            request()->user()?->can('articles.review') || $artikel->user_id === request()->user()?->id,
            403,
        );

        return Inertia::render('Panel/Artikel/Sunting', [
            'artikel' => [
                ...$this->ringkas($artikel),
                'tipe' => $artikel->tipe,
                'kategori_id' => $artikel->kategori_id,
                'cover_media_id' => $artikel->cover_media_id,
                'cover_url' => $artikel->cover_media_id
                    ? Media::query()->find($artikel->cover_media_id)?->getUrl()
                    : null,
                'judul' => $artikel->getTranslations('judul'),
                'slug' => $artikel->getTranslations('slug'),
                'ringkasan' => $artikel->getTranslations('ringkasan'),
                'konten' => $artikel->getTranslations('konten'),
                'seo_judul' => $artikel->getTranslations('seo_judul'),
                'seo_deskripsi' => $artikel->getTranslations('seo_deskripsi'),
                'tag' => $artikel->tags->map(fn (Tag $tag): string => (string) $tag->getTranslation('nama', 'id', false))->implode(', '),
                'unggulan' => $artikel->unggulan,
                'dijadwalkan_pada' => $artikel->dijadwalkan_pada?->format('Y-m-d\TH:i'),
                'catatan_review' => $artikel->catatan_review,
                'nomor_dokumen' => $artikel->nomor_dokumen,
                'tanggal_agenda' => $artikel->tanggal_agenda?->format('Y-m-d'),
                'agenda' => $artikel->agenda,
                'keputusan' => $artikel->keputusan,
                'penandatangan' => $artikel->penandatangan,
                'jabatan_penandatangan' => $artikel->jabatan_penandatangan,
                'tautan_publik' => $this->tautanPublik($artikel),
                'revisi' => $artikel->revisi()->with('pengubah:id,name')->limit(Article::MAKS_REVISI)->get()->map(fn ($r): array => [
                    'id' => $r->id,
                    'judul' => $r->getTranslation('judul', 'id', false),
                    'status' => $r->status,
                    'catatan' => $r->catatan,
                    'oleh' => $r->pengubah?->name ?? 'Sistem',
                    'waktu' => $r->created_at?->translatedFormat('d M Y H:i'),
                ])->all(),
            ],
            'pilihanTipe' => Article::TIPE,
            'daftarKategori' => ArticleCategory::query()->aktif()->get(['id', 'nama']),
            'pilihanGambar' => $this->pilihanGambar(),
            'tagPopuler' => Tag::query()->populer()->limit(20)->get(['id', 'nama']),
            'penerjemah' => [
                'tersedia' => app(Penerjemah::class)->tersedia(),
                'alasan' => app(Penerjemah::class)->alasanTidakTersedia(),
            ],
        ]);
    }

    public function simpan(ArtikelRequest $request): RedirectResponse
    {
        $artikel = new Article;
        $this->redaksi->simpan($artikel, $request->validated(), $request->user());

        return redirect()
            ->route('panel.artikel.sunting', $artikel)
            ->with('sukses', 'Artikel disimpan sebagai '.$artikel->labelStatus().'.');
    }

    public function perbarui(ArtikelRequest $request, Article $artikel): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('articles.update') && ($request->user()?->can('articles.review') || $artikel->user_id === $request->user()?->id),
            403,
        );

        $this->redaksi->simpan($artikel, $request->validated(), $request->user());

        return back()->with('sukses', 'Artikel berhasil diperbarui.');
    }

    public function kirimReview(Request $request, Article $artikel): RedirectResponse
    {
        abort_unless($artikel->user_id === $request->user()?->id, 403);

        $this->redaksi->kirimReview($artikel, $request->user());

        return back()->with('sukses', 'Artikel dikirim ke pengelola konten untuk ditinjau.');
    }

    public function terbitkan(Request $request, Article $artikel): RedirectResponse
    {
        abort_if(
            $artikel->adalahBeritaAcara(),
            403,
            'Berita acara diterbitkan melalui tombol khusus berita acara.',
        );

        $this->redaksi->terbitkan($artikel, $request->user());

        $pesan = $artikel->fresh()->terbit_pada?->isFuture()
            ? 'Artikel dijadwalkan terbit pada '.$artikel->fresh()->terbit_pada->translatedFormat('d M Y H:i').'.'
            : 'Artikel berhasil diterbitkan.';

        return back()->with('sukses', $pesan);
    }

    /**
     * Terbitkan berita acara — tanpa melewati review.
     *
     * Berita acara adalah dokumen administratif Sekretaris, sehingga alur
     * review Konten Manager tidak berlaku untuknya.
     */
    public function terbitkanBeritaAcara(Request $request, Article $artikel): RedirectResponse
    {
        abort_unless(
            $artikel->adalahBeritaAcara(),
            403,
            'Rute ini hanya untuk berita acara.',
        );

        $this->redaksi->terbitkanLangsung($artikel, $request->user());

        return back()->with('sukses', 'Berita acara '.$artikel->nomor_dokumen.' diterbitkan.');
    }

    public function mintaRevisi(Request $request, Article $artikel): RedirectResponse
    {
        $data = $request->validate([
            'catatan' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['catatan' => 'catatan revisi']);

        $this->redaksi->mintaRevisi($artikel, $request->user(), $data['catatan']);

        return back()->with('sukses', 'Permintaan revisi dikirim ke penulis.');
    }

    public function tolak(Request $request, Article $artikel): RedirectResponse
    {
        $data = $request->validate([
            'catatan' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['catatan' => 'alasan penolakan']);

        $this->redaksi->tolak($artikel, $request->user(), $data['catatan']);

        return back()->with('sukses', 'Artikel ditolak dan penulis sudah diberi kabar.');
    }

    public function tarikKembali(Request $request, Article $artikel): RedirectResponse
    {
        $this->redaksi->tarikKembali($artikel, $request->user());

        return back()->with('sukses', 'Artikel ditarik kembali menjadi draf.');
    }

    /**
     * Unduh berita acara sebagai berkas PDF.
     *
     * Hanya berlaku untuk berita acara — dokumen administratif yang memang
     * perlu dicetak dan ditandatangani. Artikel biasa tidak memerlukannya.
     */
    public function unduhPdf(Article $artikel): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless(
            $artikel->adalahBeritaAcara(),
            403,
            'Hanya berita acara yang dapat diunduh sebagai PDF.',
        );

        $pdf = Pdf::loadView('pdf.berita-acara', ['artikel' => $artikel])->setPaper('a4');

        // Nomor dokumen memakai garis miring (012/BA/RAAB/IX/2026); diganti
        // tanda hubung lebih dulu agar tidak hilang menjadi "012baraabix2026".
        $nomor = \Illuminate\Support\Str::slug(str_replace('/', '-', (string) $artikel->nomor_dokumen));

        $namaBerkas = 'berita-acara-'.($nomor !== '' ? $nomor : $artikel->id).'.pdf';

        return $pdf->download($namaBerkas);
    }

    /**
     * Statistik publikasi (internal).
     *
     * Angka-angka di sini membantu pengelola menilai: tipe apa yang paling
     * banyak dibaca, siapa yang paling produktif, dan berapa banyak artikel
     * yang terbit tanpa versi Inggris.
     */
    public function statistik(): Response
    {
        $totalDibaca = (int) Article::query()->sum('dilihat');
        $jumlahTerbit = Article::query()->where('status', Article::STATUS_TERBIT)->count();

        return Inertia::render('Panel/Artikel/Statistik', [
            'ringkasan' => [
                'artikel' => Article::query()->count(),
                'terbit' => $jumlahTerbit,
                'draf' => Article::query()->where('status', Article::STATUS_DRAF)->count(),
                'menunggu' => Article::query()->where('status', Article::STATUS_MENUNGGU)->count(),
                'total_dibaca' => $totalDibaca,
                'rata_dibaca' => $jumlahTerbit > 0 ? (int) round($totalDibaca / $jumlahTerbit) : 0,
                'belum_terjemah' => Article::query()->belumDiterjemahkan()->count(),
            ],
            'terpopuler' => Article::query()
                ->with('penulis:id,name')
                ->where('status', Article::STATUS_TERBIT)
                ->orderByDesc('dilihat')
                ->limit(10)
                ->get()
                ->map(fn (Article $artikel): array => [
                    'id' => $artikel->id,
                    'judul' => $artikel->getTranslation('judul', 'id', false),
                    'label_tipe' => $artikel->labelTipe(),
                    'penulis' => $artikel->penulis?->name,
                    'dilihat' => $artikel->dilihat,
                ]),
            'perPenulis' => Article::query()
                ->with('penulis:id,name')
                ->selectRaw('user_id, count(*) as jumlah, coalesce(sum(dilihat), 0) as total_dibaca, coalesce(sum(status = ?), 0) as jumlah_terbit', [Article::STATUS_TERBIT])
                ->groupBy('user_id')
                ->orderByDesc('jumlah')
                ->limit(10)
                ->get()
                ->map(fn (Article $artikel): array => [
                    'penulis' => $artikel->penulis?->name ?? 'Pengguna terhapus',
                    'jumlah' => (int) $artikel->jumlah,
                    'jumlah_terbit' => (int) $artikel->jumlah_terbit,
                    'total_dibaca' => (int) $artikel->total_dibaca,
                ]),
            'perTipe' => Article::query()
                ->selectRaw('tipe, count(*) as jumlah, coalesce(sum(dilihat), 0) as total_dibaca')
                ->whereIn('tipe', Article::TIPE_PUBLIK)
                ->groupBy('tipe')
                ->orderByDesc('jumlah')
                ->get()
                ->map(fn (Article $artikel): array => [
                    'tipe' => $artikel->labelTipe(),
                    'jumlah' => (int) $artikel->jumlah,
                    'total_dibaca' => (int) $artikel->total_dibaca,
                ]),
        ]);
    }

    public function hapus(Article $artikel): RedirectResponse
    {
        $artikel->delete();

        return redirect()->route('panel.artikel')->with('sukses', 'Artikel dihapus.');
    }

    public function unggulan(Request $request, Article $artikel): RedirectResponse
    {
        $artikel->forceFill(['unggulan' => ! $artikel->unggulan])->save();

        return back()->with('sukses', $artikel->unggulan
            ? 'Artikel ditandai sebagai unggulan.'
            : 'Tanda unggulan dilepas.');
    }

    /**
     * Jalankan terjemahan otomatis Indonesia → Inggris.
     *
     * Dikerjakan lewat antrean karena layanan penerjemah dapat memakan beberapa
     * detik; panel tidak perlu menunggu. Bila penerjemah belum dikonfigurasi,
     * panel diberi tahu alasannya secara jelas — bukan gagal diam-diam.
     */
    public function terjemahkan(Article $artikel): RedirectResponse
    {
        $penerjemah = app(Penerjemah::class);

        if (! $penerjemah->tersedia()) {
            return back()->with('galat', $penerjemah->alasanTidakTersedia());
        }

        TerjemahkanArtikel::dispatch($artikel->id, true);

        return back()->with('sukses', 'Terjemahan otomatis dijalankan di latar belakang. Muat ulang halaman beberapa saat lagi.');
    }

    /* ------------------------------------------------------------------ */
    /* Bagian dalam                                                        */
    /* ------------------------------------------------------------------ */

    private function hitung(bool $bolehKelola, ?User $user, ?string $status): int
    {
        return Article::query()
            ->when(! $bolehKelola, fn ($q) => $q->where('user_id', $user?->id))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(Article $artikel): array
    {
        return [
            'id' => $artikel->id,
            'tipe' => $artikel->tipe,
            'label_tipe' => $artikel->labelTipe(),
            'status' => $artikel->status,
            'label_status' => $artikel->labelStatus(),
            'judul_id' => $artikel->getTranslation('judul', 'id', false),
            'judul_en' => $artikel->getTranslation('judul', 'en', false),
            'slug_id' => $artikel->getTranslation('slug', 'id', false),
            'kategori' => $artikel->kategori?->getTranslation('nama', 'id', false),
            'penulis' => $artikel->penulis?->name,
            'unggulan' => $artikel->unggulan,
            'dilihat' => $artikel->dilihat,
            'kelengkapan_en' => $artikel->kelengkapanTerjemahan(),
            'terbit_pada' => $artikel->terbit_pada?->translatedFormat('d M Y H:i'),
            'dijadwalkan_pada' => $artikel->dijadwalkan_pada?->translatedFormat('d M Y H:i'),
            'diperbarui_pada' => $artikel->updated_at?->translatedFormat('d M Y H:i'),
        ];
    }

    private function tautanPublik(Article $artikel): ?string
    {
        if ($artikel->status !== Article::STATUS_TERBIT
            || ! in_array($artikel->tipe, Article::TIPE_PUBLIK, true)) {
            return null;
        }

        $slug = $artikel->getTranslation('slug', 'id', false);

        return $slug ? route('public.publikasi.detail', ['tipe' => $artikel->tipe, 'slug' => $slug]) : null;
    }

    /**
     * Daftar gambar pada pustaka media, untuk memilih sampul.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function pilihanGambar()
    {
        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'nama' => $media->name ?: $media->file_name,
                'url' => $media->hasGeneratedConversion('kecil') ? $media->getUrl('kecil') : $media->getUrl(),
            ]);
    }
}
