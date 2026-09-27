<?php

namespace App\Services;

use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRsvp;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kegiatan & presensi kader.
 *
 * TIGA JANJI yang dipegang kelas ini:
 *
 * 1. SATU ORANG, SATU CATATAN PER KEGIATAN. Diperkuat indeks unik pada tabel
 *    `attendance_records`, bukan sekadar pemeriksaan di kode — sehingga dua
 *    pindaian QR yang datang bersamaan tetap hanya menghasilkan satu catatan.
 *
 * 2. KEHADIRAN HANYA DARI KEGIATAN YANG SEDANG TERBUKA. Panitia harus membuka
 *    presensi lebih dulu. Tanpa aturan ini, siapa pun yang pernah melihat token
 *    QR bisa mendaftarkan dirinya hadir pada kegiatan yang sudah lama lewat.
 *
 * 3. QR DIPUTAR SETIAP KEGIATAN DIBUKA. Tangkapan layar QR kemarin tidak lagi
 *    berguna hari ini. Panitia juga bisa memutarnya di tengah kegiatan bila
 *    tautannya tersebar ke luar.
 *
 * RSVP dan KEHADIRAN sengaja disimpan terpisah: yang satu niat, yang satu
 * fakta. Rekap kehadiran tidak pernah ikut menghitung orang yang berjanji
 * datang lalu tidak muncul.
 *
 * POIN KONTRIBUSI DIBERIKAN DARI SINI, lewat `selaraskanKehadiran()` —
 * bukan oleh pemanggil. "Poin otomatis dari presensi" tidak boleh bergantung
 * pada setiap controller ingat memanggilnya.
 */
class Presensi
{
    /**
     * Presensi bergantung pada Kontribusi, BUKAN sebaliknya. Poin diberikan
     * di sini supaya "poin otomatis dari presensi" tidak bergantung pada
     * setiap pemanggil ingat memanggilnya.
     */
    public function __construct(private Kontribusi $kontribusi) {}

    /**
     * Simpan kegiatan baru. Selalu lahir sebagai draf.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpan(array $data, User $petugas): AttendanceActivity
    {
        $kegiatan = new AttendanceActivity;

        $kegiatan->kode = AttendanceActivity::kodeBaru();
        $kegiatan->jenis = $data['jenis'] ?? AttendanceActivity::JENIS_RAPAT;
        $kegiatan->unit_id = $data['unit_id'] ?? null;
        $kegiatan->mulai = Carbon::parse($data['mulai']);
        $kegiatan->selesai = isset($data['selesai']) ? Carbon::parse($data['selesai']) : null;
        $kegiatan->lokasi = $data['lokasi'] ?? null;
        $kegiatan->mode_presensi = $data['mode_presensi'] ?? AttendanceActivity::MODE_KEDUANYA;
        $kegiatan->poin = (int) ($data['poin'] ?? 0);
        $kegiatan->wajib = (bool) ($data['wajib'] ?? false);
        $kegiatan->status = AttendanceActivity::STATUS_DRAF;
        $kegiatan->dibuat_oleh = $petugas->id;

        // Kunci bahasa ditulis EKSPLISIT sebagai 'id'. Kalau diserahkan ke
        // `$model->judul = ...`, lokasi penulisan mengikuti bahasa antarmuka
        // pengurus — sehingga pengurus yang membuka panel versi Inggris akan
        // menulis judul Indonesia ke kunci 'en'.
        $kegiatan->setTranslations('judul', ['id' => $data['judul']]);
        $this->isiDeskripsi($kegiatan, $data['deskripsi'] ?? null);

        $kegiatan->save();

        return $kegiatan;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function perbarui(AttendanceActivity $kegiatan, array $data): AttendanceActivity
    {
        if ($kegiatan->status === AttendanceActivity::STATUS_BATAL) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Kegiatan yang sudah dibatalkan tidak dapat diubah lagi.',
            ]);
        }

        $kegiatan->jenis = $data['jenis'] ?? $kegiatan->jenis;
        $kegiatan->unit_id = $data['unit_id'] ?? null;
        $kegiatan->mulai = isset($data['mulai']) ? Carbon::parse($data['mulai']) : $kegiatan->mulai;
        $kegiatan->selesai = isset($data['selesai']) ? Carbon::parse($data['selesai']) : null;
        $kegiatan->lokasi = $data['lokasi'] ?? null;
        $kegiatan->mode_presensi = $data['mode_presensi'] ?? $kegiatan->mode_presensi;
        $kegiatan->poin = (int) ($data['poin'] ?? $kegiatan->poin);
        $kegiatan->wajib = (bool) ($data['wajib'] ?? false);

        $kegiatan->setTranslations('judul', ['id' => $data['judul']]);
        $this->isiDeskripsi($kegiatan, $data['deskripsi'] ?? null);

        $kegiatan->save();

        return $kegiatan;
    }

    /**
     * Buka presensi: kegiatan menjadi terbuka DAN token QR baru diterbitkan.
     *
     * Membuka ulang kegiatan yang sudah pernah terbuka juga memutar tokennya,
     * dengan sengaja — kalau tautan lama sudah tersebar, itu satu-satunya cara
     * mencabutnya.
     */
    public function buka(AttendanceActivity $kegiatan, ?User $petugas = null): AttendanceActivity
    {
        if ($kegiatan->status === AttendanceActivity::STATUS_BATAL) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Kegiatan yang sudah dibatalkan tidak dapat dibuka.',
            ]);
        }

        $kegiatan->status = AttendanceActivity::STATUS_TERBUKA;
        $this->putarToken($kegiatan);
        $kegiatan->save();

        activity()
            ->performedOn($kegiatan)
            ->withProperties(['petugas' => $petugas?->id])
            ->log('Presensi kegiatan dibuka');

        return $kegiatan;
    }

    /**
     * Putar token QR saja — untuk kegiatan yang sedang berlangsung dan
     * tautannya perlu dicabut tanpa menutup presensi.
     */
    public function putarQr(AttendanceActivity $kegiatan, ?User $petugas = null): AttendanceActivity
    {
        if (! $kegiatan->sedangTerbuka()) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Presensi belum dibuka, jadi belum ada token QR yang perlu diputar.',
            ]);
        }

        $this->putarToken($kegiatan);
        $kegiatan->save();

        activity()
            ->performedOn($kegiatan)
            ->withProperties(['petugas' => $petugas?->id])
            ->log('Token QR presensi diputar');

        return $kegiatan;
    }

    /**
     * Tutup presensi. Setelah ditutup, kehadiran tidak bisa dicatat lagi dan
     * rekapnya dianggap final.
     */
    public function tutup(AttendanceActivity $kegiatan, ?User $petugas = null): AttendanceActivity
    {
        $kegiatan->status = AttendanceActivity::STATUS_SELESAI;

        // Token dimatikan, bukan sekadar kegiatan ditandai selesai: kalau token
        // tetap hidup, `qrAktif()` bergantung pada satu pemeriksaan status saja.
        $kegiatan->qr_token = null;
        $kegiatan->qr_berlaku_sampai = null;
        $kegiatan->save();

        activity()
            ->performedOn($kegiatan)
            ->withProperties(['petugas' => $petugas?->id])
            ->log('Presensi kegiatan ditutup');

        return $kegiatan;
    }

    /**
     * Batalkan kegiatan tanpa menghapus jejaknya.
     */
    public function batalkan(AttendanceActivity $kegiatan, string $alasan, ?User $petugas = null): AttendanceActivity
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        $kegiatan->status = AttendanceActivity::STATUS_BATAL;
        $kegiatan->qr_token = null;
        $kegiatan->qr_berlaku_sampai = null;

        // Alasan pembatalan ditempelkan pada deskripsi, pada kunci bahasa
        // Indonesia yang sama — bukan pada bahasa antarmuka pengurus.
        $lama = (string) $kegiatan->getTranslation('deskripsi', 'id');
        $kegiatan->setTranslations('deskripsi', ['id' => trim($lama.' [Dibatalkan: '.trim($alasan).']')]);

        $kegiatan->save();

        activity()
            ->performedOn($kegiatan)
            ->withProperties(['petugas' => $petugas?->id, 'alasan' => $alasan])
            ->log('Kegiatan dibatalkan');

        return $kegiatan;
    }

    /* ------------------------------------------------------------------ */
    /* RSVP                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Catat atau ubah janji hadir.
     *
     * Jawaban terakhir yang menang: memanggil ulang tidak menumpuk baris.
     */
    public function rsvp(AttendanceActivity $kegiatan, Member $anggota, string $status, ?string $catatan = null): AttendanceRsvp
    {
        if (! in_array($status, array_keys(AttendanceRsvp::STATUS), true)) {
            throw ValidationException::withMessages(['status' => 'Jawaban kesediaan tidak dikenali.']);
        }

        if ($kegiatan->status === AttendanceActivity::STATUS_BATAL) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Kegiatan ini sudah dibatalkan.',
            ]);
        }

        if ($kegiatan->status === AttendanceActivity::STATUS_SELESAI) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Kegiatan ini sudah selesai — kesediaan tidak bisa diubah lagi.',
            ]);
        }

        $rsvp = AttendanceRsvp::query()->firstOrNew([
            'activity_id' => $kegiatan->id,
            'member_id' => $anggota->id,
        ]);

        $rsvp->status = $status;
        $rsvp->catatan = $catatan;
        $rsvp->save();

        return $rsvp;
    }

    /* ------------------------------------------------------------------ */
    /* Pencatatan kehadiran                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Catat kehadiran oleh pengurus (mode manual).
     *
     * Bila anggota sudah punya catatan pada kegiatan ini, catatannya DIPERBARUI
     * — bukan ditambah. Dengan begitu memperbaiki salah klik tidak pernah
     * menghasilkan dua baris untuk satu orang.
     */
    public function catatManual(
        AttendanceActivity $kegiatan,
        Member $anggota,
        string $status,
        User $petugas,
        ?string $catatan = null,
    ): AttendanceRecord {
        $this->pastikanBisaDicatat($kegiatan);
        $this->pastikanStatusSah($status);

        if (! in_array($kegiatan->mode_presensi, [AttendanceActivity::MODE_MANUAL, AttendanceActivity::MODE_KEDUANYA], true)) {
            throw ValidationException::withMessages([
                'metode' => 'Kegiatan ini hanya menerima presensi lewat QR.',
            ]);
        }

        return DB::transaction(function () use ($kegiatan, $anggota, $status, $petugas, $catatan): AttendanceRecord {
            $catatanPresensi = AttendanceRecord::query()->firstOrNew([
                'activity_id' => $kegiatan->id,
                'member_id' => $anggota->id,
            ]);

            $catatanPresensi->status = $status;
            $catatanPresensi->metode = AttendanceRecord::METODE_MANUAL;
            $catatanPresensi->catatan = $catatan;
            $catatanPresensi->dicatat_oleh = $petugas->id;
            $catatanPresensi->dicatat_pada = now();
            $catatanPresensi->save();

            // Poin diselaraskan, bukan sekadar DITAMBAH: bila status berubah
            // dari hadir menjadi izin, poin yang telanjur diberikan ikut
            // dicabut.
            $this->kontribusi->selaraskanKehadiran($catatanPresensi, $petugas);

            return $catatanPresensi;
        }, 3);
    }

    /**
     * Tandai banyak anggota sekaligus HADIR (atau TERLAMBAT).
     *
     * SENGAJA TIDAK MENERIMA STATUS "alpa". Mencontreng daftar lalu menandai
     * sisanya alpa lewat satu tombol akan menuduh orang yang sebenarnya sudah
     * mengirim izin. Alpa diberikan satu per satu, dengan sadar.
     *
     * @param  iterable<int, int>  $idAnggota
     * @return int  jumlah catatan yang dibuat atau diperbarui
     */
    public function catatMassal(
        AttendanceActivity $kegiatan,
        iterable $idAnggota,
        string $status,
        User $petugas,
    ): int {
        if (! in_array($status, AttendanceRecord::HADIR, true)) {
            throw ValidationException::withMessages([
                'status' => 'Tanda serentak hanya untuk Hadir atau Terlambat. Izin, sakit, dan alpa dicatat satu per satu.',
            ]);
        }

        $this->pastikanBisaDicatat($kegiatan);

        $anggota = Member::query()->whereIn('id', $idAnggota)->get();
        $jumlah = 0;

        DB::transaction(function () use ($kegiatan, $anggota, $status, $petugas, &$jumlah): void {
            foreach ($anggota as $orang) {
                $catatan = AttendanceRecord::query()->firstOrNew([
                    'activity_id' => $kegiatan->id,
                    'member_id' => $orang->id,
                ]);

                $catatan->status = $status;
                $catatan->metode = AttendanceRecord::METODE_MANUAL;
                $catatan->dicatat_oleh = $petugas->id;
                $catatan->dicatat_pada = now();
                $catatan->save();

                $this->kontribusi->selaraskanKehadiran($catatan, $petugas);

                $jumlah++;
            }
        }, 3);

        return $jumlah;
    }

    /**
     * Kader memindai QR dan mencatat kehadirannya sendiri.
     *
     * @throws ValidationException bila token tidak sah, presensi belum dibuka,
     *                             atau kehadirannya SUDAH tercatat.
     */
    public function scanQr(string $token, Member $anggota): AttendanceRecord
    {
        $kegiatan = AttendanceActivity::query()->where('qr_token', $token)->first();

        if ($kegiatan === null) {
            throw ValidationException::withMessages([
                'token' => 'Kode QR tidak dikenali. Pastikan kamu memindai kode dari panitia kegiatan.',
            ]);
        }

        if (! $kegiatan->qrAktif()) {
            throw ValidationException::withMessages([
                'token' => 'Kode QR ini sudah tidak berlaku. Minta panitia menampilkan kode terbaru.',
            ]);
        }

        $sudah = AttendanceRecord::query()
            ->where('activity_id', $kegiatan->id)
            ->where('member_id', $anggota->id)
            ->first();

        if ($sudah !== null) {
            throw ValidationException::withMessages([
                'presensi' => 'Kehadiranmu pada kegiatan ini sudah tercatat sebagai '
                    .strtolower($sudah->labelStatus()).' pada '
                    .$sudah->dicatat_pada?->translatedFormat('d F Y, H:i').'.',
            ]);
        }

        return DB::transaction(function () use ($kegiatan, $anggota): AttendanceRecord {
            $catatan = new AttendanceRecord;
            $catatan->activity_id = $kegiatan->id;
            $catatan->member_id = $anggota->id;
            $catatan->status = AttendanceRecord::STATUS_HADIR;
            $catatan->metode = AttendanceRecord::METODE_QR;
            $catatan->dicatat_pada = now();
            $catatan->save();

            // Pemberi poinnya adalah kader itu sendiri — ia yang memindai,
            // bukan pengurus yang mengirim.
            $this->kontribusi->selaraskanKehadiran($catatan);

            return $catatan;
        }, 3);
    }

    /**
     * Tandai sisa anggota yang belum punya catatan sebagai TANPA KETERANGAN.
     *
     * Tindakan sengaja, bukan bawaan: hanya dipakai setelah panitia yakin
     * daftarnya lengkap. Alasan wajib diisi dan tercatat di log aktivitas,
     * supaya penandaan massal tidak pernah jadi tindakan tanpa jejak.
     *
     * @param  iterable<int, int>  $idAnggota
     */
    public function tandaiAlpaSisa(
        AttendanceActivity $kegiatan,
        iterable $idAnggota,
        string $alasan,
        User $petugas,
    ): int {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penandaan wajib diisi — daftar kehadiran adalah catatan yang dipertanggungjawabkan.',
            ]);
        }

        $anggota = Member::query()->whereIn('id', $idAnggota)->get();
        $jumlah = 0;

        DB::transaction(function () use ($kegiatan, $anggota, $petugas, &$jumlah): void {
            foreach ($anggota as $orang) {
                $sudahAda = AttendanceRecord::query()
                    ->where('activity_id', $kegiatan->id)
                    ->where('member_id', $orang->id)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $catatan = new AttendanceRecord;
                $catatan->activity_id = $kegiatan->id;
                $catatan->member_id = $orang->id;
                $catatan->status = AttendanceRecord::STATUS_ALPA;
                $catatan->metode = AttendanceRecord::METODE_MANUAL;
                $catatan->dicatat_oleh = $petugas->id;
                $catatan->dicatat_pada = now();
                $catatan->save();

                $jumlah++;
            }
        }, 3);

        if ($jumlah > 0) {
            activity()
                ->performedOn($kegiatan)
                ->withProperties(['jumlah' => $jumlah, 'petugas' => $petugas->id, 'alasan' => $alasan])
                ->log('Sisa kehadiran ditandai tanpa keterangan');
        }

        return $jumlah;
    }

    /* ------------------------------------------------------------------ */
    /* Rekap                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Rekap satu kegiatan.
     *
     * @return array{hadir: int, terlambat: int, izin: int, sakit: int, alpa: int, belum: int, total_hadir: int, total_anggota: int, rsvp_hadir: int}
     */
    public function rekap(AttendanceActivity $kegiatan): array
    {
        $catatan = $kegiatan->presensi()->get();

        $anggotaAktif = Member::query()->where('status', Member::STATUS_AKTIF)->count();

        return [
            'hadir' => $catatan->where('status', AttendanceRecord::STATUS_HADIR)->count(),
            'terlambat' => $catatan->where('status', AttendanceRecord::STATUS_TERLAMBAT)->count(),
            'izin' => $catatan->where('status', AttendanceRecord::STATUS_IZIN)->count(),
            'sakit' => $catatan->where('status', AttendanceRecord::STATUS_SAKIT)->count(),
            'alpa' => $catatan->where('status', AttendanceRecord::STATUS_ALPA)->count(),
            // "Belum" = kader aktif yang belum punya catatan sama sekali, bukan
            // baris berstatus null — catatan tanpa status memang tidak ada.
            'belum' => max(0, $anggotaAktif - $catatan->count()),
            'total_hadir' => $catatan->whereIn('status', AttendanceRecord::HADIR)->count(),
            'total_anggota' => $anggotaAktif,
            'rsvp_hadir' => $kegiatan->rsvp()->where('status', AttendanceRsvp::STATUS_HADIR)->count(),
        ];
    }

    /**
     * Rekap kehadiran seorang kader.
     *
     * HANYA kegiatan yang sudah dibuka atau selesai yang dihitung. Kegiatan
     * yang masih draf belum pernah diumumkan, jadi ketidakhadiran di sana
     * bukan kesalahan kader.
     *
     * @return array{hadir: int, terlambat: int, izin: int, sakit: int, alpa: int, total_hadir: int, total_kegiatan: int, persen: float, poin: int}
     */
    public function rekapKader(Member $anggota, ?string $dari = null, ?string $sampai = null): array
    {
        $kegiatan = AttendanceActivity::query()
            ->whereIn('status', [AttendanceActivity::STATUS_TERBUKA, AttendanceActivity::STATUS_SELESAI])
            ->when($dari, fn ($q) => $q->where('mulai', '>=', Carbon::parse($dari)->startOfDay()))
            ->when($sampai, fn ($q) => $q->where('mulai', '<=', Carbon::parse($sampai)->endOfDay()))
            ->get();

        $catatan = AttendanceRecord::query()
            ->where('member_id', $anggota->id)
            ->whereIn('activity_id', $kegiatan->pluck('id'))
            ->get();

        $totalHadir = $catatan->whereIn('status', AttendanceRecord::HADIR)->count();
        $totalKegiatan = $kegiatan->count();

        return [
            'hadir' => $catatan->where('status', AttendanceRecord::STATUS_HADIR)->count(),
            'terlambat' => $catatan->where('status', AttendanceRecord::STATUS_TERLAMBAT)->count(),
            'izin' => $catatan->where('status', AttendanceRecord::STATUS_IZIN)->count(),
            'sakit' => $catatan->where('status', AttendanceRecord::STATUS_SAKIT)->count(),
            'alpa' => $catatan->where('status', AttendanceRecord::STATUS_ALPA)->count(),
            'total_hadir' => $totalHadir,
            'total_kegiatan' => $totalKegiatan,
            'persen' => $totalKegiatan > 0 ? round(($totalHadir / $totalKegiatan) * 100, 1) : 0.0,
            'poin' => (int) $kegiatan->whereIn('id', $catatan->whereIn('status', AttendanceRecord::HADIR)->pluck('activity_id'))->sum('poin'),
        ];
    }

    /* ------------------------------------------------------------------ */

    private function putarToken(AttendanceActivity $kegiatan): void
    {
        $kegiatan->qr_token = AttendanceActivity::tokenQrBaru();
        $kegiatan->qr_berlaku_sampai = now()->addMinutes(AttendanceActivity::QR_BERLAKU_MENIT);
    }

    /**
     * Isi kolom deskripsi hanya bila ada isinya.
     *
     * Mengosongkan kolom JSON membuatnya bernilai null, bukan string kosong —
     * supaya halaman publik tidak pernah menampilkan paragraf kosong.
     *
     * @throws ValidationException
     */
    private function isiDeskripsi(AttendanceActivity $kegiatan, ?string $deskripsi): void
    {
        if ($deskripsi !== null && trim($deskripsi) !== '') {
            $kegiatan->setTranslations('deskripsi', ['id' => trim($deskripsi)]);

            return;
        }

        $kegiatan->deskripsi = null;
    }

    /**
     * @throws ValidationException
     */
    private function pastikanBisaDicatat(AttendanceActivity $kegiatan): void
    {
        if ($kegiatan->status === AttendanceActivity::STATUS_BATAL) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Kegiatan ini sudah dibatalkan.',
            ]);
        }

        if (! $kegiatan->sedangTerbuka()) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Presensi kegiatan ini belum dibuka atau sudah ditutup.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function pastikanStatusSah(string $status): void
    {
        if (! in_array($status, array_keys(AttendanceRecord::STATUS), true)) {
            throw ValidationException::withMessages([
                'status' => 'Status kehadiran tidak dikenali.',
            ]);
        }
    }
}
