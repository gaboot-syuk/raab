<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\ContributionPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;
use Symfony\Component\HttpFoundation\Response as ResponsHttp;

/**
 * Laporan lintas modul: kegiatan, presensi, poin, prestasi.
 *
 * SATU HAL YANG MENENTUKAN SELURUH HALAMAN INI: izin dibaca DUA LAPIS.
 *
 * Lapis pertama `reports.generate`/`reports.export` — siapa yang boleh membuka
 * halaman laporan. Lapis kedua adalah izin data aslinya: `activities.view`,
 * `attendances.view`, `points.view`, `achievements.view`.
 *
 * Keduanya diperlukan. Kalau hanya lapis pertama yang diperiksa, sebuah peran
 * yang sengaja TIDAK diberi `points.view` bisa membaca rincian poin tiap kader
 * lewat halaman laporan — dan pembatasan di modul aslinya jadi tidak berarti.
 * Jadi laporan tidak pernah menjadi pintu belakang menuju data yang di tempat
 * lain sengaja ditutup.
 *
 * Akibat yang jujur: Bendahara memegang `reports.generate` tetapi tidak
 * memegang satu pun izin data di atas, sehingga halaman ini kosong baginya.
 * Itu memang disengaja; laporan yang menjadi haknya ada di
 * `/panel/keuangan/laporan`.
 */
class LaporanController extends Controller
{
    /**
     * Daftar laporan beserta izin data yang dibutuhkan.
     *
     * @var array<string, array{label: string, keterangan: string, izin: string}>
     */
    private const JENIS = [
        'kegiatan' => [
            'label' => 'Laporan Kegiatan',
            'keterangan' => 'Seluruh kegiatan beserta jumlah peserta yang tercatat hadir.',
            'izin' => 'activities.view',
        ],
        'presensi' => [
            'label' => 'Laporan Presensi',
            'keterangan' => 'Rincian kehadiran per kader per kegiatan, termasuk metode pencatatannya.',
            'izin' => 'attendances.view',
        ],
        'poin' => [
            'label' => 'Laporan Poin Kontribusi',
            'keterangan' => 'Buku besar poin: dari mana poinnya datang, dan poin mana yang dibatalkan.',
            'izin' => 'points.view',
        ],
        'prestasi' => [
            'label' => 'Laporan Prestasi',
            'keterangan' => 'Prestasi kader beserta tingkat, peringkat, dan keadaan verifikasinya.',
            'izin' => 'achievements.view',
        ],
    ];

    public function index(Request $request): ResponsInertia
    {
        $pengguna = $request->user();

        $tersedia = [];
        $tertutup = [];

        foreach (self::JENIS as $kunci => $jenis) {
            $butir = [
                'kunci' => $kunci,
                'label' => $jenis['label'],
                'keterangan' => $jenis['keterangan'],
                'izin' => $jenis['izin'],
            ];

            if ($pengguna->can($jenis['izin'])) {
                $tersedia[] = $butir;
            } else {
                $tertutup[] = $butir;
            }
        }

        return Inertia::render('Panel/Laporan/Index', [
            'tersedia' => $tersedia,
            'tertutup' => $tertutup,
            'ringkasan' => $this->ringkasan($pengguna),
            'bolehEkspor' => $pengguna->can('reports.export'),
            'catatan' => 'Laporan yang muncul di sini hanya yang datanya memang boleh kamu baca. Sebuah laporan tidak pernah menjadi pintu belakang menuju data yang di modul aslinya sengaja dibatasi — kalau sebuah laporan tidak muncul, berarti kamu tidak memegang izin data mentahnya.',
        ]);
    }

    /**
     * Ekspor satu jenis laporan sebagai CSV.
     *
     * Rentang tanggal memakai `dari`/`sampai`; keduanya boleh kosong, dan
     * artinya "seluruh riwayat" — bukan "hari ini", karena itu akan membuat
     * orang mengira datanya hilang.
     */
    public function ekspor(Request $request, string $jenis): ResponsHttp
    {
        abort_unless(array_key_exists($jenis, self::JENIS), 404, 'Jenis laporan tidak dikenal.');

        $pengguna = $request->user();
        $izin = self::JENIS[$jenis]['izin'];

        // Dua lapis, sama seperti halaman: izin ekspor DAN izin datanya.
        abort_unless($pengguna->can('reports.export'), 403);
        abort_unless($pengguna->can($izin), 403);

        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
        ]);

        $dari = isset($data['dari']) ? Carbon::parse($data['dari'])->startOfDay() : null;
        $sampai = isset($data['sampai']) ? Carbon::parse($data['sampai'])->endOfDay() : null;

        [$kepala, $baris] = match ($jenis) {
            'kegiatan' => $this->laporanKegiatan($dari, $sampai),
            'presensi' => $this->laporanPresensi($dari, $sampai),
            'poin' => $this->laporanPoin($dari, $sampai),
            'prestasi' => $this->laporanPrestasi($dari, $sampai),
        };

        $bersih = fn (?string $teks): string => '"'.str_replace('"', '""', (string) $teks).'"';

        $isi = "\xEF\xBB\xBFsep=;\n";
        $isi .= implode(';', array_map($bersih, $kepala))."\n";

        foreach ($baris as $b) {
            $isi .= implode(';', array_map($bersih, $b))."\n";
        }

        return response($isi, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-'.$jenis.'.csv"',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Ringkasan halaman                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ringkasan(/* User */ $pengguna): array
    {
        $hasil = [];

        if ($pengguna->can('activities.view')) {
            $hasil[] = [
                'label' => 'Kegiatan tercatat',
                'nilai' => AttendanceActivity::query()->count(),
            ];
        }

        if ($pengguna->can('attendances.view')) {
            $hadir = AttendanceRecord::query()->whereIn('status', AttendanceRecord::HADIR)->count();
            $semua = AttendanceRecord::query()->count();

            $hasil[] = ['label' => 'Catatan kehadiran', 'nilai' => $semua];
            $hasil[] = ['label' => 'Hadir (termasuk terlambat)', 'nilai' => $hadir];
        }

        if ($pengguna->can('points.view')) {
            $hasil[] = [
                'label' => 'Poin sah terkumpul',
                'nilai' => (int) ContributionPoint::query()->sah()->sum('poin'),
            ];
        }

        if ($pengguna->can('achievements.view')) {
            $hasil[] = [
                'label' => 'Prestasi terverifikasi',
                'nilai' => Achievement::query()->where('status', Achievement::STATUS_TERVERIFIKASI)->count(),
            ];
        }

        return $hasil;
    }

    /* ------------------------------------------------------------------ */
    /* Isi tiap laporan                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function laporanKegiatan(?Carbon $dari, ?Carbon $sampai): array
    {
        $kegiatan = AttendanceActivity::query()
            ->when($dari, fn ($q) => $q->where('mulai', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('mulai', '<=', $sampai))
            ->orderBy('mulai')
            ->get();

        $baris = [];

        foreach ($kegiatan as $k) {
            $catatan = $k->presensi;
            $hadir = $catatan->whereIn('status', AttendanceRecord::HADIR)->count();

            $baris[] = [
                $k->kode,
                $k->judulTeks(),
                $k->jenis,
                $k->mulai?->format('Y-m-d H:i') ?? '-',
                $k->lokasi ?? '-',
                $k->mode_presensi,
                (string) $k->poin,
                $k->wajib ? 'Ya' : 'Tidak',
                (string) $hadir,
                (string) $catatan->count(),
            ];
        }

        return [
            ['Kode', 'Judul', 'Jenis', 'Mulai', 'Lokasi', 'Mode Presensi', 'Poin', 'Wajib', 'Hadir', 'Tercatat'],
            $baris,
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function laporanPresensi(?Carbon $dari, ?Carbon $sampai): array
    {
        $catatan = AttendanceRecord::query()
            ->with(['kegiatan', 'anggota'])
            ->when($dari, fn ($q) => $q->where('created_at', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('created_at', '<=', $sampai))
            ->orderBy('activity_id')
            ->limit(20000)
            ->get();

        $baris = [];

        foreach ($catatan as $c) {
            $baris[] = [
                $c->kegiatan?->kode ?? '-',
                $c->kegiatan?->judulTeks() ?? '-',
                $c->kegiatan?->mulai?->format('Y-m-d') ?? '-',
                $c->anggota?->nama_lengkap ?? '-',
                $c->anggota?->nomor_anggota ?? '-',
                $c->status,
                $c->metode,
                $c->dicatat_pada?->format('Y-m-d H:i') ?? '-',
                $c->catatan ?? '-',
            ];
        }

        return [
            ['Kode Kegiatan', 'Kegiatan', 'Tanggal', 'Nama Kader', 'Nomor Anggota', 'Status', 'Metode', 'Dicatat Pada', 'Catatan'],
            $baris,
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function laporanPoin(?Carbon $dari, ?Carbon $sampai): array
    {
        $poin = ContributionPoint::query()
            ->with(['anggota', 'pemberi'])
            ->when($dari, fn ($q) => $q->where('terjadi_pada', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('terjadi_pada', '<=', $sampai))
            ->orderByDesc('terjadi_pada')
            ->limit(20000)
            ->get();

        $baris = [];

        foreach ($poin as $p) {
            $baris[] = [
                $p->anggota?->nama_lengkap ?? '-',
                $p->anggota?->nomor_anggota ?? '-',
                (string) $p->poin,
                $p->sumber,
                $p->periode_label ?? '-',
                $p->keterangan ?? '-',
                $p->terjadi_pada?->format('Y-m-d') ?? '-',
                // Poin yang dibatalkan TIDAK dihapus dari buku besar, jadi
                // laporan harus tetap menampilkannya — kalau tidak, jumlahnya
                // tidak akan pernah bisa ditelusuri.
                $p->dibatalkan_pada !== null ? 'Dibatalkan' : 'Sah',
                $p->alasan_pembatalan ?? '-',
                $p->pemberi?->name ?? '-',
            ];
        }

        return [
            ['Nama Kader', 'Nomor Anggota', 'Poin', 'Sumber', 'Periode', 'Keterangan', 'Terjadi Pada', 'Keadaan', 'Alasan Pembatalan', 'Diberikan Oleh'],
            $baris,
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function laporanPrestasi(?Carbon $dari, ?Carbon $sampai): array
    {
        $prestasi = Achievement::query()
            ->with(['anggota', 'kategori'])
            ->when($dari, fn ($q) => $q->where('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('tanggal', '<=', $sampai))
            ->orderByDesc('tanggal')
            ->limit(20000)
            ->get();

        $baris = [];

        foreach ($prestasi as $p) {
            $baris[] = [
                $p->anggota?->nama_lengkap ?? '-',
                $p->anggota?->nomor_anggota ?? '-',
                $p->judulTeks(),
                $p->kategori?->namaTeks() ?? '-',
                $p->tingkat,
                $p->peringkat,
                (string) $p->poin(),
                $p->tanggal?->format('Y-m-d') ?? '-',
                $p->status,
                $p->unggulan ? 'Ya' : 'Tidak',
                $p->tampil_publik ? 'Ya' : 'Tidak',
            ];
        }

        return [
            ['Nama Kader', 'Nomor Anggota', 'Prestasi', 'Kategori', 'Tingkat', 'Peringkat', 'Poin', 'Tanggal', 'Status', 'Unggulan', 'Tampil Publik'],
            $baris,
        ];
    }
}
