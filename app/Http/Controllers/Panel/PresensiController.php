<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\Member;
use App\Services\Presensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ResponsHttp;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekap & pencatatan kehadiran untuk satu kegiatan.
 *
 * Halaman ini dipakai panitia DI PINTU: membuka kegiatan yang sedang
 * berlangsung, mencontreng yang datang, mencatat izin satu per satu.
 *
 * Karena itu daftarnya memuat SELURUH kader aktif — bukan hanya yang sudah
 * punya catatan. Daftar yang hanya menampilkan yang sudah hadir membuat
 * panitia tidak punya cara menandai orang yang baru datang.
 */
class PresensiController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Presensi $presensi) {}

    public function index(Request $request): Response
    {
        $kegiatanId = $request->integer('kegiatan');

        $kegiatan = AttendanceActivity::query()
            ->with('unit:id,nama')
            ->when($kegiatanId, fn ($q) => $q->whereKey($kegiatanId))
            ->orderByDesc('mulai')
            ->first();

        if ($kegiatan === null) {
            return Inertia::render('Panel/Presensi/Index', [
                'kegiatan' => null,
                'daftarKegiatan' => AttendanceActivity::query()->orderByDesc('mulai')->limit(50)->get()
                    ->map(fn (AttendanceActivity $k): array => [
                        'id' => $k->id,
                        'label' => $k->kode.' — '.$k->judulTeks(),
                        'status' => $k->status,
                    ])->all(),
                'peserta' => [],
                'rekap' => null,
                'pilihanStatus' => AttendanceRecord::STATUS,
                'catatan' => 'Belum ada kegiatan yang bisa dipilih. Buat kegiatannya lebih dulu di menu Kegiatan.',
            ]);
        }

        $catatan = $kegiatan->presensi()->get()->keyBy('member_id');
        $rsvp = $kegiatan->rsvp()->get()->keyBy('member_id');

        $peserta = Member::query()
            ->where('status', Member::STATUS_AKTIF)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn (Member $m): array => [
                'id' => $m->id,
                'nama' => $m->nama_lengkap,
                'nomor_anggota' => $m->nomor_anggota,
                // Label untuk dibaca, status mentah untuk disaring — menyaring
                // berdasarkan label membuat filter diam-diam rusak begitu
                // teks labelnya diubah.
                'rsvp' => $rsvp->has($m->id) ? $rsvp->get($m->id)->labelStatus() : null,
                'rsvp_status' => $rsvp->get($m->id)?->status,
                'status' => $catatan->get($m->id)?->status,
                'label_status' => $catatan->get($m->id)?->labelStatus(),
                'metode' => $catatan->get($m->id)?->labelMetode(),
                'dicatat_pada' => $catatan->get($m->id)?->dicatat_pada?->translatedFormat('d M Y, H:i'),
            ])->all();

        return Inertia::render('Panel/Presensi/Index', [
            'kegiatan' => [
                'id' => $kegiatan->id,
                'kode' => $kegiatan->kode,
                'judul' => $kegiatan->judulTeks(),
                'label_jenis' => $kegiatan->labelJenis(),
                'label_status' => $kegiatan->labelStatus(),
                'status' => $kegiatan->status,
                'label_mode' => $kegiatan->labelMode(),
                'mulai' => $kegiatan->mulai?->translatedFormat('d M Y, H:i'),
                'lokasi' => $kegiatan->lokasi,
                'unit' => $kegiatan->unit?->nama,
                'poin' => $kegiatan->poin,
                'qr_aktif' => $kegiatan->qrAktif(),
                'boleh_dicatat' => $kegiatan->sedangTerbuka(),
            ],
            'daftarKegiatan' => AttendanceActivity::query()->orderByDesc('mulai')->limit(50)->get()
                ->map(fn (AttendanceActivity $k): array => [
                    'id' => $k->id,
                    'label' => $k->kode.' — '.$k->judulTeks(),
                    'status' => $k->status,
                ])->all(),
            'peserta' => $peserta,
            'rekap' => $this->presensi->rekap($kegiatan),
            'pilihanStatus' => AttendanceRecord::STATUS,
            'catatan' => 'Menandai seseorang hadir saat presensi belum dibuka akan ditolak. Selama presensi terbuka, kehadiran lewat QR dicatat kader masing-masing — panitia hanya perlu menangani yang izin atau sakit.',
        ]);
    }

    /**
     * Catat atau perbaiki kehadiran satu orang.
     */
    public function catat(Request $request, AttendanceActivity $kegiatan, Member $anggota): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(AttendanceRecord::STATUS))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->presensi->catatManual($kegiatan, $anggota, $data['status'], $request->user(), $data['catatan'] ?? null),
            $anggota->nama_lengkap.' dicatat sebagai '.(AttendanceRecord::STATUS[$data['status']] ?? $data['status']).'.',
        );
    }

    /**
     * Tandai sekaligus banyak anggota sebagai hadir — pekerjaan paling sering
     * dilakukan panitia saat rapat kecil.
     */
    public function catatMassal(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        $data = $request->validate([
            'anggota' => ['required', 'array', 'min:1'],
            'anggota.*' => ['integer', 'exists:members,id'],
            'status' => ['required', Rule::in(AttendanceRecord::HADIR)],
        ], [], ['anggota' => 'daftar anggota']);

        $hasil = $this->presensi->catatMassal($kegiatan, $data['anggota'], $data['status'], $request->user());

        return back()->with('sukses', $hasil.' kehadiran dicatat sebagai '
            .strtolower(AttendanceRecord::STATUS[$data['status']] ?? $data['status']).'.');
    }

    /**
     * Tandai sisa yang belum punya catatan sebagai tanpa keterangan.
     *
     * Selalu disertai alasan, dan tidak pernah dijalankan sendiri oleh sistem.
     */
    public function tandaiSisa(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        $data = $request->validate([
            'anggota' => ['required', 'array', 'min:1'],
            'anggota.*' => ['integer', 'exists:members,id'],
            'alasan' => ['required', 'string', 'max:300'],
        ], [], ['anggota' => 'daftar anggota']);

        $hasil = $this->presensi->tandaiAlpaSisa($kegiatan, $data['anggota'], $data['alasan'], $request->user());

        return back()->with('sukses', $hasil.' anggota ditandai tanpa keterangan, beserta alasannya.');
    }

    /**
     * Rekap kehadiran satu kegiatan sebagai CSV.
     *
     * CSV, bukan xlsx: container ini tidak punya ekstensi zip yang dibutuhkan
     * pembuat berkas Excel.
     */
    public function ekspor(AttendanceActivity $kegiatan): ResponsHttp
    {
        $catatan = $kegiatan->presensi()->get()->keyBy('member_id');

        $baris = Member::query()
            ->where('status', Member::STATUS_AKTIF)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function (Member $m) use ($catatan): array {
                $satu = $catatan->get($m->id);

                return [
                    $m->nomor_anggota ?? '-',
                    $m->nama_lengkap,
                    $satu?->labelStatus() ?? 'Belum Ada Catatan',
                    $satu?->labelMetode() ?? '-',
                    $satu?->dicatat_pada?->format('Y-m-d H:i') ?? '-',
                ];
            })->all();

        $isi = "\xEF\xBB\xBFsep=;\n";
        $isi .= "Nomor Anggota;Nama;Kehadiran;Metode;Waktu\n";

        foreach ($baris as $kolom) {
            $isi .= implode(';', array_map(fn (string $c): string => '"'.str_replace('"', '""', $c).'"', $kolom))."\n";
        }

        $rekap = $this->presensi->rekap($kegiatan);
        $isi .= "\n";
        $isi .= 'Hadir;'.$rekap['hadir']."\n";
        $isi .= 'Terlambat;'.$rekap['terlambat']."\n";
        $isi .= 'Izin;'.$rekap['izin']."\n";
        $isi .= 'Sakit;'.$rekap['sakit']."\n";
        $isi .= 'Tanpa Keterangan;'.$rekap['alpa']."\n";
        $isi .= 'Belum Ada Catatan;'.$rekap['belum']."\n";

        $nama = 'presensi-'.$kegiatan->kode.'.csv';

        return response($isi, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }
}
