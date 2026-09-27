<?php

namespace App\Notifications\Pustaka;

use App\Models\Loan;
use App\Models\LoanExtension;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Services\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Satu kelas untuk seluruh kabar peminjaman — mengikuti pola KabarArtikel dan
 * KabarPendaftaran, supaya isi pesan tidak tersebar di banyak berkas.
 *
 * TANPA DENDA: pesan keterlambatan hanya mengingatkan, tidak pernah menyebut
 * biaya. Ini keputusan produk yang disengaja.
 */
class KabarPinjaman extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public const DIAJUKAN = 'diajukan';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    public const DISERAHKAN = 'diserahkan';

    public const DIKEMBALIKAN = 'dikembalikan';

    public const SIAP_DIAMBIL = 'siap_diambil';

    public const PENGINGAT_H1 = 'pengingat_h1';

    public const TERLAMBAT = 'terlambat';

    public const PERPANJANGAN_DISETUJUI = 'perpanjangan_disetujui';

    public const PERPANJANGAN_DITOLAK = 'perpanjangan_ditolak';

    public function __construct(
        public readonly Loan $pinjaman,
        public readonly string $keadaan = self::DIAJUKAN,
        public readonly ?string $catatan = null,
        public readonly ?LoanExtension $perpanjangan = null,
    ) {}

    public function kategori(): string
    {
        return Notifikasi::PUSTAKA;
    }

    public function judulPusat(): string
    {
        return match ($this->keadaan) {
            self::DIAJUKAN => 'Pengajuan peminjaman diterima',
            self::DISETUJUI => 'Peminjaman disetujui',
            self::DITOLAK => 'Peminjaman belum dapat disetujui',
            self::DISERAHKAN => 'Barang sudah diserahkan',
            self::DIKEMBALIKAN => 'Pengembalian diterima',
            self::SIAP_DIAMBIL => 'Buku siap diambil',
            self::PENGINGAT_H1 => 'Jatuh tempo besok',
            self::TERLAMBAT => 'Sudah lewat jatuh tempo',
            self::PERPANJANGAN_DISETUJUI => 'Perpanjangan disetujui',
            self::PERPANJANGAN_DITOLAK => 'Perpanjangan belum disetujui',
            default => 'Kabar peminjaman',
        };
    }

    public function pesanPusat(): string
    {
        $pesan = 'Peminjaman '.$this->pinjaman->kode_pinjam.'.';

        return $this->catatan !== null && $this->catatan !== ''
            ? $pesan.' Catatan: '.$this->catatan
            : $pesan;
    }

    public function tautanPusat(): ?string
    {
        return '/pustaka';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pinjaman = $this->pinjaman;
        $barang = $pinjaman->apaYangDipinjam();

        $pesan = (new MailMessage)
            ->greeting('Halo, '.$pinjaman->namaPeminjam().'!')
            ->line('Peminjaman **'.$barang.'** (kode '.$pinjaman->kode_pinjam.')');

        switch ($this->keadaan) {
            case self::DISETUJUI:
                $pesan->subject('Peminjaman Disetujui — '.$pinjaman->kode_pinjam)
                    ->line('telah DISETUJUI. Silakan diambil di sekretariat rayon.');
                break;

            case self::DITOLAK:
                $pesan->subject('Peminjaman Belum Dapat Disetujui — '.$pinjaman->kode_pinjam)
                    ->line('belum dapat disetujui.');
                break;

            case self::DISERAHKAN:
                $pesan->subject('Barang Sudah Diserahkan — '.$pinjaman->kode_pinjam)
                    ->line('sudah diserahkan kepadamu. Mohon dijaga dengan baik.');
                break;

            case self::DIKEMBALIKAN:
                $pesan->subject('Pengembalian Diterima — '.$pinjaman->kode_pinjam)
                    ->line('sudah kami terima kembali. Terima kasih!');
                break;

            case self::SIAP_DIAMBIL:
                $pesan->subject('Buku Siap Diambil — '.$pinjaman->kode_pinjam)
                    ->line('sudah siap diambil di sekretariat. Giliranmu sudah tiba.');
                break;

            case self::PENGINGAT_H1:
                $pesan->subject('Pengingat: Jatuh Tempo Besok — '.$pinjaman->kode_pinjam)
                    ->line('akan jatuh tempo BESOK. Mohon disiapkan pengembaliannya.');
                break;

            case self::TERLAMBAT:
                $pesan->subject('Pengingat: Sudah Lewat Jatuh Tempo — '.$pinjaman->kode_pinjam)
                    ->line('sudah melewati jatuh tempo.');
                break;

            case self::PERPANJANGAN_DISETUJUI:
                $pesan->subject('Perpanjangan Disetujui — '.$pinjaman->kode_pinjam)
                    ->line('diperpanjang. Jatuh tempo baru menyusul di bawah.');
                break;

            case self::PERPANJANGAN_DITOLAK:
                $pesan->subject('Perpanjangan Belum Disetujui — '.$pinjaman->kode_pinjam)
                    ->line('belum dapat diperpanjang. Mohon dikembalikan sesuai jadwal.');
                break;

            default:
                $pesan->subject('Pengajuan Peminjaman Diterima — '.$pinjaman->kode_pinjam)
                    ->line('sudah kami terima dan menunggu persetujuan Sekretaris.');
                break;
        }

        if (filled($this->catatan)) {
            $pesan->line('Catatan petugas: '.$this->catatan);
        }

        $jatuhTempo = $this->perpanjangan?->jatuh_tempo_baru ?? $pinjaman->jatuh_tempo;

        if ($jatuhTempo && in_array($this->keadaan, [
            self::DISETUJUI, self::DISERAHKAN, self::PENGINGAT_H1,
            self::TERLAMBAT, self::PERPANJANGAN_DISETUJUI,
        ], true)) {
            $pesan->line('Jatuh tempo: '.$jatuhTempo->translatedFormat('d F Y'));
        }

        if ($this->keadaan === self::TERLAMBAT) {
            $pesan->line('Rayon ini **tidak menerapkan denda**. Pengingat ini semata agar buku dapat dibaca kader lain.');
        }

        return $pesan->line('Terima kasih.');
    }
}
