<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Models\MediaLibrary;
use App\Models\Member;
use App\Services\Prestasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Prestasi dari sisi kader: mengajukan dan memantau keputusannya.
 *
 * Kader melihat STATUS pengajuannya, bukan hanya daftar. Klaim yang ditolak
 * beserta alasan pengurusnya ditampilkan apa adanya — kalau alasannya
 * disembunyikan, kader akan mengajukan hal yang sama lagi.
 *
 * Tanpa izin Spatie: yang menentukan hanya kepemilikan prestasinya sendiri.
 */
class PrestasiController extends Controller
{
    use MenjalankanAksi;

    /** Batas ukuran sertifikat (KB). */
    private const MAKS_KB = 5120;

    /** Jenis berkas sertifikat yang diterima. */
    private const MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(private Prestasi $prestasi) {}

    public function index(Request $request): Response
    {
        $anggota = $request->user()->member;

        if ($anggota === null) {
            return Inertia::render('Anggota/Prestasi', [
                'anggota' => false,
                'daftar' => [],
                'kategori' => [],
                'pilihanTingkat' => Achievement::TINGKAT,
                'pilihanPeringkat' => Achievement::PERINGKAT,
                'rekap' => null,
            ]);
        }

        $daftar = Achievement::query()
            ->with('kategori:id,nama')
            ->where('member_id', $anggota->id)
            ->orderByDesc('tanggal')
            ->limit(100)
            ->get()
            ->map(fn (Achievement $p): array => [
                'id' => $p->id,
                'judul' => $p->judulTeks(),
                'deskripsi' => $p->getTranslation('deskripsi', 'id') ?: null,
                'kategori_id' => $p->achievement_category_id,
                'kategori' => $p->kategori?->namaTeks(),
                'penyelenggara' => $p->penyelenggara,
                'tingkat' => $p->tingkat,
                'label_tingkat' => $p->labelTingkat(),
                'peringkat' => $p->peringkat,
                'label_peringkat' => $p->labelPeringkat(),
                'tanggal' => $p->tanggal?->format('Y-m-d'),
                'tanggal_teks' => $p->tanggal?->translatedFormat('d F Y'),
                'status' => $p->status,
                'label_status' => $p->labelStatus(),
                'catatan_verifikasi' => $p->catatan_verifikasi,
                // Poin yang AKAN didapat bila terverifikasi — ditampilkan
                // supaya kader tahu apa yang masih menggantung.
                'poin_diharapkan' => $p->poin(),
                'poin_diberikan' => $p->terverifikasi() ? $p->poin() : 0,
                'unggulan' => $p->unggulan,
                'tampil_publik' => $p->tampil_publik,
                // Boleh disunting selama belum diperiksa.
                'boleh_disunting' => ! $p->terverifikasi() && $p->diverifikasi_pada === null,
                'sertifikat_url' => $p->sertifikat_media_id
                    ? Media::query()->find($p->sertifikat_media_id)?->getUrl()
                    : null,
                'tautan_bukti' => $p->tautan_bukti,
            ])->all();

        return Inertia::render('Anggota/Prestasi', [
            'anggota' => true,
            'daftar' => $daftar,
            'kategori' => AchievementCategory::query()->aktif()->urut()->get()
                ->map(fn (AchievementCategory $k): array => ['id' => $k->id, 'nama' => $k->namaTeks()])->all(),
            'pilihanTingkat' => Achievement::TINGKAT,
            'pilihanPeringkat' => Achievement::PERINGKAT,
            'rekap' => $this->prestasi->rekap($anggota),
            'catatan' => 'Prestasi yang kamu ajukan belum dianggap benar sampai Sekretaris atau Konten Manager memeriksa sertifikatnya. Poin dan penayangan di halaman publik baru muncul setelah terverifikasi.',
        ]);
    }

    public function ajukan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $anggota = $request->user()->member;

        if ($anggota === null || $anggota->status !== Member::STATUS_AKTIF) {
            return back()->with('galat', 'Pengajuan prestasi hanya untuk kader aktif.');
        }

        if ($request->hasFile('sertifikat')) {
            $data['sertifikat_media_id'] = $this->simpanSertifikat($request);
        }

        $prestasi = $this->prestasi->ajukan($anggota, $data, $request->user());

        return back()->with('sukses', 'Prestasi "'.$prestasi->judulTeks().'" diajukan. Pengurus akan memeriksa sertifikatnya.');
    }

    public function perbarui(Request $request, Achievement $prestasi): RedirectResponse
    {
        $anggota = $request->user()->member;

        if ($anggota === null || $prestasi->member_id !== $anggota->id) {
            return back()->with('galat', 'Prestasi ini bukan milikmu.');
        }

        $data = $this->validasi($request);

        if ($request->hasFile('sertifikat')) {
            $data['sertifikat_media_id'] = $this->simpanSertifikat($request);
        }

        return $this->jalankan(
            fn () => $this->prestasi->perbarui($prestasi, $data, $request->user()),
            'Pengajuan diperbarui dan menunggu diperiksa kembali.',
        );
    }

    /**
     * Sakelar milik kader: sembunyikan prestasi dari halaman publik.
     */
    public function tampil(Request $request, Achievement $prestasi): RedirectResponse
    {
        $anggota = $request->user()->member;

        if ($anggota === null || $prestasi->member_id !== $anggota->id) {
            return back()->with('galat', 'Prestasi ini bukan milikmu.');
        }

        $data = $request->validate(['tampil_publik' => ['required', 'boolean']]);

        $this->prestasi->aturTampilPublik($prestasi, (bool) $data['tampil_publik']);

        return back()->with('sukses', $data['tampil_publik']
            ? 'Prestasi ini akan tampil di halaman publik.'
            : 'Prestasi ini disembunyikan dari halaman publik. Poinnya tetap dihitung.');
    }

    /**
     * Tarik pengajuan yang belum diperiksa.
     */
    public function hapus(Request $request, Achievement $prestasi): RedirectResponse
    {
        $anggota = $request->user()->member;

        if ($anggota === null || $prestasi->member_id !== $anggota->id) {
            return back()->with('galat', 'Prestasi ini bukan milikmu.');
        }

        return $this->jalankan(
            fn () => $this->prestasi->hapusPengajuan($prestasi),
            'Pengajuan ditarik.',
        );
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'achievement_category_id' => ['nullable', 'integer', 'exists:achievement_categories,id'],
            'penyelenggara' => ['nullable', 'string', 'max:190'],
            'tingkat' => ['required', Rule::in(array_keys(Achievement::TINGKAT))],
            'peringkat' => ['required', Rule::in(array_keys(Achievement::PERINGKAT))],
            'tanggal' => ['required', 'date'],
            'tautan_bukti' => ['nullable', 'url', 'max:500'],
            'tampil_publik' => ['boolean'],
            'sertifikat' => ['nullable', 'file', 'max:'.self::MAKS_KB, 'mimetypes:'.implode(',', self::MIME)],
        ], [], [
            'achievement_category_id' => 'kategori prestasi',
            'sertifikat' => 'sertifikat',
        ]);
    }

    /**
     * Simpan sertifikat ke pustaka media dan kembalikan id-nya.
     *
     * Kader tidak memegang izin `media.upload`, jadi berkasnya ditempelkan
     * lewat jalur ini ke koleksi dokumen — bukan dibiarkan menempel bebas pada
     * prestasinya, supaya seluruh berkas tetap terkelola di satu tempat.
     */
    private function simpanSertifikat(Request $request): int
    {
        $media = MediaLibrary::induk()
            ->addMedia($request->file('sertifikat'))
            ->usingName('Sertifikat '.$request->user()->name)
            ->toMediaCollection('dokumen', 'public');

        return (int) $media->id;
    }
}
