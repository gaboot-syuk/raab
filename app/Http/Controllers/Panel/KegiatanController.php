<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRsvp;
use App\Models\OrganisationUnit;
use App\Services\Presensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kegiatan yang presensinya dicatat.
 *
 * Halaman ini soal MENYIAPKAN: membuat kegiatan, membukanya, dan menampilkan
 * kode QR. Pencatatan kehadirannya sendiri ada di PresensiController — dua
 * pekerjaan berbeda yang sering dilakukan orang berbeda (Sekretaris menyusun
 * agenda, panitia mencontreng di pintu).
 */
class KegiatanController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Presensi $presensi) {}

    public function index(Request $request): Response
    {
        $jenis = $request->string('jenis')->toString();
        $status = $request->string('status')->toString();

        $daftar = AttendanceActivity::query()
            ->with('unit:id,nama')
            ->withCount([
                'presensi as total_catatan',
                'presensi as total_hadir' => fn ($q) => $q->hadir(),
                'rsvp as total_rsvp' => fn ($q) => $q->where('status', AttendanceRsvp::STATUS_HADIR),
            ])
            ->when($jenis, fn ($q) => $q->jenis($jenis))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('mulai')
            ->limit(100)
            ->get()
            ->map(fn (AttendanceActivity $k): array => [
                'id' => $k->id,
                'kode' => $k->kode,
                'judul' => $k->judulTeks(),
                'jenis' => $k->jenis,
                'label_jenis' => $k->labelJenis(),
                'status' => $k->status,
                'label_status' => $k->labelStatus(),
                'mode_presensi' => $k->mode_presensi,
                'label_mode' => $k->labelMode(),
                'unit' => $k->unit?->nama,
                'mulai' => $k->mulai?->translatedFormat('d M Y, H:i'),
                'lokasi' => $k->lokasi,
                'poin' => $k->poin,
                'wajib' => $k->wajib,
                'total_hadir' => $k->total_hadir,
                'total_catatan' => $k->total_catatan,
                'total_rsvp' => $k->total_rsvp,
                'qr_aktif' => $k->qrAktif(),
                'qr_berlaku_sampai' => $k->qr_berlaku_sampai?->translatedFormat('d M Y, H:i'),
                // Tautan QR diberikan apa adanya supaya panitia bisa membagikan
                // tautannya di grup WA bila proyektor bermasalah.
                'tautan_qr' => $k->qrAktif() ? url('/presensi/scan/'.$k->qr_token) : null,
            ])->all();

        return Inertia::render('Panel/Kegiatan/Index', [
            'daftar' => $daftar,
            'pilihanJenis' => AttendanceActivity::JENIS,
            'pilihanMode' => AttendanceActivity::MODE,
            'pilihanStatus' => AttendanceActivity::STATUS,
            'pilihanUnit' => OrganisationUnit::query()->orderBy('urutan')->get()
                ->map(fn (OrganisationUnit $u): array => ['id' => $u->id, 'nama' => $u->nama])->all(),
            'saringan' => ['jenis' => $jenis, 'status' => $status],
            'catatan' => 'Kehadiran hanya dapat dicatat saat presensi sedang DIBUKA. Membuka presensi selalu menerbitkan kode QR baru, sehingga tangkapan layar kode lama tidak lagi berlaku.',
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $kegiatan = $this->presensi->simpan($data, $request->user());

        return back()->with('sukses', 'Kegiatan '.$kegiatan->kode.' disimpan sebagai draf. Buka presensinya saat acara dimulai.');
    }

    public function perbarui(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        $data = $this->validasi($request);

        return $this->jalankan(
            fn () => $this->presensi->perbarui($kegiatan, $data),
            'Kegiatan diperbarui.',
        );
    }

    public function buka(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->presensi->buka($kegiatan, $request->user()),
            'Presensi dibuka dan kode QR baru diterbitkan. Kode QR sebelumnya tidak berlaku lagi.',
        );
    }

    public function putarQr(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->presensi->putarQr($kegiatan, $request->user()),
            'Kode QR diputar. Tautan lama mati, tampilkan kode yang baru.',
        );
    }

    public function tutup(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->presensi->tutup($kegiatan, $request->user()),
            'Presensi ditutup. Kehadiran tidak dapat dicatat lagi dan rekapnya dianggap final.',
        );
    }

    public function batalkan(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:300'],
        ]);

        return $this->jalankan(
            fn () => $this->presensi->batalkan($kegiatan, $data['alasan'], $request->user()),
            'Kegiatan dibatalkan. Catatannya tetap tersimpan sebagai riwayat.',
        );
    }

    /**
     * Halaman kode QR untuk ditampilkan di proyektor — berdiri sendiri dan
     * siap dicetak.
     */
    public function qr(AttendanceActivity $kegiatan): \Illuminate\View\View
    {
        return view('public.presensi-qr', [
            'kegiatan' => $kegiatan,
            'tautan' => $kegiatan->qrAktif() ? url('/presensi/scan/'.$kegiatan->qr_token) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:180'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'jenis' => ['required', Rule::in(array_keys(AttendanceActivity::JENIS))],
            'unit_id' => ['nullable', 'integer', 'exists:organisation_units,id'],
            'mulai' => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'lokasi' => ['nullable', 'string', 'max:190'],
            'mode_presensi' => ['required', Rule::in(array_keys(AttendanceActivity::MODE))],
            // Poin dibatasi: satu kegiatan tidak boleh mengalahkan seluruh
            // kegiatan lain dalam sebulan hanya karena salah ketik.
            'poin' => ['nullable', 'integer', 'min:0', 'max:50'],
            'wajib' => ['boolean'],
        ], [], [
            'judul' => 'judul kegiatan',
            'mode_presensi' => 'mode presensi',
        ]);
    }
}
