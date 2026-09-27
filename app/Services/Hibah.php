<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\Event;
use App\Models\FinanceTransaction;
use App\Models\InventoryItem;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hibah & dukungan alumni.
 *
 * SATU TABEL, TIGA CARA PENERIMAAN — dan masing-masing menyentuh modul yang
 * berbeda. Di sinilah ketiganya dijahit:
 *
 *  - `dana`   → transaksi kas masuk (sumber `hibah`), saldo akun bertambah
 *  - `barang` → mutasi inventaris `masuk`, stok aset bertambah
 *  - `jasa`   → dicatat sebagai kesediaan, boleh ditautkan ke kegiatan
 *
 * YANG TIDAK DILAKUKAN KELAS INI: menyentuh saldo atau stok secara langsung.
 * Keduanya tetap lewat App\Services\Kas dan App\Services\Inventaris, sehingga
 * janji "saldo tidak pernah bergerak tanpa jejak" dan "stok selalu konsisten"
 * tidak bocor lewat pintu hibah.
 */
class Hibah
{
    public function __construct(
        private Kas $kas,
        private Inventaris $inventaris,
    ) {}

    /**
     * Alumni/anggota mengajukan hibah dari area anggota.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function ajukan(array $data, ?Member $anggota = null, ?User $pencatat = null): Donation
    {
        $this->periksaJenis($data['jenis'] ?? null);

        return DB::transaction(function () use ($data, $anggota, $pencatat): Donation {
            $hibah = $this->buat($data, $pencatat);

            if ($anggota) {
                $hibah->member_id = $anggota->id;

                if (blank($hibah->nama_pemberi)) {
                    $hibah->nama_pemberi = (string) $anggota->nama_lengkap;
                }
            }

            $hibah->status = Donation::STATUS_DIAJUKAN;
            $hibah->save();

            activity()
                ->performedOn($hibah)
                ->withProperties(['nomor_hibah' => $hibah->nomor_hibah, 'jenis' => $hibah->jenis])
                ->log('Hibah diajukan');

            return $hibah;
        }, 3);
    }

    /**
     * Bendahara mencatat hibah langsung — mis. alumni menyerahkan dana tunai di
     * sekretariat tanpa lewat dashboard. Langsung berstatus `dijanjikan`.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function catatLangsung(array $data, User $petugas): Donation
    {
        $this->periksaJenis($data['jenis'] ?? null);

        if (blank($data['nama_pemberi'] ?? null)) {
            throw ValidationException::withMessages(['nama_pemberi' => 'Nama pemberi wajib diisi.']);
        }

        $hibah = $this->buat($data, $petugas);
        $hibah->status = Donation::STATUS_DIJANJIKAN;
        $hibah->save();

        return $hibah;
    }

    /**
     * Setujui hibah — status naik ke `dijanjikan`, siap diterima.
     *
     * @throws ValidationException
     */
    public function setujui(Donation $hibah, User $petugas, ?string $catatan = null): Donation
    {
        if (! in_array($hibah->status, [Donation::STATUS_DIAJUKAN, Donation::STATUS_DIJANJIKAN], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hibah berstatus '.strtolower($hibah->labelStatus()).' tidak dapat disetujui lagi.',
            ]);
        }

        $hibah->status = Donation::STATUS_DIJANJIKAN;

        if ($catatan !== null) {
            $hibah->catatan_bendahara = trim($catatan);
        }

        $hibah->save();

        return $hibah;
    }

    /**
     * @throws ValidationException
     */
    public function tolak(Donation $hibah, User $petugas, string $alasan): Donation
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan_tolak' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        $hibah->status = Donation::STATUS_DITOLAK;
        $hibah->alasan_tolak = trim($alasan);
        $hibah->diverifikasi_oleh = $petugas->id;
        $hibah->diverifikasi_pada = now();
        $hibah->save();

        return $hibah;
    }

    /**
     * Terima hibah. Inilah titik di mana jenis hibah menentukan tujuannya.
     *
     * @param  array<string, mixed>  $data  account_id, category_id, nilai_diterima,
     *                                      inventory_item_id, jumlah, kondisi_barang,
     *                                      event_id, catatan_bendahara
     *
     * @throws ValidationException
     */
    public function terima(Donation $hibah, User $petugas, array $data = []): Donation
    {
        if ($hibah->status === Donation::STATUS_DITERIMA || $hibah->status === Donation::STATUS_DIVERIFIKASI) {
            throw ValidationException::withMessages([
                'status' => 'Hibah ini sudah pernah diterima.',
            ]);
        }

        if ($hibah->status === Donation::STATUS_DITOLAK) {
            throw ValidationException::withMessages([
                'status' => 'Hibah yang sudah ditolak tidak dapat diterima.',
            ]);
        }

        return DB::transaction(function () use ($hibah, $petugas, $data): Donation {
            $nilai = (int) ($data['nilai_diterima'] ?? $hibah->estimasi_nilai);

            $hibah->diterima_pada = now();
            $hibah->diverifikasi_oleh = $petugas->id;
            $hibah->diverifikasi_pada = now();

            if (! empty($data['catatan_bendahara'])) {
                $hibah->catatan_bendahara = trim((string) $data['catatan_bendahara']);
            }

            switch ($hibah->jenis) {
                case Donation::JENIS_DANA:
                    $this->terimaDana($hibah, $petugas, $nilai, $data);
                    break;

                case Donation::JENIS_BARANG:
                    $this->terimaBarang($hibah, $petugas, $nilai, $data);
                    break;

                default:
                    $this->terimaJasa($hibah, $data);
                    break;
            }

            $hibah->nilai_diterima = $nilai;
            $hibah->status = Donation::STATUS_DITERIMA;
            $hibah->save();

            activity()
                ->performedOn($hibah)
                ->withProperties([
                    'nomor_hibah' => $hibah->nomor_hibah,
                    'jenis' => $hibah->jenis,
                    'nilai' => $nilai,
                ])
                ->log('Hibah diterima');

            return $hibah;
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    public function batalkan(Donation $hibah, User $petugas, string $alasan): Donation
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages(['catatan_bendahara' => 'Alasan pembatalan wajib diisi.']);
        }

        $hibah->status = Donation::STATUS_DIBATALKAN;
        $hibah->catatan_bendahara = trim($alasan);
        $hibah->save();

        return $hibah;
    }

    /**
     * Rekap hibah untuk laporan Bendahara.
     *
     * @return array{total: int, jumlah: int, per_jenis: array<string, int>, per_alumni: \Illuminate\Support\Collection<int, array{nama: string, total: int, jumlah: int}>}
     */
    public function rekap(?int $tahun = null): array
    {
        $query = Donation::query()
            ->whereIn('status', [Donation::STATUS_DITERIMA, Donation::STATUS_DIVERIFIKASI])
            ->when($tahun, fn ($q) => $q->whereYear('diterima_pada', $tahun));

        $semua = $query->get();

        return [
            'total' => (int) $semua->sum(fn (Donation $d): int => $d->nilaiTercatat()),
            'jumlah' => $semua->count(),
            'per_jenis' => [
                Donation::JENIS_DANA => (int) $semua->where('jenis', Donation::JENIS_DANA)->sum(fn (Donation $d): int => $d->nilaiTercatat()),
                Donation::JENIS_BARANG => (int) $semua->where('jenis', Donation::JENIS_BARANG)->sum(fn (Donation $d): int => $d->nilaiTercatat()),
                Donation::JENIS_JASA => (int) $semua->where('jenis', Donation::JENIS_JASA)->sum(fn (Donation $d): int => $d->nilaiTercatat()),
            ],
            'per_alumni' => $semua
                ->groupBy(fn (Donation $d): string => $d->namaPemberi())
                ->map(fn ($grup, string $nama): array => [
                    'nama' => $nama,
                    'total' => (int) $grup->sum(fn (Donation $d): int => $d->nilaiTercatat()),
                    'jumlah' => $grup->count(),
                ])
                ->sortByDesc('total')
                ->values(),
        ];
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $data
     */
    private function buat(array $data, ?User $pencatat): Donation
    {
        $hibah = new Donation;
        $hibah->nomor_hibah = Donation::nomorHibahBaru();
        $hibah->setTranslations('judul', [
            'id' => $data['judul'] ?? ($data['judul_id'] ?? ''),
            'en' => $data['judul_en'] ?? ($data['judul'] ?? ''),
        ]);
        $hibah->setTranslations('deskripsi', [
            'id' => $data['deskripsi'] ?? '',
            'en' => $data['deskripsi'] ?? '',
        ]);
        $hibah->jenis = $data['jenis'];
        $hibah->nama_pemberi = trim((string) ($data['nama_pemberi'] ?? ''));
        $hibah->kontak = $data['kontak'] ?? null;
        $hibah->estimasi_nilai = (int) ($data['estimasi_nilai'] ?? 0);
        $hibah->tanggal_rencana = ! empty($data['tanggal_rencana']) ? Carbon::parse($data['tanggal_rencana']) : null;
        $hibah->anonim = (bool) ($data['anonim'] ?? false);
        $hibah->kondisi_barang = $data['kondisi_barang'] ?? null;
        $hibah->bukti_media_id = $data['bukti_media_id'] ?? null;
        $hibah->dicatat_oleh = $pencatat?->id;
        $hibah->alumni_profile_id = $data['alumni_profile_id'] ?? null;

        return $hibah;
    }

    /**
     * @throws ValidationException
     */
    private function periksaJenis(?string $jenis): void
    {
        if (! $jenis || ! array_key_exists($jenis, Donation::JENIS)) {
            throw ValidationException::withMessages(['jenis' => 'Jenis hibah harus dana, barang, atau jasa.']);
        }
    }

    /**
     * Hibah dana → kas masuk. Saldo hanya bergerak lewat App\Services\Kas.
     */
    private function terimaDana(Donation $hibah, User $petugas, int $nilai, array $data): void
    {
        if ($nilai <= 0) {
            throw ValidationException::withMessages([
                'nilai_diterima' => 'Nilai hibah dana harus lebih dari nol.',
            ]);
        }

        $transaksi = $this->kas->catat([
            'account_id' => $data['account_id'] ?? $this->kas->akunUtama()->id,
            'category_id' => $data['category_id'] ?? null,
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => $nilai,
            'keterangan' => 'Hibah '.$hibah->nomor_hibah.' — '.$hibah->judulTeks(),
            'sumber' => FinanceTransaction::SUMBER_HIBAH,
            'donation_id' => $hibah->id,
            // Bukti boleh ditempelkan petugas saat menerima, karena hibah besar
            // (di atas batas wajib bukti) tidak akan lolos tanpa nota.
            'bukti_media_id' => $data['bukti_media_id'] ?? $hibah->bukti_media_id,
        ], $petugas);

        $this->kas->konfirmasi($transaksi, $petugas);

        $hibah->transaction_id = $transaksi->id;
    }

    /**
     * Hibah barang → mutasi inventaris `masuk`. Stok hanya bergerak lewat
     * App\Services\Inventaris, supaya riwayat stoknya tetap lengkap.
     */
    private function terimaBarang(Donation $hibah, User $petugas, int $nilai, array $data): void
    {
        $aset = InventoryItem::query()->find($data['inventory_item_id'] ?? null);

        if (! $aset) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'Pilih aset inventaris yang akan ditambah stoknya. '
                    .'Bila barangnya jenis baru, daftarkan asetnya dulu di panel Inventaris.',
            ]);
        }

        $jumlah = max(1, (int) ($data['jumlah'] ?? 1));

        $mutasi = $this->inventaris->catat($aset, 'masuk', $jumlah, [
            'catatan' => 'Hibah '.$hibah->nomor_hibah.' dari '.$hibah->namaPemberi($petugas),
            'kondisi_baru' => $hibah->kondisi_barang,
        ], $petugas);

        $hibah->inventory_movement_id = $mutasi->id;
    }

    /**
     * Hibah jasa → kesediaan yang dicatat pada kegiatan. Tidak menyentuh saldo
     * maupun stok: jasanya tidak bisa dihitung sebagai uang atau barang.
     *
     * @throws ValidationException
     */
    private function terimaJasa(Donation $hibah, array $data): void
    {
        $eventId = $data['event_id'] ?? null;

        if ($eventId && ! Event::query()->whereKey($eventId)->exists()) {
            throw ValidationException::withMessages(['event_id' => 'Kegiatan yang dipilih tidak ditemukan.']);
        }

        $hibah->event_id = $eventId;
    }
}
