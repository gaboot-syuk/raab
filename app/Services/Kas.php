<?php

namespace App\Services;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SATU-SATUNYA PINTU PERUBAHAN SALDO KAS.
 *
 * TIGA JANJI YANG DIJAGA KELAS INI:
 *
 *  1. SALDO TIDAK PERNAH BERUBAH TANPA JEJAK. `finance_accounts.saldo_berjalan`
 *     hanya bergerak di dalam transaksi basis data di kelas ini, dan setiap
 *     pergerakan menyimpan `saldo_sebelum` & `saldo_sesudah` pada barisnya.
 *
 *  2. HANYA TRANSAKSI TERKONFIRMASI YANG MENYENTUH SALDO. Mencatat transaksi
 *     tidak mengubah apa pun (status `draft`); baru setelah dikonfirmasi saldo
 *     bergerak. Ini yang memisahkan "sudah saya tulis" dari "uangnya sudah
 *     benar-benar pindah".
 *
 *  3. TIDAK ADA HAPUS. Salah input diselesaikan dengan `void()` + alasan wajib,
 *     dan saldonya dikoreksi kembali. Menghapus baris akan menghilangkan bukti
 *     bahwa kesalahan itu pernah terjadi — justru itu yang dicari auditor.
 *
 * SALDO TIDAK BOLEH NEGATIF. Bukan karena sistemnya tidak sanggup, tetapi karena
 * kas fisik tidak bisa minus: angka negatif hampir selalu berarti salah ketik
 * (mis. 10.000.000 padahal 1.000.000). Lebih baik ditolak di depan.
 */
class Kas
{
    /**
     * Catat transaksi baru berstatus `draft`.
     *
     * @param  array<string, mixed>  $data  account_id, category_id, tanggal, jenis,
     *                                      jumlah, keterangan, sumber, event_id,
     *                                      budget_id, bukti_media_id
     *
     * @throws ValidationException
     */
    public function catat(array $data, ?User $pencatat = null): FinanceTransaction
    {
        $jenis = (string) ($data['jenis'] ?? '');

        if (! array_key_exists($jenis, FinanceTransaction::JENIS)) {
            throw ValidationException::withMessages(['jenis' => 'Jenis transaksi tidak dikenal.']);
        }

        $jumlah = (int) ($data['jumlah'] ?? 0);

        if ($jumlah <= 0) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah transaksi harus lebih dari nol.']);
        }

        $akun = FinanceAccount::query()->find($data['account_id'] ?? null) ?? $this->akunUtama();
        if (! $akun->aktif) {
            throw ValidationException::withMessages(['account_id' => 'Akun kas ini sudah dinonaktifkan.']);
        }

        // Kategori harus sejalan dengan jenis transaksi — kategori penerimaan
        // tidak boleh dipakai untuk pengeluaran.
        if (! empty($data['category_id'])) {
            $kategori = FinanceCategory::query()->find($data['category_id']);

            if (! $kategori) {
                throw ValidationException::withMessages(['category_id' => 'Kategori tidak ditemukan.']);
            }

            if ($kategori->jenis !== $jenis) {
                throw ValidationException::withMessages([
                    'category_id' => 'Kategori "'.$kategori->labelLengkap().'" untuk '.strtolower($kategori->labelJenis())
                        .', tidak cocok dengan transaksi '.strtolower(FinanceTransaction::JENIS[$jenis]).'.',
                ]);
            }
        }

        // Nominal besar wajib ada buktinya.
        if ($jumlah > FinanceTransaction::BATAS_WAJIB_BUKTI && empty($data['bukti_media_id'])) {
            throw ValidationException::withMessages([
                'bukti_media_id' => 'Transaksi di atas Rp'.number_format(FinanceTransaction::BATAS_WAJIB_BUKTI, 0, ',', '.')
                    .' wajib melampirkan bukti/nota.',
            ]);
        }

        return DB::transaction(function () use ($data, $jenis, $jumlah, $pencatat): FinanceTransaction {
            $transaksi = new FinanceTransaction;
            $transaksi->account_id = $data['account_id'];
            $transaksi->category_id = $data['category_id'] ?? null;
            $transaksi->nomor_voucher = FinanceTransaction::nomorVoucherBaru();
            $transaksi->tanggal = $data['tanggal'] ?? now()->toDateString();
            $transaksi->jenis = $jenis;
            $transaksi->jumlah = $jumlah;
            $transaksi->keterangan = trim((string) ($data['keterangan'] ?? ''));
            $transaksi->sumber = $data['sumber'] ?? FinanceTransaction::SUMBER_LAIN;
            $transaksi->event_id = $data['event_id'] ?? null;
            $transaksi->budget_id = $data['budget_id'] ?? null;
            $transaksi->due_payment_id = $data['due_payment_id'] ?? null;
            $transaksi->donation_id = $data['donation_id'] ?? null;
            $transaksi->bukti_media_id = $data['bukti_media_id'] ?? null;
            $transaksi->status = FinanceTransaction::STATUS_DRAFT;
            $transaksi->dicatat_oleh = $pencatat?->id;
            $transaksi->save();

            activity()
                ->performedOn($transaksi)
                ->withProperties([
                    'nomor_voucher' => $transaksi->nomor_voucher,
                    'jenis' => $jenis,
                    'jumlah' => $jumlah,
                    'akun' => $transaksi->akun?->namaTeks(),
                    'sumber' => $transaksi->sumber,
                ])
                ->log('Transaksi kas dicatat (draft)');

            return $transaksi;
        }, 3);
    }

    /**
     * Konfirmasi transaksi: di sinilah saldo akun benar-benar bergerak.
     *
     * @throws ValidationException
     */
    public function konfirmasi(FinanceTransaction $transaksi, User $petugas): FinanceTransaction
    {
        if ($transaksi->status === FinanceTransaction::STATUS_TERKONFIRMASI) {
            throw ValidationException::withMessages([
                'transaksi' => 'Transaksi ini sudah dikonfirmasi sebelumnya.',
            ]);
        }

        if ($transaksi->status === FinanceTransaction::STATUS_VOID) {
            throw ValidationException::withMessages([
                'transaksi' => 'Transaksi yang sudah di-void tidak dapat dikonfirmasi lagi.',
            ]);
        }

        return DB::transaction(function () use ($transaksi, $petugas): FinanceTransaction {
            // Kunci baris akun selama perhitungan agar dua konfirmasi yang
            // bersamaan tidak menghasilkan saldo yang salah.
            $akun = FinanceAccount::query()->lockForUpdate()->findOrFail($transaksi->account_id);

            $sebelum = $akun->saldo_berjalan;
            $sesudah = $sebelum + ($transaksi->arah() * $transaksi->jumlah);

            if ($sesudah < 0) {
                throw ValidationException::withMessages([
                    'transaksi' => 'Saldo '.$akun->namaTeks().' hanya Rp'.number_format($sebelum, 0, ',', '.')
                        .'. Pengeluaran Rp'.number_format($transaksi->jumlah, 0, ',', '.')
                        .' akan membuat saldo negatif.',
                ]);
            }

            $transaksi->status = FinanceTransaction::STATUS_TERKONFIRMASI;
            $transaksi->dikonfirmasi_oleh = $petugas->id;
            $transaksi->dikonfirmasi_pada = now();
            $transaksi->saldo_sebelum = $sebelum;
            $transaksi->saldo_sesudah = $sesudah;
            $transaksi->save();

            $akun->saldo_berjalan = $sesudah;
            $akun->save();

            activity()
                ->performedOn($transaksi)
                ->withProperties([
                    'nomor_voucher' => $transaksi->nomor_voucher,
                    'saldo_sebelum' => $sebelum,
                    'saldo_sesudah' => $sesudah,
                ])
                ->log('Transaksi kas dikonfirmasi');

            return $transaksi;
        }, 3);
    }

    /**
     * Void transaksi: saldo dikoreksi kembali, barisnya tetap ada.
     *
     * `saldo_sebelum`/`saldo_sesudah` SENGAJA tidak ditimpa. Angka itu adalah
     * catatan historis tentang apa yang pernah terjadi; yang berubah hanya
     * saldo akun dan status barisnya.
     *
     * @throws ValidationException
     */
    public function void(FinanceTransaction $transaksi, User $petugas, string $alasan): FinanceTransaction
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'void_alasan' => 'Alasan void wajib diisi — inilah satu-satunya jejak mengapa saldo dikoreksi.',
            ]);
        }

        if ($transaksi->status === FinanceTransaction::STATUS_VOID) {
            throw ValidationException::withMessages([
                'transaksi' => 'Transaksi ini sudah di-void sebelumnya.',
            ]);
        }

        return DB::transaction(function () use ($transaksi, $petugas, $alasan): FinanceTransaction {
            $akun = FinanceAccount::query()->lockForUpdate()->findOrFail($transaksi->account_id);

            // Transaksi draft belum pernah menyentuh saldo, jadi tidak ada yang
            // perlu dikembalikan — cukup ditandai batal.
            if ($transaksi->status === FinanceTransaction::STATUS_TERKONFIRMASI) {
                $akun->saldo_berjalan = $akun->saldo_berjalan - ($transaksi->arah() * $transaksi->jumlah);
                $akun->save();
            }

            $transaksi->status = FinanceTransaction::STATUS_VOID;
            $transaksi->void_oleh = $petugas->id;
            $transaksi->void_pada = now();
            $transaksi->void_alasan = trim($alasan);
            $transaksi->save();

            activity()
                ->performedOn($transaksi)
                ->withProperties([
                    'nomor_voucher' => $transaksi->nomor_voucher,
                    'alasan' => $alasan,
                    'saldo_setelah_koreksi' => $akun->saldo_berjalan,
                ])
                ->log('Transaksi kas di-void');

            return $transaksi;
        }, 3);
    }

    /**
     * Akun kas bawaan (Kas Utama bila ada, kalau tidak akun aktif pertama).
     *
     * Dipakai saat petugas tidak memilih akun — mis. pembayaran iuran yang
     * selalu masuk Kas Utama.
     *
     * @throws ValidationException
     */
    public function akunUtama(): FinanceAccount
    {
        $akun = FinanceAccount::query()->aktif()->where('jenis', FinanceAccount::JENIS_UTAMA)->urut()->first()
            ?? FinanceAccount::query()->aktif()->urut()->first();

        if (! $akun) {
            throw ValidationException::withMessages([
                'account_id' => 'Belum ada akun kas aktif. Buat akun kas terlebih dahulu di panel Keuangan.',
            ]);
        }

        return $akun;
    }

    /**
     * Saldo yang SEHARUSNYA menurut buku kas.
     *
     * Dipakai untuk memeriksa kewarasan: hasilnya harus selalu sama dengan
     * `saldo_berjalan`. Bila berbeda, ada jalur yang menulis saldo di luar
     * kelas ini — persis yang tidak boleh terjadi.
     */
    public function saldoSeharusnya(FinanceAccount $akun): int
    {
        $masuk = (int) $akun->transaksi()->terkonfirmasi()->where('jenis', FinanceTransaction::JENIS_MASUK)->sum('jumlah');
        $keluar = (int) $akun->transaksi()->terkonfirmasi()->where('jenis', FinanceTransaction::JENIS_KELUAR)->sum('jumlah');

        return (int) $akun->saldo_awal + $masuk - $keluar;
    }

    /**
     * Apakah saldo tersimpan masih cocok dengan buku kas?
     */
    public function saldoSehat(FinanceAccount $akun): bool
    {
        return $akun->saldo_berjalan === $this->saldoSeharusnya($akun);
    }
}
