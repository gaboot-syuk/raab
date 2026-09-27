<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRsvp;
use App\Models\Member;
use App\Services\Presensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kegiatan & presensi dari sisi kader.
 *
 * Berisi dua hal yang berbeda dan sengaja dipisah tampilannya:
 *  - KESEDIAAN HADIR untuk kegiatan yang belum berlangsung, dan
 *  - RIWAYAT KEHADIRAN untuk kegiatan yang sudah lewat.
 *
 * Tanpa izin Spatie: yang menentukan hanya kepemilikan datanya sendiri. Seorang
 * kader tidak boleh melihat — apalagi mengubah — kehadiran kader lain.
 */
class KegiatanController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Presensi $presensi) {}

    public function index(Request $request): Response
    {
        $anggota = $request->user()->member;

        if ($anggota === null) {
            return Inertia::render('Anggota/Kegiatan', [
                'anggota' => false,
                'akanDatang' => [],
                'riwayat' => [],
                'rekap' => null,
            ]);
        }

        $rsvp = AttendanceRsvp::query()
            ->where('member_id', $anggota->id)
            ->get()
            ->keyBy('activity_id');

        $catatan = AttendanceRecord::query()
            ->where('member_id', $anggota->id)
            ->get()
            ->keyBy('activity_id');

        $akanDatang = AttendanceActivity::query()
            // Hanya kegiatan yang presensinya sudah DIBUKA. Kegiatan yang masih
            // draf belum diumumkan, jadi tidak boleh muncul di sini.
            ->terbuka()
            ->orderBy('mulai')
            ->limit(30)
            ->get()
            ->map(fn (AttendanceActivity $k): array => [
                'id' => $k->id,
                'kode' => $k->kode,
                'judul' => $k->judulTeks(),
                'label_jenis' => $k->labelJenis(),
                'mulai' => $k->mulai?->translatedFormat('d F Y, H:i'),
                'lokasi' => $k->lokasi,
                'poin' => $k->poin,
                'wajib' => $k->wajib,
                'mode' => $k->labelMode(),
                'sedang_terbuka' => $k->sedangTerbuka(),
                'rsvp' => $rsvp->get($k->id)?->status,
                'label_rsvp' => $rsvp->get($k->id)?->labelStatus(),
                'catatan_rsvp' => $rsvp->get($k->id)?->catatan,
                'sudah_presensi' => $catatan->has($k->id),
                'label_presensi' => $catatan->get($k->id)?->labelStatus(),
            ])->values()->all();

        $riwayat = AttendanceActivity::query()
            ->whereIn('status', [AttendanceActivity::STATUS_TERBUKA, AttendanceActivity::STATUS_SELESAI])
            ->orderByDesc('mulai')
            ->limit(50)
            ->get()
            ->map(fn (AttendanceActivity $k): array => [
                'id' => $k->id,
                'kode' => $k->kode,
                'judul' => $k->judulTeks(),
                'mulai' => $k->mulai?->translatedFormat('d F Y'),
                'poin' => $k->poin,
                'status' => $catatan->get($k->id)?->status,
                'label_status' => $catatan->get($k->id)?->labelStatus(),
                'metode' => $catatan->get($k->id)?->labelMetode(),
                'dihitung_hadir' => $catatan->get($k->id)?->dihitungHadir() ?? false,
            ])->all();

        return Inertia::render('Anggota/Kegiatan', [
            'anggota' => true,
            'akanDatang' => $akanDatang,
            'riwayat' => $riwayat,
            'pilihanRsvp' => AttendanceRsvp::STATUS,
            'rekap' => $this->presensi->rekapKader($anggota),
            'catatan' => 'Kehadiran dicatat panitia di pintu, atau olehmu sendiri dengan memindai kode QR yang ditampilkan panitia. Kode QR hanya berlaku saat kegiatan sedang berlangsung.',
        ]);
    }

    /**
     * Kader menyatakan kesediaan hadir.
     */
    public function rsvp(Request $request, AttendanceActivity $kegiatan): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(AttendanceRsvp::STATUS))],
            'catatan' => ['nullable', 'string', 'max:300'],
        ]);

        $anggota = $request->user()->member;

        if ($anggota === null || $anggota->status !== Member::STATUS_AKTIF) {
            return back()->with('galat', 'Kesediaan hadir hanya untuk kader aktif.');
        }

        return $this->jalankan(
            fn () => $this->presensi->rsvp($kegiatan, $anggota, $data['status'], $data['catatan'] ?? null),
            'Kesediaanmu dicatat: '.(AttendanceRsvp::STATUS[$data['status']] ?? $data['status']).'.',
        );
    }
}
