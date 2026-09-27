<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Achievement;
use App\Models\AttendanceActivity;
use App\Models\AttendanceRecord;
use App\Models\ContributionPoint;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Poin kontribusi kader.
 *
 * EMPAT JANJI yang dipegang kelas ini:
 *
 * 1. POIN TIDAK PERNAH GANDA. Setiap peristiwa punya `sidik` unik di basis
 *    data, jadi menjalankan pemberian poin berkali-kali tetap menghasilkan satu
 *    baris. Ini bukan pemeriksaan di kode yang bisa dilewati permintaan
 *    bersamaan, melainkan aturan tabel.
 *
 * 2. POIN HANYA UNTUK YANG DIHITUNG HADIR. Izin, sakit, dan tanpa keterangan
 *    dicatat dan dihargai secara sosial, tetapi tidak menghasilkan poin —
 *    kalau semua dapat poin, "aktif" kehilangan artinya.
 *
 * 3. PEMBATALAN, BUKAN PENGHAPUSAN. Baris poin yang keliru dibatalkan beserta
 *    alasan dan pelakunya, sama seperti void pada buku kas. Riwayat yang
 *    hilang tidak bisa dipertanggungjawabkan.
 *
 * 4. TOTAL DIJUMLAHKAN, BUKAN DISIMPAN. Tidak ada kolom "total_poin" di tabel
 *    anggota. Dua angka yang harus selalu sama tidak perlu disimpan dua kali —
 *    dan kalau disimpan, cepat atau lambat keduanya berbeda.
 */
class Kontribusi
{
    /* ------------------------------------------------------------------ */
    /* Pemberian poin                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Selaraskan poin dengan keadaan seorang peserta pada satu kegiatan.
     *
     * Dipanggil setiap kali kehadiran dicatat atau diubah, sehingga:
     *  - yang hadir mendapat poin,
     *  - yang statusnya DIUBAH dari hadir menjadi izin/sakit/alpa kehilangan
     *    poinnya.
     *
     * Yang kedua itu yang mudah terlupa. Tanpa itu, panitia yang memperbaiki
     * salah klik dari "hadir" menjadi "izin" akan meninggalkan poin yang sudah
     * terlanjur diberikan.
     *
     * @return string diberi|dibatalkan|tetap|tanpa_poin
     */
    public function selaraskanKehadiran(AttendanceRecord $catatan, ?User $petugas = null): string
    {        $kegiatan = $catatan->kegiatan;

        if ($kegiatan === null) {
            return 'tetap';
        }

        $sidik = ContributionPoint::sidikPresensi($kegiatan->id, $catatan->member_id);
        $adaSebelumnya = ContributionPoint::query()->where('sidik', $sidik)->first();

        // Status bukan kehadiran: cabut poin yang mungkin sudah ada.
        if (! $catatan->dihitungHadir()) {
            if ($adaSebelumnya !== null && ! $adaSebelumnya->dibatalkan()) {
                $this->batalkan(
                    $adaSebelumnya,
                    'Kehadiran diubah menjadi '.strtolower($catatan->labelStatus()).'.',
                    $petugas,
                );

                return 'dibatalkan';
            }

            // "tanpa_poin" BEDA dari "tetap". Kalau keduanya disatukan, laporan
            // panel akan menulis "sudah punya poin" untuk kader yang izin —
            // padahal ia memang tidak berhak dan tidak pernah diberi.
            return 'tanpa_poin';
        }

        if ($kegiatan->poin <= 0) {
            return 'tanpa_poin';
        }

        if ($adaSebelumnya !== null) {
            // Sudah pernah diberi lalu dibatalkan (mis. sempat diubah ke izin,
            // lalu dikembalikan lagi ke hadir) — hidupkan kembali.
            if ($adaSebelumnya->dibatalkan()) {
                $adaSebelumnya->forceFill([
                    'dibatalkan_pada' => null,
                    'alasan_pembatalan' => null,
                    'dibatalkan_oleh' => null,
                ])->save();

                return 'diberi';
            }

            return 'tetap';
        }

        $this->catat(
            anggota: $catatan->anggota,
            sumber: ContributionPoint::SUMBER_PRESENSI,
            sidik: $sidik,
            poin: $kegiatan->poin,
            keterangan: 'Hadir pada '.$kegiatan->judulTeks().' ('.$kegiatan->kode.')',
            periode: ContributionPoint::periodeDari($kegiatan->mulai ?? now()),
            kegiatan: $kegiatan,
            petugas: $petugas,
        );

        return 'diberi';
    }

    /**
     * Beri poin untuk SELURUH peserta yang hadir pada satu kegiatan.
     *
     * Aman dijalankan berulang: yang sudah punya poin dilewati, bukan
     * ditambahkan lagi.
     *
     * @return array{diberi: int, sudah_ada: int, tanpa_poin: int, dibatalkan: int}
     */
    public function dariPresensi(AttendanceActivity $kegiatan, ?User $petugas = null): array
    {
        $hasil = ['diberi' => 0, 'sudah_ada' => 0, 'tanpa_poin' => 0, 'dibatalkan' => 0];

        $catatan = AttendanceRecord::query()
            ->where('activity_id', $kegiatan->id)
            ->with('anggota')
            ->get();

        foreach ($catatan as $satu) {
            $keadaan = $this->selaraskanKehadiran($satu, $petugas);

            match ($keadaan) {
                'diberi' => $hasil['diberi']++,
                'dibatalkan' => $hasil['dibatalkan']++,
                'tanpa_poin' => $hasil['tanpa_poin']++,
                default => $hasil['sudah_ada']++,
            };
        }

        return $hasil;
    }

    /**
     * Beri poin kepada penulis artikel yang sudah terbit.
     *
     * Poin artikel TIDAK diambil dari pengaturan situs, melainkan dari
     * konstanta kelas ini, supaya besarannya ikut terversi di kode dan tidak
     * bisa berubah diam-diam dari panel.
     */
    public const POIN_ARTIKEL = 5;

    public function dariArtikel(Article $artikel, ?User $petugas = null): ?ContributionPoint
    {
        $penulis = $artikel->penulis;

        if ($penulis === null) {
            return null;
        }

        $anggota = Member::query()->where('user_id', $penulis->id)->first();

        if ($anggota === null) {
            return null;
        }

        return $this->catat(
            anggota: $anggota,
            sumber: ContributionPoint::SUMBER_ARTIKEL,
            sidik: ContributionPoint::sidikArtikel($artikel->id, $anggota->id),
            poin: self::POIN_ARTIKEL,
            keterangan: 'Karya terbit: '.($artikel->getTranslation('judul', 'id') ?: 'Artikel'),
            periode: ContributionPoint::periodeDari($artikel->terbit_pada ?? now()),
            kegiatan: null,
            petugas: $petugas,
        );
    }

    /**
     * Beri poin untuk prestasi yang sudah TERVERIFIKASI.
     *
     * Poin MENYUSUL KEPUTUSAN pengurus, bukan klaim kader. Prestasi yang masih
     * berstatus diajukan tidak menghasilkan poin apa pun — kalau tidak, siapa
     * pun bisa mengklaim juara nasional dan langsung menembus puncak papan
     * peringkat sebelum sertifikatnya diperiksa.
     */
    public function dariPrestasi(Achievement $prestasi, ?User $petugas = null): ?ContributionPoint
    {
        if (! $prestasi->terverifikasi()) {
            return null;
        }

        $poin = $prestasi->poin();
        $anggota = $prestasi->anggota;

        if ($poin <= 0 || $anggota === null) {
            return null;
        }

        return $this->catat(
            anggota: $anggota,
            sumber: ContributionPoint::SUMBER_PRESTASI,
            sidik: Achievement::sidik($prestasi->id, $anggota->id),
            poin: $poin,
            keterangan: $prestasi->labelPeringkat().' — '.$prestasi->judulTeks().' ('.$prestasi->labelTingkat().')',
            periode: $prestasi->periodeLabel(),
            kegiatan: null,
            petugas: $petugas,
        );
    }

    /**
     * Cabut poin prestasi — dipakai ketika verifikasinya dibatalkan.
     */
    public function tarikPrestasi(Achievement $prestasi, string $alasan, ?User $petugas = null): void
    {
        $anggota = $prestasi->anggota;

        if ($anggota === null) {
            return;
        }

        $baris = ContributionPoint::query()
            ->where('sidik', Achievement::sidik($prestasi->id, $anggota->id))
            ->first();

        if ($baris !== null && ! $baris->dibatalkan()) {
            $this->batalkan($baris, $alasan, $petugas);
        }
    }

    /**
     * Penyesuaian manual oleh pengurus.
     *
     * WAJIB BERALASAN. Poin yang bisa diberikan tanpa alasan akan berubah
     * menjadi alat mengatur papan peringkat.
     */
    public function sesuaikan(Member $anggota, int $poin, string $alasan, User $petugas, ?string $periode = null): ContributionPoint
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penyesuaian wajib diisi — poin yang berubah tanpa keterangan tidak bisa dipertanggungjawabkan.',
            ]);
        }

        if ($poin === 0) {
            throw ValidationException::withMessages([
                'poin' => 'Penyesuaian nol tidak mengubah apa pun — isi jumlah poinnya.',
            ]);
        }

        if (abs($poin) > ContributionPoint::BATAS_PENYESUAIAN) {
            throw ValidationException::withMessages([
                'poin' => 'Penyesuaian dibatasi '.ContributionPoint::BATAS_PENYESUAIAN.' poin sekali beri, untuk mencegah salah ketik yang melompatkan seseorang ke puncak papan peringkat.',
            ]);
        }

        return $this->catat(
            anggota: $anggota,
            sumber: ContributionPoint::SUMBER_MANUAL,
            // Penyesuaian manual SENGAJA tidak di-dedup: pengurus boleh
            // memberi dua penyesuaian terpisah. Sidik di sini hanya memenuhi
            // batasan unik, diberi akhiran acak agar tidak pernah bentrok.
            sidik: 'manual:anggota:'.$anggota->id.':'.Str::lower(Str::random(20)),
            poin: $poin,
            keterangan: trim($alasan),
            periode: $periode ?? now()->format('Y-m'),
            kegiatan: null,
            petugas: $petugas,
        );
    }

    /**
     * Batalkan satu baris poin. Tidak ada penghapusan.
     */
    public function batalkan(ContributionPoint $poin, string $alasan, ?User $petugas = null): ContributionPoint
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        if ($poin->dibatalkan()) {
            throw ValidationException::withMessages([
                'poin' => 'Poin ini sudah dibatalkan.',
            ]);
        }

        $poin->forceFill([
            'dibatalkan_pada' => now(),
            'alasan_pembatalan' => trim($alasan),
            'dibatalkan_oleh' => $petugas?->id,
        ])->save();

        return $poin;
    }

    /* ------------------------------------------------------------------ */
    /* Rekap                                                               */
    /* ------------------------------------------------------------------ */

    public function total(Member $anggota, ?string $periode = null): int
    {
        return (int) ContributionPoint::query()
            ->sah()
            ->where('member_id', $anggota->id)
            ->when($periode, fn ($q) => $q->periode($periode))
            ->sum('poin');
    }

    /**
     * @return array<int, array{sumber: string, label: string, poin: int, jumlah: int}>
     */
    public function rekapSumber(Member $anggota, ?string $periode = null): array
    {
        $baris = ContributionPoint::query()
            ->sah()
            ->where('member_id', $anggota->id)
            ->when($periode, fn ($q) => $q->periode($periode))
            ->get()
            ->groupBy('sumber');

        $hasil = [];

        foreach (ContributionPoint::SUMBER as $kunci => $label) {
            $grup = $baris->get($kunci);

            $hasil[] = [
                'sumber' => $kunci,
                'label' => $label,
                'poin' => (int) ($grup?->sum('poin') ?? 0),
                'jumlah' => (int) ($grup?->count() ?? 0),
            ];
        }

        return $hasil;
    }

    /**
     * Papan peringkat kader teraktif.
     *
     * HANYA KADER AKTIF yang masuk papan peringkat. Alumni tidak lagi berlomba
     * di papan kader, tetapi poinnya TETAP tersimpan — kalau statusnya kembali
     * aktif, riwayatnya utuh.
     *
     * @return array<int, array{peringkat: int, anggota_id: int, nama: string, unit: string|null, poin: int, jumlah_peristiwa: int, total_hadir: int}>
     */
    public function peringkat(?string $periode = null, int $batas = 10): array
    {
        $baris = $this->papanUrut($periode, $batas);

        $anggota = Member::query()
            ->with('unit:id,nama')
            ->whereIn('id', $baris->pluck('member_id'))
            ->get()
            ->keyBy('id');

        $hadir = AttendanceRecord::query()
            ->hadir()
            // Rentang tanggal, BUKAN DATE_FORMAT() — fungsi itu hanya ada di
            // MySQL dan akan mematahkan pengujian yang memakai SQLite.
            ->when($periode, fn ($q) => $q->whereHas('kegiatan', fn ($k) => $k->whereBetween('mulai', [
                Carbon::parse($periode.'-01')->startOfMonth(),
                Carbon::parse($periode.'-01')->endOfMonth(),
            ])))
            ->whereIn('member_id', $baris->pluck('member_id'))
            ->selectRaw('member_id, COUNT(*) as jumlah')
            ->groupBy('member_id')
            ->pluck('jumlah', 'member_id');

        $hasil = [];
        $nomor = 1;

        foreach ($baris as $satu) {
            $orang = $anggota->get($satu->member_id);

            if ($orang === null) {
                continue;
            }

            $hasil[] = [
                'peringkat' => $nomor++,
                'anggota_id' => $orang->id,
                'nama' => $orang->nama_lengkap,
                'unit' => $orang->unit?->nama,
                'poin' => (int) $satu->total,
                'jumlah_peristiwa' => (int) $satu->jumlah,
                'total_hadir' => (int) ($hadir[$orang->id] ?? 0),
            ];
        }

        return $hasil;
    }

    /**
     * Posisi seorang kader pada papan peringkat, atau null bila belum berpoin.
     *
     * MEMAKAI URUTAN YANG SAMA PERSIS dengan `peringkat()`. Sebelumnya metode
     * ini hanya menghitung "berapa orang yang poinnya lebih tinggi", sehingga
     * saat ada SERI dua kader sama-sama merasa peringkat 1 — sementara papan
     * peringkat di panel menulis #1 dan #2. Dua tampilan untuk hal yang sama
     * tidak boleh berbeda, jadi urutannya sekarang diambil dari satu tempat.
     */
    public function posisi(Member $anggota, ?string $periode = null): ?int
    {
        if ($anggota->status !== Member::STATUS_AKTIF) {
            return null;
        }

        $urutan = $this->papanUrut($periode);

        $ketemu = $urutan->search(fn ($baris): bool => (int) $baris->member_id === $anggota->id);

        return $ketemu === false ? null : $ketemu + 1;
    }

    /**
     * Sumber tunggal urutan papan peringkat.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function papanUrut(?string $periode = null, ?int $batas = null)
    {
        return ContributionPoint::query()
            ->sah()
            // Kader aktif disaring DI QUERY, bukan setelah pemeringkatan.
            // Menyaring belakangan membuat batas 10 bisa menyisakan 8 baris.
            ->join('members', 'members.id', '=', 'contribution_points.member_id')
            ->where('members.status', Member::STATUS_AKTIF)
            ->selectRaw('contribution_points.member_id, SUM(contribution_points.poin) as total, COUNT(*) as jumlah')
            ->when($periode, fn ($q) => $q->where('contribution_points.periode_label', $periode))
            ->groupBy('contribution_points.member_id')
            ->orderByDesc('total')
            // Seri diputus oleh BANYAKNYA peristiwa: dua kader dengan 20 poin
            // tidak sama kalau yang satu mendapatkannya dari 4 kegiatan dan
            // yang lain dari 2.
            ->orderByDesc('jumlah')
            // Terakhir, urutan tetap stabil agar halaman tidak berubah-ubah
            // setiap dimuat ulang.
            ->orderBy('contribution_points.member_id')
            ->when($batas, fn ($q) => $q->limit($batas))
            ->get();
    }

    /**
     * Daftar periode yang punya catatan poin — untuk saringan di panel.
     *
     * @return array<int, string>
     */
    public function periodeTersedia(): array
    {
        return ContributionPoint::query()
            ->select('periode_label')
            ->distinct()
            ->orderByDesc('periode_label')
            ->pluck('periode_label')
            ->all();
    }

    /* ------------------------------------------------------------------ */

    /**
     * Jantung kelas ini: menulis satu baris poin.
     *
     * Bila `sidik` sudah ada, TIDAK ADA baris baru — nilai kembaliannya null.
     * Itulah yang membuat seluruh operasi di atas aman dijalankan berkali-kali.
     */
    private function catat(
        ?Member $anggota,
        string $sumber,
        string $sidik,
        int $poin,
        string $keterangan,
        string $periode,
        ?AttendanceActivity $kegiatan,
        ?User $petugas,
    ): ?ContributionPoint {
        if ($anggota === null || $poin === 0) {
            return null;
        }

        return DB::transaction(function () use ($anggota, $sumber, $sidik, $poin, $keterangan, $periode, $kegiatan, $petugas): ?ContributionPoint {
            // Diperiksa LAGI di dalam transaksi, lalu disandarkan pada indeks
            // unik. Kalau pemeriksaan ini dilewati oleh permintaan bersamaan,
            // basis data yang menolaknya — bukan diam-diam menggandakan.
            if (ContributionPoint::query()->where('sidik', $sidik)->exists()) {
                return null;
            }

            $baris = new ContributionPoint;
            $baris->member_id = $anggota->id;
            $baris->sumber = $sumber;
            $baris->sidik = $sidik;
            $baris->poin = $poin;
            $baris->periode_label = $periode;
            $baris->keterangan = $keterangan;
            $baris->terjadi_pada = $kegiatan?->mulai ?? now();
            $baris->activity_id = $kegiatan?->id;
            $baris->diberikan_oleh = $petugas?->id;
            $baris->save();

            return $baris;
        }, 3);
    }
}
