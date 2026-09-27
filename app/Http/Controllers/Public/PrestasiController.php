<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Halaman prestasi kader — PUBLIK, hanya baca.
 *
 * YANG TAMPIL HANYA prestasi yang SUDAH terverifikasi pengurus DAN masih
 * diizinkan kadernya. Dua syarat itu harus terpenuhi bersamaan:
 * halaman ini menyiarkan nama kader, jadi keputusan pemilik datanya tidak
 * boleh diabaikan hanya karena pengurus sudah memverifikasi.
 *
 * Prestasi yang ditolak atau masih menunggu TIDAK PERNAH bocor ke sini.
 */
class PrestasiController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tingkat = $request->string('tingkat')->toString();
        $kategoriId = $request->integer('kategori');

        $prestasi = Achievement::query()
            ->tayangPublik()
            ->when($tingkat, fn ($q) => $q->tingkat($tingkat))
            ->when($kategoriId, fn ($q) => $q->where('achievement_category_id', $kategoriId))
            ->with(['anggota:id,nama_lengkap,slug,unit_id,profil_publik', 'anggota.unit:id,nama', 'kategori:id,nama'])
            ->orderByDesc('tanggal')
            ->limit(120)
            ->get()
            ->map(fn (Achievement $p): array => [
                'id' => $p->id,
                'judul' => $p->judulTeks(),
                'deskripsi' => $p->getTranslation('deskripsi', 'id'),
                'anggota' => $p->anggota?->nama_lengkap,
                // Profil kader hanya ditautkan bila memang dibuka pemiliknya.
                // URL disusun langsung, bukan lewat route(): rute publik dibungkus
                // middleware lokalisasi mcamara dan route() perlu tahu bahasanya.
                'tautan_kader' => $p->anggota?->profil_publik && $p->anggota?->slug
                    ? url('/prestasi/kader/'.$p->anggota->slug)
                    : null,
                'unit' => $p->anggota?->unit?->nama,
                'kategori' => $p->kategori?->namaTeks(),
                'penyelenggara' => $p->penyelenggara,
                'label_tingkat' => $p->labelTingkat(),
                'label_peringkat' => $p->labelPeringkat(),
                'tingkat' => $p->tingkat,
                'tanggal' => $p->tanggal?->translatedFormat('d F Y'),
                'unggulan' => $p->unggulan,
                'sertifikat_url' => $p->sertifikat_media_id
                    ? Media::query()->find($p->sertifikat_media_id)?->getUrl()
                    : null,
            ])->all();

        return view('public.prestasi', [
            'situs' => Pengaturan::semua(),
            'prestasi' => $prestasi,
            'kategori' => AchievementCategory::query()->aktif()->urut()->get()
                ->map(fn (AchievementCategory $k): array => ['id' => $k->id, 'nama' => $k->namaTeks()])->all(),
            'pilihanTingkat' => Achievement::TINGKAT,
            'saringan' => ['tingkat' => $tingkat, 'kategori' => $kategoriId ?: null],
            // Angka ringkas di kepala halaman: berapa prestasi dan berapa kader
            // yang menyumbangnya. Dihitung dari data yang sama dengan daftarnya
            // supaya kepala dan isi tidak pernah berbeda.
            'ringkasan' => [
                'prestasi' => count($prestasi),
                'kader' => count(array_unique(array_filter(array_column($prestasi, 'anggota')))),
            ],
            'papanTingkat' => $this->sebaranTingkat(),
        ]);
    }

    /**
     * Sebaran prestasi per tingkat — dari SELURUH prestasi tayang, bukan dari
     * hasil saringan, supaya angkanya tetap bermakna saat disaring.
     *
     * @return array<string, int>
     */
    private function sebaranTingkat(): array
    {
        $semua = Achievement::query()->tayangPublik()->get();
        $hasil = [];

        foreach (Achievement::TINGKAT as $kunci => $label) {
            $hasil[$label] = $semua->where('tingkat', $kunci)->count();
        }

        return $hasil;
    }
}
