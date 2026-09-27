<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bisnis inventaris.
 *
 * SATU PINTU PERUBAHAN STOK. Seluruh perubahan `InventoryItem::$jumlah` harus
 * lewat kelas ini supaya:
 *  1. Setiap perubahan meninggalkan jejak mutasi (angka sebelum & sesudah).
 *  2. Barang rusak dan hilang WAJIB menyebut penanggung jawab.
 *  3. Stok tidak pernah bisa menjadi negatif.
 */
class Inventaris
{
    /**
     * Catat satu mutasi stok.
     *
     * @param  array<string, mixed>  $opsi  penanggung_jawab_id, penanggung_jawab_nama,
     *                                      event_id, catatan, terjadi_pada, kondisi_baru
     *
     * @throws ValidationException
     */
    public function catat(
        InventoryItem $item,
        string $jenis,
        int $jumlah,
        array $opsi = [],
        ?User $pencatat = null,
    ): InventoryMovement {
        if (! array_key_exists($jenis, InventoryMovement::JENIS)) {
            throw ValidationException::withMessages(['jenis' => 'Jenis mutasi tidak dikenal.']);
        }

        if ($jumlah <= 0) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah mutasi harus lebih dari nol.']);
        }

        // Barang rusak & hilang adalah kehilangan aset organisasi — harus jelas
        // siapa yang bertanggung jawab.
        if (in_array($jenis, InventoryMovement::WAJIB_PENANGGUNG_JAWAB, true)
            && blank($opsi['penanggung_jawab_id'] ?? null)
            && blank($opsi['penanggung_jawab_nama'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'penanggung_jawab_nama' => 'Barang rusak atau hilang wajib mencantumkan penanggung jawab.',
            ]);
        }

        return DB::transaction(function () use ($item, $jenis, $jumlah, $opsi, $pencatat): InventoryMovement {
            // Kunci baris aset selama perhitungan agar dua pencatatan yang
            // bersamaan tidak menghasilkan angka stok yang salah.
            $aset = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            $sebelum = $aset->jumlah;
            $arah = InventoryMovement::ARAH[$jenis] ?? 0;
            $sesudah = max(0, $sebelum + ($arah * $jumlah));

            if ($jenis === InventoryMovement::JENIS_KELUAR && $jumlah > $sebelum) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Stok tersisa hanya '.$sebelum.' '.$aset->satuan.'. Tidak dapat mengeluarkan '.$jumlah.'.',
                ]);
            }

            $mutasi = new InventoryMovement;
            $mutasi->item_id = $aset->id;
            $mutasi->jenis = $jenis;
            $mutasi->jumlah = $jumlah;
            $mutasi->jumlah_sebelum = $sebelum;
            $mutasi->jumlah_sesudah = $sesudah;
            $mutasi->penanggung_jawab_id = $opsi['penanggung_jawab_id'] ?? null;
            $mutasi->penanggung_jawab_nama = $opsi['penanggung_jawab_nama'] ?? null;
            $mutasi->event_id = $opsi['event_id'] ?? null;
            $mutasi->dicatat_oleh = $pencatat?->id;
            $mutasi->catatan = $opsi['catatan'] ?? null;
            $mutasi->terjadi_pada = $opsi['terjadi_pada'] ?? now();
            $mutasi->save();

            // Kondisi aset ikut berubah bila mutasinya menyangkut kerusakan,
            // kecuali pengurus menyebut kondisi baru secara eksplisit.
            $aset->jumlah = $sesudah;
            $aset->kondisi = $opsi['kondisi_baru'] ?? $this->kondisiSetelahMutasi($aset, $jenis, $sesudah);
            $aset->save();

            activity()
                ->performedOn($aset)
                ->withProperties([
                    'jenis' => $jenis,
                    'jumlah' => $jumlah,
                    'sebelum' => $sebelum,
                    'sesudah' => $sesudah,
                    'penanggung_jawab' => $mutasi->namaPenanggungJawab(),
                ])
                ->log('Mutasi inventaris: '.$mutasi->labelJenis());

            return $mutasi;
        }, 3);
    }

    /**
     * Kondisi aset setelah mutasi.
     *
     * Barang rusak berat membuat seluruh stok ditandai rusak berat; perbaikan
     * mengembalikannya ke baik. Mutasi lain tidak menyentuh kondisi.
     */
    private function kondisiSetelahMutasi(InventoryItem $aset, string $jenis, int $sesudah): string
    {
        if ($sesudah === 0) {
            return InventoryItem::KONDISI_RUSAK_BERAT;
        }

        return match ($jenis) {
            InventoryMovement::JENIS_RUSAK => InventoryItem::KONDISI_RUSAK_RINGAN,
            InventoryMovement::JENIS_PERBAIKAN => InventoryItem::KONDISI_BAIK,
            default => $aset->kondisi,
        };
    }

    /**
     * Ringkasan mutasi terakhir sebuah aset.
     *
     * @return \Illuminate\Support\Collection<int, InventoryMovement>
     */
    public function riwayat(InventoryItem $item, int $batas = 50)
    {
        return $item->mutasi()->with(['penanggungJawab:id,nama_lengkap', 'pencatat:id,name'])->limit($batas)->get();
    }

    /**
     * Daftar aset yang stoknya sudah menyentuh batas minimum.
     *
     * @return \Illuminate\Support\Collection<int, InventoryItem>
     */
    public function stokMenipis()
    {
        return InventoryItem::query()
            ->aktif()
            ->where('jumlah_minimum', '>', 0)
            ->whereColumn('jumlah', '<=', 'jumlah_minimum')
            ->orderBy('jumlah')
            ->get();
    }
}
