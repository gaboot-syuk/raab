<?php

namespace App\Services;

use App\Models\DueCategory;
use App\Models\DueInvoice;
use App\Models\DuePayment;
use App\Models\FinanceTransaction;
use App\Models\Member;
use App\Models\User;
use App\Notifications\Keuangan\PengingatIuran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Iuran anggota: penerbitan tagihan, pembayaran, dan verifikasinya.
 *
 * SATU PRINSIP: uang hanya dicatat SEKALI di buku kas.
 *
 * Pembayaran di sini adalah BUKTI (siapa bayar, kapan, lewat apa), sedangkan
 * kas adalah SALDO. Karena itu transaksi kas baru lahir saat pembayaran
 * diverifikasi — bukan saat diunggah. Kalau keduanya dibuat berbarengan, satu
 * pembayaran akan tercatat dua kali di laporan.
 *
 * Pembayaran TIDAK PERNAH DIHAPUS. Yang ditolak tetap tersimpan supaya jejak
 * unggahannya ada; tagihannya yang dikembalikan ke "perlu unggah ulang".
 */
class Iuran
{
    /**
     * Jeda minimum sebelum tagihan yang sama boleh diingatkan lagi (jam).
     */
    public const JEDA_PENGINGAT_JAM = 24;

    public function __construct(private Kas $kas) {}

    /**
     * Terbitkan tagihan untuk seluruh penerima kategori pada satu periode.
     *
     * Aman dijalankan berulang: tagihan yang sudah ada dilewati, bukan dibuat
     * dobel. Bendahara boleh menekan tombolnya dua kali tanpa takut menggandakan.
     *
     * @return int jumlah tagihan yang BARU dibuat
     */
    public function terbitkan(
        DueCategory $kategori,
        string $periodeLabel,
        ?string $jatuhTempo = null,
        ?User $petugas = null,
    ): int {
        $periodeLabel = trim($periodeLabel);

        if ($periodeLabel === '') {
            throw ValidationException::withMessages(['periode_label' => 'Label periode wajib diisi, mis. 2026-09.']);
        }

        if ($kategori->nominal <= 0) {
            throw ValidationException::withMessages([
                'nominal' => 'Nominal iuran belum diisi pada kategori "'.$kategori->namaTeks().'".',
            ]);
        }

        $penerima = $this->penerima($kategori);

        return DB::transaction(function () use ($kategori, $periodeLabel, $jatuhTempo, $petugas, $penerima): int {
            $jumlah = 0;

            foreach ($penerima as $anggota) {
                $sudahAda = DueInvoice::query()
                    ->where('due_category_id', $kategori->id)
                    ->where('member_id', $anggota->id)
                    ->where('periode_label', $periodeLabel)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $tagihan = new DueInvoice;
                $tagihan->due_category_id = $kategori->id;
                $tagihan->member_id = $anggota->id;
                $tagihan->periode_label = $periodeLabel;
                $tagihan->nominal = $kategori->nominal;
                $tagihan->jatuh_tempo = $jatuhTempo ? Carbon::parse($jatuhTempo) : null;
                $tagihan->status = DueInvoice::STATUS_BELUM;
                $tagihan->dibuat_oleh = $petugas?->id;
                $tagihan->save();

                $jumlah++;
            }

            if ($jumlah > 0) {
                activity()
                    ->performedOn($kategori)
                    ->withProperties(['periode' => $periodeLabel, 'jumlah_tagihan' => $jumlah])
                    ->log('Tagihan iuran diterbitkan');
            }

            return $jumlah;
        }, 3);
    }

    /**
     * Siapa yang otomatis menerima tagihan kategori ini.
     *
     * "Pengurus" berarti anggota yang akunnya memegang peran kepengurusan.
     * Dihitung dari tabel peran, bukan dari daftar nama yang di-hardcode, agar
     * pengurus baru ikut tertagih tanpa perlu mengubah kode.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Member>
     */
    public function penerima(DueCategory $kategori)
    {
        $dasar = Member::query()->where('status', '!=', Member::STATUS_DITOLAK);

        return match ($kategori->target_audiens) {
            DueCategory::TARGET_KADER_AKTIF => $dasar->where('jalur', Member::JALUR_KADER)
                ->where('status', Member::STATUS_AKTIF)->get(),

            DueCategory::TARGET_ALUMNI => $dasar->where('jalur', Member::JALUR_ALUMNI)->get(),

            DueCategory::TARGET_PENGURUS => $dasar
                ->whereIn('user_id', $this->penggunaBerperan())
                ->get(),

            default => $dasar->whereIn('status', [Member::STATUS_AKTIF, Member::STATUS_ALUMNI])->get(),
        };
    }

    /**
     * Catat pembayaran TUNAI — langsung terverifikasi karena uangnya sudah di
     * tangan Bendahara.
     *
     * @throws ValidationException
     */
    public function catatTunai(DueInvoice $tagihan, int $jumlah, User $petugas, array $kas = []): DuePayment
    {
        $this->pastikanJumlahWajar($tagihan, $jumlah);
        $this->pastikanBelumLunas($tagihan);

        $pembayaran = DB::transaction(function () use ($tagihan, $jumlah, $petugas, $kas): DuePayment {
            $pembayaran = $this->buatPembayaran($tagihan, $jumlah, DuePayment::METODE_TUNAI, null, $kas['catatan'] ?? null);
            $pembayaran->status = DuePayment::STATUS_TERVERIFIKASI;
            $pembayaran->dibayar_pada = now();
            $pembayaran->diverifikasi_oleh = $petugas->id;
            $pembayaran->diverifikasi_pada = now();
            $pembayaran->save();

            $this->lahirkanTransaksiKas($pembayaran, $petugas, $kas);
            $this->tandaiLunas($tagihan);

            return $pembayaran;
        }, 3);

        return $pembayaran;
    }

    /**
     * Anggota mengunggah bukti transfer — statusnya MENUNGGU, belum lunas.
     *
     * @throws ValidationException
     */
    public function ajukanTransfer(DueInvoice $tagihan, int $jumlah, Member $anggota, ?int $buktiMediaId = null, ?string $catatan = null): DuePayment
    {
        $this->pastikanJumlahWajar($tagihan, $jumlah);
        $this->pastikanBelumLunas($tagihan);

        if ($tagihan->member_id !== $anggota->id) {
            throw ValidationException::withMessages([
                'tagihan' => 'Tagihan ini bukan milikmu.',
            ]);
        }

        return DB::transaction(function () use ($tagihan, $jumlah, $anggota, $buktiMediaId, $catatan): DuePayment {
            $pembayaran = $this->buatPembayaran($tagihan, $jumlah, DuePayment::METODE_TRANSFER, $buktiMediaId, $catatan);
            $pembayaran->member_id = $anggota->id;
            $pembayaran->save();

            $tagihan->status = DueInvoice::STATUS_MENUNGGU;
            $tagihan->save();

            return $pembayaran;
        }, 3);
    }

    /**
     * Verifikasi pembayaran transfer: di sinilah kas masuk terbentuk.
     *
     * @throws ValidationException
     */
    public function verifikasi(DuePayment $pembayaran, User $petugas, array $kas = []): DuePayment
    {
        if ($pembayaran->status === DuePayment::STATUS_TERVERIFIKASI) {
            throw ValidationException::withMessages(['pembayaran' => 'Pembayaran ini sudah diverifikasi.']);
        }

        return DB::transaction(function () use ($pembayaran, $petugas, $kas): DuePayment {
            $tagihan = $pembayaran->tagihan;

            $pembayaran->status = DuePayment::STATUS_TERVERIFIKASI;
            $pembayaran->diverifikasi_oleh = $petugas->id;
            $pembayaran->diverifikasi_pada = now();
            $pembayaran->save();

            $this->lahirkanTransaksiKas($pembayaran, $petugas, $kas);
            $this->tandaiLunas($tagihan);

            activity()
                ->performedOn($pembayaran)
                ->withProperties(['tagihan' => $tagihan->id, 'jumlah' => $pembayaran->jumlah])
                ->log('Pembayaran iuran diverifikasi');

            return $pembayaran;
        }, 3);
    }

    /**
     * Tolak bukti pembayaran. Tagihannya kembali "perlu unggah ulang".
     *
     * @throws ValidationException
     */
    public function tolak(DuePayment $pembayaran, User $petugas, string $alasan): DuePayment
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'catatan_bendahara' => 'Alasan penolakan wajib diisi agar anggota tahu apa yang harus diperbaiki.',
            ]);
        }

        return DB::transaction(function () use ($pembayaran, $petugas, $alasan): DuePayment {
            $pembayaran->status = DuePayment::STATUS_DITOLAK;
            $pembayaran->catatan_bendahara = trim($alasan);
            $pembayaran->diverifikasi_oleh = $petugas->id;
            $pembayaran->diverifikasi_pada = now();
            $pembayaran->save();

            $tagihan = $pembayaran->tagihan;
            $tagihan->status = DueInvoice::STATUS_DITOLAK;
            $tagihan->save();

            return $pembayaran;
        }, 3);
    }

    /**
     * Bebaskan tagihan tanpa pembayaran — WAJIB beralasan.
     *
     * @throws ValidationException
     */
    public function bebaskan(DueInvoice $tagihan, User $petugas, string $alasan): DueInvoice
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'dibebaskan_alasan' => 'Alasan pembebasan wajib diisi — tanpa itu, tagihan yang hangus tidak bisa dipertanggungjawabkan.',
            ]);
        }

        $tagihan->status = DueInvoice::STATUS_DIBEBASKAN;
        $tagihan->dibebaskan_alasan = trim($alasan);
        $tagihan->dibebaskan_oleh = $petugas->id;
        $tagihan->save();

        return $tagihan;
    }

    /**
     * Rekap siapa sudah dan belum membayar untuk satu kategori & periode.
     *
     * @return array{tagihan: \Illuminate\Database\Eloquent\Collection<int, DueInvoice>, lunas: int, belum: int, menunggu: int, nominal_terkumpul: int, nominal_target: int}
     */
    public function rekap(DueCategory $kategori, string $periodeLabel): array
    {
        $tagihan = DueInvoice::query()
            ->where('due_category_id', $kategori->id)
            ->where('periode_label', $periodeLabel)
            ->with('anggota')
            ->get()
            ->sortBy(fn (DueInvoice $t): string => (string) $t->anggota?->nama_lengkap)
            ->values();

        return [
            'tagihan' => $tagihan,
            'lunas' => $tagihan->whereIn('status', DueInvoice::STATUS_TUNTAS)->count(),
            'belum' => $tagihan->whereIn('status', [DueInvoice::STATUS_BELUM, DueInvoice::STATUS_DITOLAK])->count(),
            'menunggu' => $tagihan->where('status', DueInvoice::STATUS_MENUNGGU)->count(),
            'nominal_terkumpul' => (int) $tagihan->whereIn('status', DueInvoice::STATUS_TUNTAS)->sum('nominal'),
            'nominal_target' => (int) $tagihan->sum('nominal'),
        ];
    }

    /**
     * Kirim pengingat ke anggota yang tagihannya belum tuntas.
     *
     * MANUAL, bukan penjadwal: yang tahu kapan tagihan pantas diingatkan adalah
     * Bendahara, dan pengingat otomatis untuk iuran himpunan cenderung terasa
     * menagih. Karena manual, tombolnya bisa tertekan dua kali — itulah alasan
     * kolom `pengingat_terakhir_pada` ada.
     *
     * DUA KELOMPOK SENGAJA DILEWATI:
     *  - `menunggu`  : bukti transfernya sudah diunggah. Menagih orang yang
     *                  berkasnya sedang di meja Bendahara itu keliru.
     *  - baru diingatkan dalam JEDA_PENGINGAT_JAM terakhir.
     *
     * `total` adalah jumlah tagihan yang BELUM TUNTAS — tagihan lunas dan
     * dibebaskan tidak ikut dihitung sama sekali, karena memang sudah beres.
     *
     * @return array{terkirim: int, menunggu_verifikasi: int, tanpa_akun: int, baru_diingatkan: int, total: int}
     */
    public function ingatkan(DueCategory $kategori, string $periodeLabel, ?User $petugas = null): array
    {
        $batas = now()->subHours(self::JEDA_PENGINGAT_JAM);

        $tagihan = DueInvoice::query()
            ->where('due_category_id', $kategori->id)
            ->where('periode_label', $periodeLabel)
            ->whereNotIn('status', DueInvoice::STATUS_TUNTAS)
            ->with(['anggota.user', 'kategori'])
            ->get();

        $hasil = [
            'terkirim' => 0,
            'menunggu_verifikasi' => 0,
            'tanpa_akun' => 0,
            'baru_diingatkan' => 0,
            'total' => $tagihan->count(),
        ];

        foreach ($tagihan as $satu) {
            if ($satu->status === DueInvoice::STATUS_MENUNGGU) {
                $hasil['menunggu_verifikasi']++;

                continue;
            }

            $penerima = $satu->anggota?->user;

            // Anggota lama bisa saja tercatat tanpa akun. Melewatinya lebih baik
            // daripada menggagalkan seluruh kiriman.
            if ($penerima === null) {
                $hasil['tanpa_akun']++;

                continue;
            }

            if ($satu->pengingat_terakhir_pada !== null
                && $satu->pengingat_terakhir_pada->greaterThan($batas)) {
                $hasil['baru_diingatkan']++;

                continue;
            }

            $penerima->notify(new PengingatIuran($satu));

            // Ditulis SETELAH notify() supaya notifikasi memegang tagihan yang
            // masih menggambarkan keadaan saat dikirim.
            $satu->pengingat_terakhir_pada = now();
            $satu->pengingat_terkirim = ((int) $satu->pengingat_terkirim) + 1;
            $satu->save();

            $hasil['terkirim']++;
        }

        if ($hasil['terkirim'] > 0) {
            activity()
                ->performedOn($kategori)
                ->withProperties([
                    'periode' => $periodeLabel,
                    'terkirim' => $hasil['terkirim'],
                    'petugas' => $petugas?->id,
                ])
                ->log('Pengingat iuran dikirim');
        }

        return $hasil;
    }

    /* ------------------------------------------------------------------ */

    /**
     * @throws ValidationException
     */
    private function pastikanJumlahWajar(DueInvoice $tagihan, int $jumlah): void
    {
        if ($jumlah <= 0) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah pembayaran harus lebih dari nol.']);
        }

        if ($jumlah > $tagihan->nominal) {
            throw ValidationException::withMessages([
                'jumlah' => 'Jumlah melebihi nominal tagihan (Rp'.number_format($tagihan->nominal, 0, ',', '.').').',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function pastikanBelumLunas(DueInvoice $tagihan): void
    {
        if ($tagihan->tuntas()) {
            throw ValidationException::withMessages([
                'tagihan' => 'Tagihan ini sudah '.strtolower($tagihan->labelStatus()).'.',
            ]);
        }
    }

    private function buatPembayaran(DueInvoice $tagihan, int $jumlah, string $metode, ?int $buktiMediaId, ?string $catatan): DuePayment
    {
        $pembayaran = new DuePayment;
        $pembayaran->due_invoice_id = $tagihan->id;
        $pembayaran->member_id = $tagihan->member_id;
        $pembayaran->jumlah = $jumlah;
        $pembayaran->metode = $metode;
        $pembayaran->bukti_media_id = $buktiMediaId;
        $pembayaran->status = DuePayment::STATUS_MENUNGGU;
        $pembayaran->catatan_pembayar = $catatan;
        $pembayaran->save();

        return $pembayaran;
    }

    /**
     * Lahirkan transaksi kas masuk dan kaitkan ke pembayarannya.
     */
    private function lahirkanTransaksiKas(DuePayment $pembayaran, User $petugas, array $kas): void
    {
        $tagihan = $pembayaran->tagihan;
        $anggota = $tagihan?->anggota;

        $transaksi = $this->kas->catat([
            'account_id' => $kas['account_id'] ?? $this->kas->akunUtama()->id,
            'category_id' => $kas['category_id'] ?? null,
            'tanggal' => $kas['tanggal'] ?? now()->toDateString(),
            'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => $pembayaran->jumlah,
            'keterangan' => 'Iuran '.($tagihan?->kategori?->namaTeks() ?? '').' '.$tagihan?->periode_label
                .' — '.($anggota?->nama_lengkap ?? 'anggota'),
            'sumber' => FinanceTransaction::SUMBER_IURAN,
            'bukti_media_id' => $kas['bukti_media_id'] ?? $pembayaran->bukti_media_id,
            'due_payment_id' => $pembayaran->id,
        ], $petugas);

        // Pembayaran iuran sudah jelas uangnya di tangan, jadi langsung dikonfirmasi.
        $this->kas->konfirmasi($transaksi, $petugas);

        $pembayaran->transaction_id = $transaksi->id;
        $pembayaran->save();
    }

    private function tandaiLunas(DueInvoice $tagihan): void
    {
        $tagihan->status = DueInvoice::STATUS_LUNAS;
        $tagihan->save();
    }

    /**
     * @return array<int, int>
     */
    private function penggunaBerperan(): array
    {
        return DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->pluck('model_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
