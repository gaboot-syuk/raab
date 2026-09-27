<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Support\Audiens;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengumuman.
 *
 * EMPAT JANJI:
 *
 * 1. SATU TEMPAT MENENTUKAN "SEDANG TAYANG". Yang belum waktunya tidak bocor
 *    lebih awal, dan yang masa berlakunya habis berhenti sendiri. Aturan itu
 *    hidup di `Announcement::scopeTayang()` — bukan diulang di tiap pengendali,
 *    karena satu tempat yang salah lebih mudah ditutup daripada lima.
 *
 * 2. AUDIENS DIPERIKSA DI SERVER. Halaman anggota tidak mengirim pengumuman
 *    yang bukan haknya lalu menyembunyikannya dengan CSS. Yang tidak berhak
 *    memang tidak pernah sampai ke peramban.
 *
 * 3. PENGUMUMAN PUBLIK WAJIB MEMUAT `publik` DI AUDIENSNYA. Kalau tidak, ia
 *    akan tayang di halaman publik tetapi tidak bisa dibaca siapa pun —
 *    kesalahan yang tidak kelihatan sampai ada yang mengeluh.
 *
 * 4. MENGHAPUS BUKAN MEMUSNAHKAN. Sama seperti arsip: pengumuman yang pernah
 *    dibaca orang tidak boleh lenyap tanpa jejak.
 */
class Pengumuman
{
    /**
     * Judul satu pengumuman tidak boleh kosong, dan begitu pula isinya.
     */
    public const MIN_ISI = 20;

    /**
     * @param  array<string, mixed>  $data
     */
    public function simpan(array $data, User $petugas): Announcement
    {
        $bersih = $this->bersihkan($data);

        $pengumuman = new Announcement;
        $pengumuman->slug = Announcement::slugUnik($bersih['judul']);
        $pengumuman->tipe = $bersih['tipe'];
        $pengumuman->target_audience = $bersih['target_audience'];
        $pengumuman->is_pinned = $bersih['is_pinned'];
        $pengumuman->publish_at = $bersih['publish_at'];
        $pengumuman->expire_at = $bersih['expire_at'];
        $pengumuman->dibuat_oleh = $petugas->id;

        // Kolom JSON NOT NULL harus terisi SEBELUM save().
        $pengumuman->setTranslations('judul', ['id' => $bersih['judul']]);
        $pengumuman->setTranslations('isi', ['id' => $bersih['isi']]);
        $pengumuman->save();

        activity()
            ->performedOn($pengumuman)
            ->withProperties(['tipe' => $pengumuman->tipe])
            ->log('Pengumuman dibuat');

        return $pengumuman;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function perbarui(Announcement $pengumuman, array $data, User $petugas): Announcement
    {
        $bersih = $this->bersihkan($data);

        if ($bersih['judul'] !== $pengumuman->judulTeks()) {
            $pengumuman->slug = Announcement::slugUnik($bersih['judul'], $pengumuman->id);
        }

        $pengumuman->tipe = $bersih['tipe'];
        $pengumuman->target_audience = $bersih['target_audience'];
        $pengumuman->is_pinned = $bersih['is_pinned'];
        $pengumuman->publish_at = $bersih['publish_at'];
        $pengumuman->expire_at = $bersih['expire_at'];
        $pengumuman->setTranslations('judul', ['id' => $bersih['judul']]);
        $pengumuman->setTranslations('isi', ['id' => $bersih['isi']]);
        $pengumuman->save();

        activity()
            ->performedOn($pengumuman)
            ->withProperties(['tipe' => $pengumuman->tipe])
            ->log('Pengumuman diperbarui');

        return $pengumuman;
    }

    /**
     * Sematkan atau lepaskan dari puncak daftar.
     */
    public function sematkan(Announcement $pengumuman, bool $semat, User $petugas): Announcement
    {
        $pengumuman->is_pinned = $semat;
        $pengumuman->save();

        activity()
            ->performedOn($pengumuman)
            ->withProperties(['is_pinned' => $semat])
            ->log($semat ? 'Pengumuman disematkan' : 'Sematkan pengumuman dilepas');

        return $pengumuman;
    }

    /**
     * Hapus lunak. Berkas dan riwayatnya tetap tinggal di basis data.
     */
    public function hapus(Announcement $pengumuman, User $petugas): void
    {
        DB::transaction(function () use ($pengumuman, $petugas): void {
            activity()
                ->performedOn($pengumuman)
                ->causedBy($petugas)
                ->log('Pengumuman dihapus');

            $pengumuman->delete();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Pembacaan                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Pengumuman untuk halaman publik.
     *
     * Hanya yang bertipe publik DAN membuka diri untuk audiens `publik`.
     * Dua syarat ini terpisah dengan sengaja: tipe berbicara tentang tempat
     * penayangan, audiens berbicara tentang siapa yang boleh membaca.
     *
     * @return Collection<int, Announcement>
     */
    public function untukPublik(int $batas = 50): Collection
    {
        return Announcement::query()
            ->tayang()
            ->tipe(Announcement::TIPE_PUBLIK)
            ->terbaruDulu()
            ->limit($batas)
            ->get()
            ->filter(fn (Announcement $p) => $p->bolehDibacaOleh(null))
            ->values();
    }

    /**
     * Pengumuman untuk seorang penonton di area anggota.
     *
     * Haknya diperiksa DI SINI — yang tidak berhak tidak pernah dikirim ke
     * peramban, jadi tidak ada yang bisa dibuka lewat alat pengembang.
     *
     * @return Collection<int, Announcement>
     */
    public function untuk(?User $pengguna, int $batas = 100): Collection
    {
        return Announcement::query()
            ->tayang()
            ->terbaruDulu()
            ->limit($batas)
            ->get()
            ->filter(fn (Announcement $p) => $p->bolehDibacaOleh($pengguna))
            ->values();
    }

    /**
     * Daftar untuk panel pengurus: SEMUA pengumuman, termasuk yang terjadwal
     * dan yang sudah kedaluwarsa — tanpa itu pengurus tidak bisa memperbaiki
     * pengumuman yang salah jadwal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function daftarPanel(?string $saringan = null, int $batas = 100): array
    {
        return Announcement::query()
            ->when($saringan === 'tayang', fn ($q) => $q->tayang())
            ->when($saringan === 'terjadwal', fn ($q) => $q->where('publish_at', '>', now()))
            ->when($saringan === 'kedaluwarsa', fn ($q) => $q->whereNotNull('expire_at')->where('expire_at', '<', now()))
            ->orderByDesc('publish_at')
            ->limit($batas)
            ->get()
            ->map(fn (Announcement $p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'judul' => $p->judulTeks(),
                'isi' => $p->isiTeks(),
                'tipe' => $p->tipe,
                'label_tipe' => $p->labelTipe(),
                'audiens' => $p->target_audience ?? [],
                'audiens_teks' => $p->audiensTeks(),
                'is_pinned' => (bool) $p->is_pinned,
                'publish_at' => $p->publish_at?->format('Y-m-d\TH:i'),
                'publish_at_teks' => $p->publish_at?->translatedFormat('d F Y, H:i'),
                'expire_at' => $p->expire_at?->format('Y-m-d\TH:i'),
                'expire_at_teks' => $p->expire_at?->translatedFormat('d F Y, H:i'),
                'label_waktu' => $p->labelWaktu(),
                'diperbarui' => $p->updated_at?->translatedFormat('d F Y, H:i'),
            ])->all();
    }

    /**
     * @return array{total: int, tayang: int, terjadwal: int, kedaluwarsa: int, disematkan: int, publik: int}
     */
    public function rekap(): array
    {
        $semua = Announcement::query()->get();

        return [
            'total' => $semua->count(),
            'tayang' => $semua->filter(fn (Announcement $p) => $p->sedangTayang())->count(),
            'terjadwal' => $semua->filter(fn (Announcement $p) => $p->terjadwal())->count(),
            'kedaluwarsa' => $semua->filter(fn (Announcement $p) => $p->kedaluwarsa())->count(),
            'disematkan' => $semua->where('is_pinned', true)->count(),
            'publik' => $semua->where('tipe', Announcement::TIPE_PUBLIK)->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Pembersihan & pemeriksaan                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $data
     * @return array{judul: string, isi: string, tipe: string, target_audience: array<int, string>, is_pinned: bool, publish_at: mixed, expire_at: mixed}
     */
    private function bersihkan(array $data): array
    {
        $judul = trim((string) ($data['judul'] ?? ''));
        $isi = trim((string) ($data['isi'] ?? ''));

        if ($judul === '') {
            throw ValidationException::withMessages(['judul' => 'Judul pengumuman wajib diisi.']);
        }

        if (mb_strlen($isi) < self::MIN_ISI) {
            throw ValidationException::withMessages([
                'isi' => 'Isi pengumuman minimal '.self::MIN_ISI.' huruf — pengumuman sekali baris tidak dapat ditindaklanjuti.',
            ]);
        }

        $audiens = Audiens::bersihkan((array) ($data['target_audience'] ?? []));

        if ($audiens === []) {
            throw ValidationException::withMessages([
                'target_audience' => 'Pilih minimal satu audiens — tanpa itu tidak ada seorang pun yang bisa membacanya.',
            ]);
        }

        $tipe = (string) ($data['tipe'] ?? Announcement::TIPE_INTERNAL);

        if (! array_key_exists($tipe, Announcement::TIPE)) {
            throw ValidationException::withMessages(['tipe' => 'Jenis pengumuman tidak dikenal.']);
        }

        // Janji 3: pengumuman publik harus benar-benar bisa dibaca publik.
        if ($tipe === Announcement::TIPE_PUBLIK && ! in_array(Audiens::PUBLIK, $audiens, true)) {
            throw ValidationException::withMessages([
                'target_audience' => 'Pengumuman bertipe publik harus menyertakan audiens "Umum", kalau tidak halaman publik akan menampilkannya tetapi tidak ada yang bisa membukanya.',
            ]);
        }

        $publish = $data['publish_at'] ?? null;
        $expire = $data['expire_at'] ?? null;

        if ($publish === null || $publish === '') {
            $publish = now();
        }

        if ($expire === null || $expire === '') {
            $expire = null;
        }

        if ($expire !== null && strtotime((string) $expire) <= strtotime((string) $publish)) {
            throw ValidationException::withMessages([
                'expire_at' => 'Masa berlaku harus berakhir SETELAH pengumuman mulai tayang.',
            ]);
        }

        return [
            'judul' => $judul,
            'isi' => $isi,
            'tipe' => $tipe,
            'target_audience' => $audiens,
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'publish_at' => $publish,
            'expire_at' => $expire,
        ];
    }
}
