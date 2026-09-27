<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use App\Services\Redaksi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Submisi karya oleh kader & alumni, langsung dari dasbor anggota.
 *
 * KENAPA BUKAN DI PANEL?
 * Panel pengurus menjaga setiap halaman dengan izin Spatie. Kader tidak
 * memegang peran apa pun, jadi bila submisi ditempatkan di sana ia tidak akan
 * pernah bisa mengaksesnya. Syarat di sini bukan izin, melainkan STATUS
 * KEANGGOTAAN: hanya anggota yang sudah diverifikasi (kader aktif / alumni)
 * yang boleh menulis.
 *
 * Kader hanya dapat menyunting karyanya sendiri dan mengirimkannya untuk
 * review — penerbitan tetap wewenang Konten Manager.
 */
class KaryaController extends Controller
{
    public function __construct(
        private readonly Redaksi $redaksi,
    ) {}

    public function index(Request $request): Response
    {
        $this->pastikanAnggota($request);

        $daftar = Article::query()
            ->with('kategori:id,nama')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Article $artikel): array => [
                'id' => $artikel->id,
                'tipe' => $artikel->tipe,
                'label_tipe' => $artikel->labelTipe(),
                'status' => $artikel->status,
                'label_status' => $artikel->labelStatus(),
                'judul' => $artikel->getTranslation('judul', 'id', false),
                'kategori' => $artikel->kategori?->getTranslation('nama', 'id', false),
                'catatan_review' => $artikel->catatan_review,
                'terbit_pada' => $artikel->terbit_pada?->translatedFormat('d M Y'),
                'diperbarui_pada' => $artikel->updated_at?->translatedFormat('d M Y H:i'),
                'boleh_kirim' => in_array($artikel->status, [Article::STATUS_DRAF, Article::STATUS_REVISI], true),
                'tautan_publik' => $artikel->status === Article::STATUS_TERBIT
                    ? route('public.publikasi.detail', [
                        'tipe' => $artikel->tipe,
                        'slug' => $artikel->getTranslation('slug', 'id', false),
                    ])
                    : null,
            ]);

        return Inertia::render('Anggota/Karya', [
            'daftar' => $daftar,
        ]);
    }

    public function baru(Request $request): Response
    {
        $this->pastikanAnggota($request);

        return Inertia::render('Anggota/KaryaSunting', [
            'karya' => null,
            'pilihanTipe' => collect(Article::TIPE)->only(Article::TIPE_PUBLIK)->all(),
            'daftarKategori' => ArticleCategory::query()->aktif()->get(['id', 'nama']),
        ]);
    }

    public function sunting(Request $request, Article $karya): Response
    {
        $this->pastikanPemilik($request, $karya);

        return Inertia::render('Anggota/KaryaSunting', [
            'karya' => [
                'id' => $karya->id,
                'tipe' => $karya->tipe,
                'label_status' => $karya->labelStatus(),
                'status' => $karya->status,
                'kategori_id' => $karya->kategori_id,
                'judul' => $karya->getTranslations('judul'),
                'ringkasan' => $karya->getTranslations('ringkasan'),
                'konten' => $karya->getTranslations('konten'),
                'tag' => $karya->tags->map(fn (Tag $tag): string => (string) $tag->getTranslation('nama', 'id', false))->implode(', '),
                'catatan_review' => $karya->catatan_review,
                'boleh_kirim' => in_array($karya->status, [Article::STATUS_DRAF, Article::STATUS_REVISI], true),
            ],
            'pilihanTipe' => collect(Article::TIPE)->only(Article::TIPE_PUBLIK)->all(),
            'daftarKategori' => ArticleCategory::query()->aktif()->get(['id', 'nama']),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $this->pastikanAnggota($request);

        $data = $this->validasi($request);

        $karya = new Article;
        // Penulis SELALU pengguna yang sedang masuk — tidak dapat ditentukan
        // dari formulir, agar tidak ada yang menulis atas nama orang lain.
        $data['user_id'] = $request->user()->id;
        $data['status'] = Article::STATUS_DRAF;

        $this->redaksi->simpan($karya, $data, $request->user());

        return redirect()
            ->route('anggota.karya.sunting', $karya)
            ->with('sukses', 'Karya tersimpan sebagai draf. Kirim untuk review bila sudah siap.');
    }

    public function perbarui(Request $request, Article $karya): RedirectResponse
    {
        $this->pastikanPemilik($request, $karya);

        abort_if(
            $karya->status === Article::STATUS_TERBIT,
            403,
            'Karya yang sudah terbit tidak dapat disunting. Hubungi pengelola konten.',
        );

        $data = $this->validasi($request);
        unset($data['user_id'], $data['status']);

        $this->redaksi->simpan($karya, $data, $request->user());

        return back()->with('sukses', 'Karya berhasil diperbarui.');
    }

    public function kirim(Request $request, Article $karya): RedirectResponse
    {
        $this->pastikanPemilik($request, $karya);

        abort_unless(
            in_array($karya->status, [Article::STATUS_DRAF, Article::STATUS_REVISI], true),
            403,
            'Karya ini sudah dikirim atau sudah diterbitkan.',
        );

        $this->redaksi->kirimReview($karya, $request->user());

        return redirect()
            ->route('anggota.karya')
            ->with('sukses', 'Karyamu terkirim ke pengelola konten untuk ditinjau.');
    }

    /* ------------------------------------------------------------------ */
    /* Bagian dalam                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Hanya anggota terverifikasi (kader aktif / alumni) yang boleh menulis.
     */
    private function pastikanAnggota(Request $request): void
    {
        abort_unless(
            $request->user()?->member?->telahDiverifikasi() ?? false,
            403,
            'Hanya anggota yang sudah diverifikasi yang dapat mengirim karya.',
        );
    }

    private function pastikanPemilik(Request $request, Article $karya): void
    {
        $this->pastikanAnggota($request);

        abort_unless($karya->user_id === $request->user()->id, 403, 'Ini bukan karyamu.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'tipe' => ['required', 'in:'.implode(',', Article::TIPE_PUBLIK)],
            'kategori_id' => ['nullable', 'integer', 'exists:article_categories,id'],
            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'min:3', 'max:190'],
            'judul.en' => ['nullable', 'string', 'max:190'],
            'ringkasan' => ['nullable', 'array'],
            'ringkasan.id' => ['nullable', 'string', 'max:500'],
            'konten' => ['required', 'array'],
            'konten.id' => ['required', 'string', 'min:50'],
            'konten.en' => ['nullable', 'string'],
            'tag' => ['nullable', 'string', 'max:500'],
        ], [
            'konten.id.required' => 'Isi karya wajib diisi.',
            'konten.id.min' => 'Isi karya minimal :min karakter agar dapat dinilai pengelola.',
        ], [
            'judul.id' => 'judul',
            'konten.id' => 'isi karya',
        ]);
    }
}
