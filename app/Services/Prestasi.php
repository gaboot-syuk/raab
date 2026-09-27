<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\AchievementCategory;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prestasi kader: pengajuan, verifikasi, dan penayangannya.
 *
 * EMPAT JANJI yang dipegang kelas ini:
 *
 * 1. KLAIM BELUM TENTANG BUKAN KEBENARAN. Prestasi yang diajukan kader berstatus
 *    `diajukan` sampai diperiksa. Hanya yang terverifikasi yang tayang publik
 *    dan menghasilkan poin.
 *
 * 2. POIN MENYUSUL KEPUTUSAN. Poin ditulis saat verifikasi, dan DICABUT saat
 *    verifikasinya dibatalkan. Prestasi yang diklaim lalu ditolak tidak pernah
 *    meninggalkan jejak poin di papan peringkat.
 *
 * 3. SEKALI SAJA. Pemberian poin memakai `sidik` unik, jadi memverifikasi
 *    ulang tidak menggandakan.
 *
 * 4. TIDAK ADA HAPUS UNTUK YANG SUDAH TERVERIFIKASI. Prestasi yang terverifikasi
 *    adalah catatan yang sudah tayang di halaman publik dan sudah memberi poin;
 *    menghapusnya akan menghapus jejak keduanya. Yang tersedia adalah pencabutan
 *    verifikasi beserta alasan. Hapus hanya untuk pengajuan yang belum diperiksa.
 */
class Prestasi
{
    public function __construct(private Kontribusi $kontribusi) {}

    /* ------------------------------------------------------------------ */
    /* Pengajuan                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Kader mengajukan prestasinya.
     *
     * @param  array<string, mixed>  $data
     */
    public function ajukan(Member $anggota, array $data, User $pengaju): Achievement
    {
        if (trim((string) ($data['judul'] ?? '')) === '') {
            throw ValidationException::withMessages(['judul' => 'Judul prestasi wajib diisi.']);
        }

        $tanggal = Carbon::parse($data['tanggal']);

        // Tanggal di masa depan hampir selalu salah ketik, dan yang lebih
        // penting: prestasi yang belum terjadi tidak bisa disertifikasi.
        if ($tanggal->isFuture()) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tanggal prestasi tidak boleh di masa depan.',
            ]);
        }

        $prestasi = new Achievement;
        $prestasi->member_id = $anggota->id;
        $prestasi->achievement_category_id = $data['achievement_category_id'] ?? null;
        $prestasi->penyelenggara = $data['penyelenggara'] ?? null;
        $prestasi->tingkat = $data['tingkat'];
        $prestasi->peringkat = $data['peringkat'];
        $prestasi->tanggal = $tanggal;
        $prestasi->sertifikat_media_id = $data['sertifikat_media_id'] ?? null;
        $prestasi->tautan_bukti = $data['tautan_bukti'] ?? null;
        $prestasi->status = Achievement::STATUS_DIAJUKAN;
        $prestasi->tampil_publik = (bool) ($data['tampil_publik'] ?? true);
        $prestasi->diajukan_oleh = $pengaju->id;

        $prestasi->setTranslations('judul', ['id' => trim((string) $data['judul'])]);

        if (($data['deskripsi'] ?? null) !== null && trim((string) $data['deskripsi']) !== '') {
            $prestasi->setTranslations('deskripsi', ['id' => trim((string) $data['deskripsi'])]);
        }

        $prestasi->save();

        activity()
            ->performedOn($prestasi)
            ->withProperties(['anggota' => $anggota->id])
            ->log('Prestasi diajukan');

        return $prestasi;
    }

    /**
     * Perbaiki pengajuan yang belum diperiksa.
     *
     * Hanya boleh selama masih `diajukan` — atau setelah ditolak, supaya kader
     * bisa memperbaiki lalu mengajukannya lagi.
     */
    public function perbarui(Achievement $prestasi, array $data, User $pengaju): Achievement
    {
        if ($prestasi->terverifikasi()) {
            throw ValidationException::withMessages([
                'prestasi' => 'Prestasi yang sudah terverifikasi tidak dapat diubah. Cabut verifikasinya lebih dulu bila memang keliru.',
            ]);
        }

        $tanggal = Carbon::parse($data['tanggal']);

        if ($tanggal->isFuture()) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tanggal prestasi tidak boleh di masa depan.',
            ]);
        }

        $prestasi->achievement_category_id = $data['achievement_category_id'] ?? null;
        $prestasi->penyelenggara = $data['penyelenggara'] ?? null;
        $prestasi->tingkat = $data['tingkat'];
        $prestasi->peringkat = $data['peringkat'];
        $prestasi->tanggal = $tanggal;
        $prestasi->tautan_bukti = $data['tautan_bukti'] ?? null;
        $prestasi->tampil_publik = (bool) ($data['tampil_publik'] ?? true);
        $prestasi->status = Achievement::STATUS_DIAJUKAN;
        $prestasi->catatan_verifikasi = null;

        if (array_key_exists('sertifikat_media_id', $data) && $data['sertifikat_media_id'] !== null) {
            $prestasi->sertifikat_media_id = $data['sertifikat_media_id'];
        }

        $prestasi->setTranslations('judul', ['id' => trim((string) $data['judul'])]);

        if (($data['deskripsi'] ?? null) !== null && trim((string) $data['deskripsi']) !== '') {
            $prestasi->setTranslations('deskripsi', ['id' => trim((string) $data['deskripsi'])]);
        }

        $prestasi->save();

        return $prestasi;
    }

    /* ------------------------------------------------------------------ */
    /* Verifikasi                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Verifikasi prestasi: inilah saat poin lahir.
     */
    public function verifikasi(Achievement $prestasi, User $pengurus, ?string $catatan = null): Achievement
    {
        if ($prestasi->terverifikasi()) {
            throw ValidationException::withMessages([
                'prestasi' => 'Prestasi ini sudah terverifikasi.',
            ]);
        }

        return DB::transaction(function () use ($prestasi, $pengurus, $catatan): Achievement {
            $prestasi->status = Achievement::STATUS_TERVERIFIKASI;
            $prestasi->diverifikasi_oleh = $pengurus->id;
            $prestasi->diverifikasi_pada = now();
            $prestasi->catatan_verifikasi = $catatan;
            $prestasi->save();

            $this->kontribusi->dariPrestasi($prestasi, $pengurus);

            activity()
                ->performedOn($prestasi)
                ->withProperties(['oleh' => $pengurus->id, 'poin' => $prestasi->poin()])
                ->log('Prestasi diverifikasi');

            return $prestasi;
        }, 3);
    }

    /**
     * Tolak pengajuan — WAJIB beralasan.
     *
     * Bila sebelumnya sempat terverifikasi, poinnya ikut dicabut; menyisakan
     * poin dari prestasi yang batal terverifikasi akan membuat papan peringkat
     * memuat jasa yang tidak ada.
     */
    public function tolak(Achievement $prestasi, User $pengurus, string $alasan): Achievement
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penolakan wajib diisi agar kader tahu apa yang perlu diperbaiki.',
            ]);
        }

        return DB::transaction(function () use ($prestasi, $pengurus, $alasan): Achievement {
            $pernahTerverifikasi = $prestasi->terverifikasi();

            $prestasi->status = Achievement::STATUS_DITOLAK;
            $prestasi->diverifikasi_oleh = $pengurus->id;
            $prestasi->diverifikasi_pada = now();
            $prestasi->catatan_verifikasi = trim($alasan);

            // Prestasi yang ditolak tidak boleh tetap menjadi unggulan.
            $prestasi->unggulan = false;
            $prestasi->save();

            if ($pernahTerverifikasi) {
                $this->kontribusi->tarikPrestasi(
                    $prestasi,
                    'Verifikasi prestasi dibatalkan: '.trim($alasan),
                    $pengurus,
                );
            }

            return $prestasi;
        }, 3);
    }

    /**
     * Tandai sebagai unggulan untuk beranda.
     *
     * HANYA yang sudah terverifikasi. Kalau klaim yang belum diperiksa bisa
     * diunggulkan, halaman depan rayon menayangkan sesuatu yang belum terbukti.
     */
    public function jadikanUnggulan(Achievement $prestasi, bool $unggulan, User $pengurus): Achievement
    {
        if ($unggulan && ! $prestasi->terverifikasi()) {
            throw ValidationException::withMessages([
                'unggulan' => 'Hanya prestasi yang sudah terverifikasi dapat dijadikan unggulan.',
            ]);
        }

        $prestasi->unggulan = $unggulan;
        $prestasi->save();

        activity()
            ->performedOn($prestasi)
            ->withProperties(['oleh' => $pengurus->id, 'unggulan' => $unggulan])
            ->log($unggulan ? 'Prestasi dijadikan unggulan' : 'Prestasi tidak lagi unggulan');

        return $prestasi;
    }

    /* ------------------------------------------------------------------ */
    /* Sakelar milik kader                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Kader menyembunyikan prestasinya dari halaman publik.
     *
     * Ini keputusan PEMILIK DATA, bukan pengurus. Prestasi tetap terverifikasi
     * dan tetap berpoin; yang berubah hanya penayangannya.
     */
    public function aturTampilPublik(Achievement $prestasi, bool $tampil): Achievement
    {
        $prestasi->tampil_publik = $tampil;
        $prestasi->save();

        return $prestasi;
    }

    /**
     * Hapus pengajuan yang BELUM diperiksa.
     *
     * Sengaja hanya untuk status `diajukan`: pengajuan yang belum diverifikasi
     * belum menghasilkan poin dan belum pernah tayang, jadi tidak ada apa pun
     * yang perlu dipertanggungjawabkan. Untuk yang sudah terverifikasi,
     * gunakan `tolak()` — jejaknya harus tetap ada.
     */
    public function hapusPengajuan(Achievement $prestasi): void
    {
        if ($prestasi->status !== Achievement::STATUS_DIAJUKAN || $prestasi->diverifikasi_pada !== null) {
            throw ValidationException::withMessages([
                'prestasi' => 'Prestasi yang sudah diperiksa tidak dapat dihapus. Cabut verifikasinya beserta alasan.',
            ]);
        }

        $prestasi->delete();
    }

    /* ------------------------------------------------------------------ */
    /* Rekap                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Ringkasan prestasi seorang kader, dikelompokkan per tingkat.
     *
     * Hanya yang TERVERIFIKASI yang dihitung — inilah angka yang diberitakan.
     *
     * @return array{total: int, per_tingkat: array<string, int>, unggulan: int}
     */
    public function rekap(Member $anggota): array
    {
        $prestasi = Achievement::query()
            ->terverifikasi()
            ->where('member_id', $anggota->id)
            ->get();

        $perTingkat = [];

        foreach (Achievement::TINGKAT as $kunci => $label) {
            $perTingkat[$label] = $prestasi->where('tingkat', $kunci)->count();
        }

        return [
            'total' => $prestasi->count(),
            'per_tingkat' => $perTingkat,
            'unggulan' => $prestasi->where('unggulan', true)->count(),
        ];
    }

    /**
     * Prestasi unggulan untuk beranda.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Achievement>
     */
    public function unggulan(int $batas = 3)
    {
        return Achievement::query()
            ->tayangPublik()
            ->where('unggulan', true)
            ->with(['anggota:id,nama_lengkap,slug,unit_id', 'anggota.unit:id,nama', 'kategori:id,nama'])
            ->orderByDesc('tanggal')
            ->limit($batas)
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /* Kategori                                                            */
    /* ------------------------------------------------------------------ */

    public function simpanKategori(string $nama, array $data = []): AchievementCategory
    {
        $kategori = new AchievementCategory;
        $kategori->kode = $this->kodeKategoriUnik($nama);
        $kategori->urutan = (int) ($data['urutan'] ?? 0);
        $kategori->aktif = true;
        $kategori->setTranslations('nama', ['id' => $nama]);

        if (($data['keterangan'] ?? null) !== null) {
            $kategori->setTranslations('keterangan', ['id' => $data['keterangan']]);
        }

        $kategori->save();

        return $kategori;
    }

    /**
     * Kode kategori yang dijamin belum terpakai.
     *
     * Kolomnya unik, jadi dua kategori bernama sama (atau yang slug-nya
     * kebetulan sama) akan menabrak batasan basis data dan gagal dengan galat
     * yang membingungkan pengurus.
     */
    private function kodeKategoriUnik(string $nama): string
    {
        $dasar = Achievement::kodeKategori($nama);
        $kode = $dasar;
        $urutan = 2;

        while (AchievementCategory::query()->where('kode', $kode)->exists()) {
            $kode = $dasar.'_'.$urutan;
            $urutan++;
        }

        return $kode;
    }
}
