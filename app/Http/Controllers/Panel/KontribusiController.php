<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\AttendanceActivity;
use App\Models\ContributionPoint;
use App\Models\Member;
use App\Services\Kontribusi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ResponsHttp;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Poin kontribusi: buku besarnya, penyesuaian manual, dan papan peringkat.
 *
 * BUKU BESAR, BUKAN SATU ANGKA. Halaman ini sengaja menampilkan daftar
 * peristiwanya, bukan hanya totalnya. Pertanyaan yang paling sering muncul dari
 * kader adalah "kok poin saya segitu?" — dan itu hanya bisa dijawab kalau
 * asalnya kelihatan.
 */
class KontribusiController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Kontribusi $kontribusi) {}

    public function index(Request $request): Response
    {
        $periode = $request->string('periode')->toString();
        $sumber = $request->string('sumber')->toString();
        $anggotaId = $request->integer('anggota');

        $catatan = ContributionPoint::query()
            ->with([
                'anggota:id,nama_lengkap',
                'kegiatan:id,kode,judul',
                'pemberi:id,name',
                'pembatal:id,name',
            ])
            ->when($periode, fn ($q) => $q->periode($periode))
            ->when($sumber, fn ($q) => $q->sumber($sumber))
            ->when($anggotaId, fn ($q) => $q->where('member_id', $anggotaId))
            ->orderByDesc('terjadi_pada')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (ContributionPoint $p): array => [
                'id' => $p->id,
                'anggota' => $p->anggota?->nama_lengkap ?? '—',
                'sumber' => $p->sumber,
                'label_sumber' => $p->labelSumber(),
                'poin' => $p->poin,
                'keterangan' => $p->keterangan,
                'periode' => $p->periode_label,
                'terjadi_pada' => $p->terjadi_pada?->translatedFormat('d M Y'),
                'kegiatan' => $p->kegiatan?->kode,
                'pemberi' => $p->pemberi?->name,
                'dibatalkan' => $p->dibatalkan(),
                'alasan_pembatalan' => $p->alasan_pembatalan,
                'pembatal' => $p->pembatal?->name,
            ])->all();

        return Inertia::render('Panel/Kontribusi/Index', [
            'catatan' => $catatan,
            'papanPeringkat' => $this->kontribusi->peringkat($periode ?: null, 10),
            'pilihanSumber' => ContributionPoint::SUMBER,
            'pilihanPeriode' => $this->kontribusi->periodeTersedia(),
            'pilihanAnggota' => Member::query()
                ->whereIn('status', [Member::STATUS_AKTIF, Member::STATUS_ALUMNI])
                ->orderBy('nama_lengkap')
                ->get()
                ->map(fn (Member $m): array => [
                    'id' => $m->id,
                    'nama' => $m->nama_lengkap,
                    'total' => $this->kontribusi->total($m, $periode ?: null),
                ])->all(),
            'kegiatanBelumDisinkron' => AttendanceActivity::query()
                ->whereIn('status', [AttendanceActivity::STATUS_TERBUKA, AttendanceActivity::STATUS_SELESAI])
                ->where('poin', '>', 0)
                ->orderByDesc('mulai')
                ->limit(20)
                ->get()
                ->map(fn (AttendanceActivity $k): array => [
                    'id' => $k->id,
                    'label' => $k->kode.' — '.$k->judulTeks().' ('.$k->poin.' poin)',
                ])->all(),
            'batasPenyesuaian' => ContributionPoint::BATAS_PENYESUAIAN,
            'saringan' => ['periode' => $periode, 'sumber' => $sumber, 'anggota' => $anggotaId ?: null],
            'catatanHalaman' => 'Poin diberikan otomatis saat kehadiran dicatat. Bila status kehadiran diperbaiki menjadi izin atau sakit, poin yang telanjur diberikan ikut dicabut. Baris poin tidak pernah dihapus — yang keliru dibatalkan beserta alasannya.',
        ]);
    }

    /**
     * Penyesuaian manual oleh pengurus.
     */
    public function sesuaikan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'poin' => ['required', 'integer', 'not_in:0'],
            'alasan' => ['required', 'string', 'max:300'],
            'periode' => ['nullable', 'string', 'max:7'],
        ], [], ['member_id' => 'anggota', 'poin' => 'jumlah poin']);

        $anggota = Member::query()->findOrFail($data['member_id']);

        return $this->jalankan(
            fn () => $this->kontribusi->sesuaikan(
                $anggota,
                (int) $data['poin'],
                $data['alasan'],
                $request->user(),
                $data['periode'] ?? null,
            ),
            'Penyesuaian '.($data['poin'] > 0 ? '+' : '').$data['poin'].' poin untuk '.$anggota->nama_lengkap.' tercatat.',
        );
    }

    public function batalkan(Request $request, ContributionPoint $poin): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:300'],
        ]);

        return $this->jalankan(
            fn () => $this->kontribusi->batalkan($poin, $data['alasan'], $request->user()),
            'Baris poin dibatalkan. Catatannya tetap tersimpan sebagai riwayat.',
        );
    }

    /**
     * Beri poin untuk seluruh peserta yang hadir pada satu kegiatan.
     *
     * Aman dijalankan berulang — yang sudah punya poin dilewati.
     */
    public function sinkronkan(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        $hasil = $this->kontribusi->dariPresensi($kegiatan, $request->user());

        $bagian = [$hasil['diberi'].' diberikan'];

        if ($hasil['sudah_ada'] > 0) {
            $bagian[] = $hasil['sudah_ada'].' sudah punya poin';
        }

        // Dibedakan dari "sudah punya poin": kader yang izin memang tidak
        // berhak, dan tidak pernah diberi — bukan sedang dilewati.
        if ($hasil['tanpa_poin'] > 0) {
            $bagian[] = $hasil['tanpa_poin'].' tidak berhak (izin/sakit/alpa)';
        }

        if ($hasil['dibatalkan'] > 0) {
            $bagian[] = $hasil['dibatalkan'].' dicabut karena statusnya bukan kehadiran';
        }

        return back()->with('sukses', 'Poin '.$kegiatan->kode.': '.implode(', ', $bagian).'.');
    }

    /**
     * Ekspor buku besar poin sebagai CSV.
     *
     * CSV, bukan xlsx: container ini tidak punya ekstensi zip yang dibutuhkan
     * pembuat berkas Excel.
     */
    public function ekspor(Request $request): ResponsHttp
    {
        $periode = $request->string('periode')->toString();

        $baris = ContributionPoint::query()
            ->with(['anggota:id,nama_lengkap', 'pemberi:id,name'])
            ->when($periode, fn ($q) => $q->periode($periode))
            ->orderByDesc('terjadi_pada')
            ->limit(5000)
            ->get();

        $isi = "\xEF\xBB\xBFsep=;\n";
        $isi .= "Periode;Tanggal;Anggota;Sumber;Poin;Keterangan;Diberikan Oleh;Status\n";

        // SEMUA kolom dikutip, termasuk yang berupa angka. Berkas CSV yang
        // sebagian kolomnya dikutip dan sebagian tidak menyulitkan pembacaan
        // ulang oleh skrip — dan angka pun bisa berisi pemisah ribuan.
        $bersih = fn (string $teks): string => '"'.str_replace('"', '""', $teks).'"';

        foreach ($baris as $satu) {
            $isi .= implode(';', [
                $bersih((string) $satu->periode_label),
                $bersih($satu->terjadi_pada?->format('Y-m-d') ?? '-'),
                $bersih($satu->anggota?->nama_lengkap ?? '-'),
                $bersih($satu->labelSumber()),
                // Baris yang dibatalkan tetap diekspor dengan poin 0, supaya
                // jumlah kolom Poin di berkas selalu sama dengan total nyata.
                $bersih((string) $satu->poinSah()),
                $bersih($satu->keterangan),
                $bersih($satu->pemberi?->name ?? '-'),
                $bersih($satu->dibatalkan() ? 'Dibatalkan: '.$satu->alasan_pembatalan : 'Sah'),
            ])."\n";
        }

        $papan = $this->kontribusi->peringkat($periode ?: null, 50);

        $isi .= "\nPapan Peringkat".($periode ? ' '.$periode : '')."\n";
        $isi .= "Peringkat;Anggota;Poin;Peristiwa;Total Hadir\n";

        foreach ($papan as $p) {
            $isi .= implode(';', [
                $bersih((string) $p['peringkat']),
                $bersih($p['nama']),
                $bersih((string) $p['poin']),
                $bersih((string) $p['jumlah_peristiwa']),
                $bersih((string) $p['total_hadir']),
            ])."\n";
        }

        $nama = 'poin-kontribusi'.($periode ? '-'.$periode : '').'.csv';

        return response($isi, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }
}
