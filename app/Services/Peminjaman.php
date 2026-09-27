<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookReservation;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\Member;
use App\Models\User;
use App\Notifications\Pustaka\BukuSiapDiambil;
use App\Notifications\Pustaka\KabarPinjaman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bisnis peminjaman buku & aset.
 *
 * TIGA JANJI YANG DIJAGA KELAS INI:
 *  1. STOK SELALU KONSISTEN. Eksemplar berstatus `dipinjam` tepat ketika ada
 *     pinjaman berjalan atasnya — tidak lebih, tidak kurang.
 *  2. ANTRIAN OTOMATIS NAIK saat buku dikembalikan.
 *  3. TIDAK ADA DENDA. Keterlambatan hanya dicatat dan diingatkan.
 *
 * KAPAN EKSEMPLAR DIKUNCI
 * Eksemplar ditandai `dipinjam` sejak pinjaman DISETUJUI, bukan saat diserahkan.
 * Alasannya: begitu disetujui, unit itu sudah dijanjikan. Kalau baru dikunci saat
 * serah terima, dua anggota dapat disetujui untuk eksemplar yang sama.
 */
class Peminjaman
{
    /**
     * Ajukan peminjaman buku.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function ajukanBuku(Book $buku, array $data, ?Member $anggota = null, ?User $petugas = null): Loan
    {
        $eksternal = $anggota === null;

        if ($eksternal) {
            $this->pastikanDataEksternal($data);
        }

        return DB::transaction(function () use ($buku, $data, $anggota, $petugas, $eksternal): Loan {
            /*
             * Eksemplar yang sudah dijanjikan kepada orang di daftar tunggu tidak
             * boleh diserahkan kepada orang lain.
             *
             * Tanpa aturan ini, jatah 48 jam di BookReservation hanya janji
             * kosong: siapa pun bisa menyambar bukunya lebih dulu dari katalog
             * publik, dan anggota yang sudah menunggu giliran tinggal gigit jari.
             */
            $tersedia = BookCopy::query()
                ->where('book_id', $buku->id)
                ->where('status', BookCopy::STATUS_TERSEDIA)
                ->count();

            $ditahan = BookReservation::query()
                ->where('book_id', $buku->id)
                ->where('status', BookReservation::STATUS_SIAP)
                ->when($anggota !== null, fn ($q) => $q->where('member_id', '!=', $anggota->id))
                ->count();

            if ($ditahan > 0 && $ditahan >= $tersedia) {
                throw ValidationException::withMessages([
                    'buku' => 'Eksemplar yang tersisa sedang ditahan untuk anggota di daftar tunggu.',
                ]);
            }

            // Cari eksemplar bebas. Dikunci agar dua pengajuan yang datang
            // bersamaan tidak mendapat eksemplar yang sama.
            $eksemplar = BookCopy::query()
                ->where('book_id', $buku->id)
                ->where('status', BookCopy::STATUS_TERSEDIA)
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            if (! $eksemplar && $anggota !== null) {
                throw ValidationException::withMessages([
                    'buku' => 'Semua eksemplar sedang dipinjam. Kamu dapat masuk daftar tunggu agar diberi tahu saat tersedia.',
                ]);
            }

            if (! $eksemplar) {
                throw ValidationException::withMessages([
                    'buku' => 'Tidak ada eksemplar yang tersedia untuk dipinjam saat ini.',
                ]);
            }

            $pinjaman = new Loan;
            $pinjaman->kode_pinjam = Loan::kodeBaru();
            $pinjaman->book_copy_id = $eksemplar->id;
            $pinjaman->jenis = $eksternal ? Loan::JENIS_EKSTERNAL : Loan::JENIS_INTERNAL;
            $pinjaman->jumlah = 1;
            $pinjaman->member_id = $anggota?->id;
            $pinjaman->status = Loan::STATUS_DIAJUKAN;
            $pinjaman->diajukan_pada = now();
            $this->isiDataPeminjam($pinjaman, $data, $anggota);
            $pinjaman->save();

            // Reservasi anggota atas judul ini dianggap terpenuhi.
            if ($anggota !== null) {
                BookReservation::query()
                    ->where('book_id', $buku->id)
                    ->where('member_id', $anggota->id)
                    ->whereIn('status', BookReservation::STATUS_AKTIF)
                    ->update(['status' => BookReservation::STATUS_DIAMBIL, 'diambil_pada' => now()]);
            }

            return $pinjaman;
        }, 3);
    }

    /**
     * Ajukan peminjaman aset inventaris.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function ajukanAset(InventoryItem $aset, array $data, ?Member $anggota = null, ?User $petugas = null): Loan
    {
        $jumlah = max(1, (int) ($data['jumlah'] ?? 1));
        $eksternal = $anggota === null;

        if ($eksternal) {
            $this->pastikanDataEksternal($data);
        }

        if ($jumlah > $aset->jumlahTersedia()) {
            throw ValidationException::withMessages([
                'jumlah' => 'Hanya '.$aset->jumlahTersedia().' '.$aset->satuan.' yang tersedia (sebagian sedang dipinjam).',
            ]);
        }

        $pinjaman = new Loan;
        $pinjaman->kode_pinjam = Loan::kodeBaru();
        $pinjaman->inventory_item_id = $aset->id;
        $pinjaman->jenis = $eksternal ? Loan::JENIS_EKSTERNAL : Loan::JENIS_INTERNAL;
        $pinjaman->jumlah = $jumlah;
        $pinjaman->member_id = $anggota?->id;
        $pinjaman->status = Loan::STATUS_DIAJUKAN;
        $pinjaman->diajukan_pada = now();
        $this->isiDataPeminjam($pinjaman, $data, $anggota);
        $pinjaman->save();

        return $pinjaman;
    }

    /**
     * Setujui peminjaman dan tentukan jatuh temponya.
     */
    public function setujui(Loan $pinjaman, User $petugas, ?string $catatan = null): Loan
    {
        if ($pinjaman->status !== Loan::STATUS_DIAJUKAN) {
            throw ValidationException::withMessages([
                'pinjaman' => 'Hanya pengajuan yang masih menunggu yang dapat disetujui.',
            ]);
        }

        return DB::transaction(function () use ($pinjaman, $petugas, $catatan): Loan {
            $pinjaman->status = Loan::STATUS_DISETUJUI;
            $pinjaman->jatuh_tempo = now()->addDays(Loan::durasiHariDefault())->endOfDay();
            $pinjaman->disetujui_oleh = $petugas->id;
            $pinjaman->disetujui_pada = now();

            if (filled($catatan)) {
                $pinjaman->catatan_petugas = $catatan;
            }

            // Eksemplar dikunci sejak sekarang: unitnya sudah dijanjikan.
            if ($pinjaman->book_copy_id) {
                BookCopy::query()->whereKey($pinjaman->book_copy_id)
                    ->update(['status' => BookCopy::STATUS_DIPINJAM]);
            }

            $pinjaman->save();

            $this->kabar($pinjaman, KabarPinjaman::DISETUJUI, $catatan);

            return $pinjaman;
        }, 3);
    }

    public function tolak(Loan $pinjaman, User $petugas, string $catatan): Loan
    {
        return DB::transaction(function () use ($pinjaman, $petugas, $catatan): Loan {
            $pinjaman->status = Loan::STATUS_DITOLAK;
            $pinjaman->catatan_petugas = $catatan;
            $pinjaman->disetujui_oleh = $petugas->id;
            $pinjaman->disetujui_pada = now();
            $pinjaman->save();

            $this->lepasEksemplar($pinjaman);
            $this->kabar($pinjaman, KabarPinjaman::DITOLAK, $catatan);

            return $pinjaman;
        }, 3);
    }

    public function batalkan(Loan $pinjaman, ?string $catatan = null): Loan
    {
        return DB::transaction(function () use ($pinjaman, $catatan): Loan {
            $pinjaman->status = Loan::STATUS_BATAL;
            $pinjaman->catatan_petugas = $catatan ?? $pinjaman->catatan_petugas;
            $pinjaman->save();

            $this->lepasEksemplar($pinjaman);
            $this->kabar($pinjaman, KabarPinjaman::DIKEMBALIKAN, $catatan);

            return $pinjaman;
        }, 3);
    }

    /**
     * Serah terima barang kepada peminjam.
     */
    public function serahkan(Loan $pinjaman, User $petugas, string $kondisiKeluar, ?string $catatan = null): Loan
    {
        if (! in_array($pinjaman->status, [Loan::STATUS_DISETUJUI, Loan::STATUS_DIAJUKAN], true)) {
            throw ValidationException::withMessages([
                'pinjaman' => 'Barang hanya dapat diserahkan pada pinjaman yang sudah disetujui.',
            ]);
        }

        $pinjaman->status = Loan::STATUS_DIPINJAM;
        $pinjaman->kondisi_keluar = $kondisiKeluar;
        $pinjaman->diserahkan_oleh = $petugas->id;
        $pinjaman->diserahkan_pada = now();
        $pinjaman->jatuh_tempo ??= now()->addDays(Loan::durasiHariDefault())->endOfDay();

        if (filled($catatan)) {
            $pinjaman->catatan_petugas = $catatan;
        }

        $pinjaman->save();

        $this->kabar($pinjaman, KabarPinjaman::DISERAHKAN, $catatan);

        return $pinjaman;
    }

    /**
     * Terima kembali barang yang dipinjam.
     *
     * INILAH titik di mana antrian naik: begitu eksemplar kembali ke rak, orang
     * berikutnya pada daftar tunggu langsung diberi kabar.
     */
    public function kembalikan(Loan $pinjaman, User $petugas, string $kondisiMasuk, ?string $catatan = null): Loan
    {
        if ($pinjaman->status !== Loan::STATUS_DIPINJAM) {
            throw ValidationException::withMessages([
                'pinjaman' => 'Hanya pinjaman yang sedang berjalan yang dapat dikembalikan.',
            ]);
        }

        return DB::transaction(function () use ($pinjaman, $petugas, $kondisiMasuk, $catatan): Loan {
            $pinjaman->status = Loan::STATUS_DIKEMBALIKAN;
            $pinjaman->kondisi_masuk = $kondisiMasuk;
            $pinjaman->dikembalikan_pada = now();
            $pinjaman->diterima_oleh = $petugas->id;

            if (filled($catatan)) {
                $pinjaman->catatan_petugas = $catatan;
            }

            $pinjaman->save();

            $this->terimaEksemplar($pinjaman, $kondisiMasuk);
            $this->kabar($pinjaman, KabarPinjaman::DIKEMBALIKAN, $catatan);

            return $pinjaman;
        }, 3);
    }

    /**
     * Tandai barang tidak kembali — dicatat, bukan dihitung sebagai denda.
     */
    public function tandaiHilang(Loan $pinjaman, User $petugas, string $catatan): Loan
    {
        return DB::transaction(function () use ($pinjaman, $petugas, $catatan): Loan {
            $pinjaman->status = Loan::STATUS_HILANG;
            $pinjaman->catatan_petugas = $catatan;
            $pinjaman->diterima_oleh = $petugas->id;
            $pinjaman->save();

            if ($pinjaman->book_copy_id) {
                BookCopy::query()->whereKey($pinjaman->book_copy_id)
                    ->update(['status' => BookCopy::STATUS_HILANG, 'catatan' => $catatan]);
            }

            activity()
                ->performedOn($pinjaman)
                ->withProperties(['kode' => $pinjaman->kode_pinjam, 'peminjam' => $pinjaman->namaPeminjam()])
                ->log('Pinjaman ditandai hilang');

            return $pinjaman;
        }, 3);
    }

    /* ------------------------------------------------------------------ */
    /* Perpanjangan                                                        */
    /* ------------------------------------------------------------------ */

    public function ajukanPerpanjangan(Loan $pinjaman, ?string $alasan = null): LoanExtension
    {
        if (! $pinjaman->bolehDiperpanjang()) {
            $maks = (int) \App\Support\Pengaturan::angka('pinjaman_perpanjangan_maks', 1);

            throw ValidationException::withMessages([
                'perpanjangan' => $pinjaman->terlambat()
                    ? 'Pinjaman yang sudah lewat jatuh tempo tidak dapat diperpanjang. Mohon dikembalikan.'
                    : 'Perpanjangan sudah dipakai '.$pinjaman->perpanjangan_ke.' dari maksimal '.$maks.' kali.',
            ]);
        }

        $perpanjangan = new LoanExtension;
        $perpanjangan->loan_id = $pinjaman->id;
        $perpanjangan->alasan = $alasan;
        $perpanjangan->status = LoanExtension::STATUS_DIAJUKAN;
        $perpanjangan->jatuh_tempo_lama = $pinjaman->jatuh_tempo;
        $perpanjangan->save();

        $this->kabar($pinjaman, KabarPinjaman::DIAJUKAN, null, $perpanjangan);

        return $perpanjangan;
    }

    public function setujuiPerpanjangan(LoanExtension $perpanjangan, User $petugas, ?string $catatan = null): LoanExtension
    {
        if ($perpanjangan->status !== LoanExtension::STATUS_DIAJUKAN) {
            throw ValidationException::withMessages(['perpanjangan' => 'Perpanjangan ini sudah diproses.']);
        }

        return DB::transaction(function () use ($perpanjangan, $petugas, $catatan): LoanExtension {
            $pinjaman = $perpanjangan->pinjaman;

            $baru = ($pinjaman->jatuh_tempo ?? now())->copy()->addDays(Loan::durasiHariDefault())->endOfDay();

            $perpanjangan->status = LoanExtension::STATUS_DISETUJUI;
            $perpanjangan->jatuh_tempo_baru = $baru;
            $perpanjangan->diproses_oleh = $petugas->id;
            $perpanjangan->diproses_pada = now();
            $perpanjangan->catatan_petugas = $catatan;
            $perpanjangan->save();

            $pinjaman->jatuh_tempo = $baru;
            $pinjaman->perpanjangan_ke = $pinjaman->perpanjangan_ke + 1;
            // Pengingat lama tidak berlaku lagi untuk tanggal yang baru.
            $pinjaman->ingat_h1_pada = null;
            $pinjaman->ingat_terlambat_pada = null;
            $pinjaman->save();

            $this->kabar($pinjaman, KabarPinjaman::PERPANJANGAN_DISETUJUI, $catatan, $perpanjangan);

            return $perpanjangan;
        }, 3);
    }

    public function tolakPerpanjangan(LoanExtension $perpanjangan, User $petugas, string $catatan): LoanExtension
    {
        if ($perpanjangan->status !== LoanExtension::STATUS_DIAJUKAN) {
            throw ValidationException::withMessages(['perpanjangan' => 'Perpanjangan ini sudah diproses.']);
        }

        $perpanjangan->status = LoanExtension::STATUS_DITOLAK;
        $perpanjangan->diproses_oleh = $petugas->id;
        $perpanjangan->diproses_pada = now();
        $perpanjangan->catatan_petugas = $catatan;
        $perpanjangan->save();

        $this->kabar($perpanjangan->pinjaman, KabarPinjaman::PERPANJANGAN_DITOLAK, $catatan, $perpanjangan);

        return $perpanjangan;
    }

    /* ------------------------------------------------------------------ */
    /* Antrian                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Masukkan anggota ke daftar tunggu judul tertentu.
     */
    public function antri(Book $buku, Member $anggota): BookReservation
    {
        if ($buku->eksemplarTersedia() > 0) {
            throw ValidationException::withMessages([
                'buku' => 'Eksemplar sedang tersedia — langsung ajukan peminjaman tanpa masuk daftar tunggu.',
            ]);
        }

        $sudahAntri = BookReservation::query()
            ->where('book_id', $buku->id)
            ->where('member_id', $anggota->id)
            ->whereIn('status', BookReservation::STATUS_AKTIF)
            ->exists();

        if ($sudahAntri) {
            throw ValidationException::withMessages([
                'buku' => 'Kamu sudah berada di daftar tunggu judul ini.',
            ]);
        }

        $reservasi = new BookReservation;
        $reservasi->book_id = $buku->id;
        $reservasi->member_id = $anggota->id;
        $reservasi->status = BookReservation::STATUS_MENUNGGU;
        $reservasi->posisi = $this->posisiBerikutnya($buku);
        $reservasi->save();

        return $reservasi;
    }

    /**
     * Naikkan antrian terdepan menjadi "siap diambil".
     *
     * Dipanggil otomatis setiap ada eksemplar yang kembali.
     */
    public function naikkanAntrian(Book $buku): ?BookReservation
    {
        if ($buku->eksemplarTersedia() < 1) {
            return null;
        }

        $berikutnya = BookReservation::query()
            ->where('book_id', $buku->id)
            ->where('status', BookReservation::STATUS_MENUNGGU)
            ->orderBy('posisi')
            ->orderBy('id')
            ->first();

        if (! $berikutnya) {
            return null;
        }

        $berikutnya->status = BookReservation::STATUS_SIAP;
        $berikutnya->siap_pada = now();
        $berikutnya->kedaluwarsa_pada = now()->addHours(BookReservation::BERLAKU_JAM);
        $berikutnya->save();

        $this->kabarkanReservasi($berikutnya);

        return $berikutnya;
    }

    /**
     * Lewati antrian yang masa berlakunya sudah lewat.
     *
     * @return int jumlah antrian yang dilewati
     */
    public function lewatiKedaluwarsa(): int
    {
        $kedaluwarsa = BookReservation::query()->kedaluwarsa()->with('buku')->get();
        $jumlah = 0;

        foreach ($kedaluwarsa as $reservasi) {
            $reservasi->status = BookReservation::STATUS_KEDALUWARSA;
            $reservasi->save();
            $jumlah++;

            // Yang berikutnya langsung diberi kesempatan.
            if ($reservasi->buku) {
                $this->naikkanAntrian($reservasi->buku);
            }
        }

        return $jumlah;
    }

    /* ------------------------------------------------------------------ */
    /* Pengingat                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Email pengingat H-1 untuk pinjaman yang jatuh tempo besok.
     *
     * @return int jumlah pengingat terkirim
     */
    public function kirimPengingatH1(): int
    {
        $daftar = Loan::query()
            ->jatuhTempoBesok()
            ->whereNull('ingat_h1_pada')
            ->with(['anggota.user', 'eksemplar.buku', 'aset'])
            ->get();

        foreach ($daftar as $pinjaman) {
            $this->kabar($pinjaman, KabarPinjaman::PENGINGAT_H1);
            $pinjaman->forceFill(['ingat_h1_pada' => now()->toDateString()])->save();
        }

        return $daftar->count();
    }

    /**
     * Pengingat harian untuk pinjaman yang sudah lewat jatuh tempo.
     *
     * @return int jumlah pengingat terkirim
     */
    public function kirimPengingatTerlambat(): int
    {
        $daftar = Loan::query()
            ->terlambat()
            ->where(fn ($q) => $q->whereNull('ingat_terlambat_pada')
                ->orWhere('ingat_terlambat_pada', '<', now()->toDateString()))
            ->with(['anggota.user', 'eksemplar.buku', 'aset'])
            ->get();

        foreach ($daftar as $pinjaman) {
            $this->kabar($pinjaman, KabarPinjaman::TERLAMBAT);
            $pinjaman->forceFill(['ingat_terlambat_pada' => now()->toDateString()])->save();
        }

        return $daftar->count();
    }

    /* ------------------------------------------------------------------ */
    /* Bantuan internal                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function pastikanDataEksternal(array $data): void
    {
        if (blank($data['peminjam_nama'] ?? null)) {
            throw ValidationException::withMessages([
                'peminjam_nama' => 'Peminjaman eksternal wajib menyebutkan nama peminjam.',
            ]);
        }

        // Inilah syarat yang membedakan peminjaman luar dari kehilangan aset:
        // selalu ada anggota yang bertanggung jawab mengembalikannya.
        if (blank($data['penanggung_jawab_id'] ?? null)) {
            throw ValidationException::withMessages([
                'penanggung_jawab_id' => 'Peminjaman eksternal wajib memiliki penanggung jawab internal dari anggota rayon.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isiDataPeminjam(Loan $pinjaman, array $data, ?Member $anggota): void
    {
        $pinjaman->peminjam_nama = $anggota?->nama_lengkap ?? ($data['peminjam_nama'] ?? null);
        $pinjaman->peminjam_kontak = $anggota?->telepon ?? ($data['peminjam_kontak'] ?? null);
        $pinjaman->peminjam_instansi = $data['peminjam_instansi'] ?? null;
        $pinjaman->penanggung_jawab_id = $data['penanggung_jawab_id'] ?? null;
        $pinjaman->catatan_peminjam = $data['catatan_peminjam'] ?? null;
    }

    /**
     * Kembalikan eksemplar ke rak dan panggil antrian berikutnya.
     */
    private function terimaEksemplar(Loan $pinjaman, string $kondisiMasuk): void
    {
        if (! $pinjaman->book_copy_id) {
            return;
        }

        $status = match ($kondisiMasuk) {
            'rusak_berat' => BookCopy::STATUS_PERBAIKAN,
            'hilang' => BookCopy::STATUS_HILANG,
            default => BookCopy::STATUS_TERSEDIA,
        };

        BookCopy::query()->whereKey($pinjaman->book_copy_id)->update([
            'status' => $status,
            'kondisi' => $kondisiMasuk,
        ]);

        // Buku kembali ke rak → giliran berikutnya naik.
        $buku = $pinjaman->eksemplar?->buku;

        if ($status === BookCopy::STATUS_TERSEDIA && $buku) {
            $this->naikkanAntrian($buku);
        }
    }

    /**
     * Lepaskan eksemplar tanpa mengubah kondisinya.
     */
    private function lepasEksemplar(Loan $pinjaman): void
    {
        if (! $pinjaman->book_copy_id) {
            return;
        }

        $eksemplar = BookCopy::query()->find($pinjaman->book_copy_id);

        if ($eksemplar && $eksemplar->status === BookCopy::STATUS_DIPINJAM) {
            $eksemplar->status = BookCopy::STATUS_TERSEDIA;
            $eksemplar->save();

            if ($eksemplar->buku) {
                $this->naikkanAntrian($eksemplar->buku);
            }
        }
    }

    private function posisiBerikutnya(Book $buku): int
    {
        return (int) BookReservation::query()
            ->where('book_id', $buku->id)
            ->whereIn('status', BookReservation::STATUS_AKTIF)
            ->max('posisi') + 1;
    }

    /**
     * Kirim kabar ke peminjam atau penanggung jawabnya.
     */
    public function kabar(Loan $pinjaman, string $keadaan, ?string $catatan = null, ?LoanExtension $perpanjangan = null): void
    {
        $pinjaman->loadMissing(['anggota.user', 'penanggungJawab.user']);

        $tujuan = $pinjaman->anggota?->user?->email;

        // Peminjaman eksternal: kabar dikirim ke penanggung jawab internal,
        // karena hanya dia yang punya akun dan dapat ditindaklanjuti.
        if (blank($tujuan)) {
            $tujuan = $pinjaman->penanggungJawab?->user?->email;
        }

        if (blank($tujuan)) {
            return;
        }

        Notification::route('mail', $tujuan)
            ->notify(new KabarPinjaman($pinjaman, $keadaan, $catatan, $perpanjangan));
    }

    private function kabarkanReservasi(BookReservation $reservasi): void
    {
        $reservasi->loadMissing('anggota.user', 'buku');

        $tujuan = $reservasi->anggota?->user?->email;

        if (blank($tujuan) || ! $reservasi->buku) {
            return;
        }

        // Memakai notifikasi khusus reservasi — TIDAK membuat model Loan
        // buatan, karena notifikasi berantrean diserialisasi Laravel dan model
        // tanpa id akan gagal dimuat ulang saat pesan dikirim.
        Notification::route('mail', $tujuan)->notify(new BukuSiapDiambil($reservasi));
    }
}
