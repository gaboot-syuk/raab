<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\Member;
use App\Services\Presensi;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman hasil pemindaian QR presensi.
 *
 * BERDIRI SENDIRI (tanpa layout publik dan tanpa Inertia) karena halaman ini
 * dibuka dari kamera ponsel: kader memindai kode di proyektor, lalu langsung
 * mendarat di sini. Yang dibutuhkan hanya satu jawaban secepat mungkin —
 * "kehadiranmu tercatat" atau "tidak, karena ini".
 *
 * Kalau kader belum masuk, middleware `auth` mengalihkannya ke halaman masuk
 * dengan menyimpan alamat tujuan — setelah masuk ia kembali ke sini dan
 * kehadirannya tetap tercatat. Itu memang perilaku yang diinginkan: memindai
 * QR tidak boleh gagal hanya karena sesinya sudah kedaluwarsa.
 */
class PresensiController extends Controller
{
    public function __construct(private Presensi $presensi) {}

    public function scan(Request $request, string $token): View
    {
        $anggota = $request->user()->member;

        if ($anggota === null) {
            return $this->hasil(false, 'Akunmu belum terhubung ke data anggota, jadi kehadiran tidak dapat dicatat. Hubungi Sekretaris rayon.');
        }

        if ($anggota->status !== Member::STATUS_AKTIF) {
            return $this->hasil(false, 'Presensi hanya untuk kader aktif. Status keanggotaanmu saat ini: '.$anggota->labelStatus().'.');
        }

        // Kegiatan dibaca lebih dulu supaya halaman tetap bisa menyebut nama
        // kegiatannya walau kehadirannya ditolak.
        $kegiatan = AttendanceActivity::query()->where('qr_token', $token)->first();

        try {
            $catatan = $this->presensi->scanQr($token, $anggota);
        } catch (ValidationException $e) {
            return $this->hasil(false, collect($e->errors())->flatten()->first(), $kegiatan);
        }

        activity()
            ->performedOn($catatan)
            ->withProperties(['kegiatan' => $catatan->activity_id, 'metode' => 'qr'])
            ->log('Kehadiran dicatat lewat QR');

        return $this->hasil(true, 'Kehadiranmu tercatat. Terima kasih sudah datang!', $catatan->kegiatan, $catatan->status);
    }

    private function hasil(bool $berhasil, string $pesan, ?AttendanceActivity $kegiatan = null, ?string $status = null): View
    {
        return view('public.presensi-hasil', [
            'berhasil' => $berhasil,
            'pesan' => $pesan,
            'kegiatan' => $kegiatan,
            'labelStatus' => $status !== null ? (AttendanceRecord::STATUS[$status] ?? $status) : null,
        ]);
    }
}
