<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Services\Prestasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ResponsHttp;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Antrean verifikasi prestasi kader.
 *
 * YANG MENUNGGU DIPERIKSA DILETAKKAN PALING ATAS. Hampir semua pekerjaan di
 * halaman ini adalah memutuskan klaim yang baru masuk; daftar prestasi yang
 * sudah beres hanya sesekali dibuka untuk mencari.
 */
class PrestasiController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Prestasi $prestasi) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $tingkat = $request->string('tingkat')->toString();
        $kategoriId = $request->integer('kategori');

        $daftar = Achievement::query()
            ->with([
                'anggota:id,nama_lengkap,nomor_anggota,unit_id',
                'anggota.unit:id,nama',
                'kategori:id,nama',
                'pemeriksa:id,name',
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($tingkat, fn ($q) => $q->tingkat($tingkat))
            ->when($kategoriId, fn ($q) => $q->where('achievement_category_id', $kategoriId))
            // Belum diperiksa di atas, lalu yang terbaru.
            ->orderByRaw("CASE status WHEN 'diajukan' THEN 0 WHEN 'terverifikasi' THEN 1 ELSE 2 END")
            ->orderByDesc('tanggal')
            ->limit(150)
            ->get()
            ->map(fn (Achievement $p): array => [
                'id' => $p->id,
                'anggota' => $p->anggota?->nama_lengkap ?? '—',
                'nomor_anggota' => $p->anggota?->nomor_anggota,
                'unit' => $p->anggota?->unit?->nama,
                'judul' => $p->judulTeks(),
                'kategori' => $p->kategori?->namaTeks(),
                'penyelenggara' => $p->penyelenggara,
                'tingkat' => $p->tingkat,
                'label_tingkat' => $p->labelTingkat(),
                'peringkat' => $p->peringkat,
                'label_peringkat' => $p->labelPeringkat(),
                'tanggal' => $p->tanggal?->translatedFormat('d M Y'),
                'status' => $p->status,
                'label_status' => $p->labelStatus(),
                'poin' => $p->poin(),
                'poin_diberikan' => $p->terverifikasi() ? $p->poin() : 0,
                'unggulan' => $p->unggulan,
                'tampil_publik' => $p->tampil_publik,
                'sertifikat_url' => $p->sertifikat_media_id
                    ? Media::query()->find($p->sertifikat_media_id)?->getUrl()
                    : null,
                'tautan_bukti' => $p->tautan_bukti,
                'catatan_verifikasi' => $p->catatan_verifikasi,
                'pemeriksa' => $p->pemeriksa?->name,
                'diverifikasi_pada' => $p->diverifikasi_pada?->translatedFormat('d M Y, H:i'),
            ])->all();

        return Inertia::render('Panel/Prestasi/Index', [
            'daftar' => $daftar,
            'kategori' => AchievementCategory::query()->urut()->get()
                ->map(fn (AchievementCategory $k): array => [
                    'id' => $k->id,
                    'kode' => $k->kode,
                    'nama' => $k->namaTeks(),
                    'aktif' => $k->aktif,
                    'jumlah_prestasi' => $k->prestasi()->count(),
                    'jumlah_terverifikasi' => $k->prestasi()->terverifikasi()->count(),
                ])->all(),
            'pilihanTingkat' => Achievement::TINGKAT,
            'pilihanPeringkat' => Achievement::PERINGKAT,
            'pilihanStatus' => Achievement::STATUS,
            'ringkasan' => [
                'menunggu' => Achievement::query()->menunggu()->count(),
                'terverifikasi' => Achievement::query()->terverifikasi()->count(),
                'ditolak' => Achievement::query()->where('status', Achievement::STATUS_DITOLAK)->count(),
                'unggulan' => Achievement::query()->terverifikasi()->where('unggulan', true)->count(),
            ],
            'saringan' => ['status' => $status, 'tingkat' => $tingkat, 'kategori' => $kategoriId ?: null],
            'catatan' => 'Prestasi yang diklaim kader BELUM TENTANG dianggap benar. Hanya yang terverifikasi yang tayang di halaman publik dan menghasilkan poin — dan poin itu ikut dicabut bila verifikasinya dibatalkan.',
        ]);
    }

    public function verifikasi(Request $request, Achievement $prestasi): RedirectResponse
    {
        $data = $request->validate([
            'catatan_verifikasi' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->prestasi->verifikasi($prestasi, $request->user(), $data['catatan_verifikasi'] ?? null),
            'Prestasi '.$prestasi->judulTeks().' terverifikasi'
                .($prestasi->poin() > 0 ? ' dan '.$prestasi->poin().' poin diberikan.' : '.'),
        );
    }

    public function tolak(Request $request, Achievement $prestasi): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->prestasi->tolak($prestasi, $request->user(), $data['alasan']),
            'Prestasi ditolak. Bila sebelumnya sempat terverifikasi, poinnya ikut dicabut.',
        );
    }

    public function unggulan(Request $request, Achievement $prestasi): RedirectResponse
    {
        $data = $request->validate([
            'unggulan' => ['required', 'boolean'],
        ]);

        return $this->jalankan(
            fn () => $this->prestasi->jadikanUnggulan($prestasi, (bool) $data['unggulan'], $request->user()),
            $data['unggulan'] ? 'Prestasi ditandai unggulan dan akan tampil di beranda.' : 'Prestasi tidak lagi unggulan.',
        );
    }

    /**
     * Hapus pengajuan yang BELUM diperiksa — untuk klaim sampah atau salah kirim.
     *
     * Prestasi yang sudah pernah diperiksa selalu ditolak lewat `tolak()`,
     * bukan dihapus: jejak pemeriksaannya harus tetap ada.
     */
    public function hapus(Achievement $prestasi): RedirectResponse
    {
        $judul = $prestasi->judulTeks();

        return $this->jalankan(
            fn () => $this->prestasi->hapusPengajuan($prestasi),
            'Pengajuan '.$judul.' dihapus.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Kategori                                                            */
    /* ------------------------------------------------------------------ */
    public function simpanKategori(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:300'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ]);

        $kategori = $this->prestasi->simpanKategori($data['nama'], $data);

        return back()->with('sukses', 'Kategori '.$kategori->namaTeks().' dibuat.');
    }

    public function perbaruiKategori(Request $request, AchievementCategory $kategori): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:300'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'aktif' => ['boolean'],
        ]);

        $kategori->urutan = (int) ($data['urutan'] ?? 0);
        $kategori->aktif = (bool) ($data['aktif'] ?? $kategori->aktif);
        $kategori->setTranslations('nama', ['id' => $data['nama']]);

        if (($data['keterangan'] ?? null) !== null) {
            $kategori->setTranslations('keterangan', ['id' => $data['keterangan']]);
        }

        $kategori->save();

        return back()->with('sukses', 'Kategori prestasi diperbarui.');
    }

    /**
     * Hapus kategori yang BELUM dipakai.
     *
     * Kategori yang sudah dipakai dinonaktifkan, bukan dihapus — kalau tidak,
     * prestasi lama kehilangan kategorinya tanpa ada yang menyadari.
     */
    public function hapusKategori(AchievementCategory $kategori): RedirectResponse
    {
        if ($kategori->prestasi()->exists()) {
            return back()->with('galat', 'Kategori ini sudah dipakai '.$kategori->prestasi()->count()
                .' prestasi. Nonaktifkan saja, agar prestasi lama tidak kehilangan kategorinya.');
        }

        $kategori->delete();

        return back()->with('sukses', 'Kategori prestasi dihapus.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Ekspor daftar prestasi sebagai CSV.
     *
     * CSV, bukan xlsx: container ini tidak punya ekstensi zip yang dibutuhkan
     * pembuat berkas Excel.
     */
    public function ekspor(Request $request): ResponsHttp
    {
        $status = $request->string('status')->toString();

        $baris = Achievement::query()
            ->with(['anggota:id,nama_lengkap,nomor_anggota', 'kategori:id,nama'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('tanggal')
            ->limit(5000)
            ->get();

        $bersih = fn (string $teks): string => '"'.str_replace('"', '""', $teks).'"';

        $isi = "\xEF\xBB\xBFsep=;\n";
        $isi .= "Tanggal;Anggota;Nomor Anggota;Prestasi;Kategori;Penyelenggara;Tingkat;Peringkat;Poin;Status;Unggulan;Tampil Publik\n";

        foreach ($baris as $p) {
            $isi .= implode(';', [
                $bersih($p->tanggal?->format('Y-m-d') ?? '-'),
                $bersih($p->anggota?->nama_lengkap ?? '-'),
                $bersih($p->anggota?->nomor_anggota ?? '-'),
                $bersih($p->judulTeks()),
                $bersih($p->kategori?->namaTeks() ?? '-'),
                $bersih($p->penyelenggara ?? '-'),
                $bersih($p->labelTingkat()),
                $bersih($p->labelPeringkat()),
                $bersih((string) ($p->terverifikasi() ? $p->poin() : 0)),
                $bersih($p->labelStatus()),
                $bersih($p->unggulan ? 'Ya' : 'Tidak'),
                $bersih($p->tampil_publik ? 'Ya' : 'Tidak'),
            ])."\n";
        }

        $nama = 'prestasi-kader'.($status ? '-'.$status : '').'.csv';

        return response($isi, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }
}
