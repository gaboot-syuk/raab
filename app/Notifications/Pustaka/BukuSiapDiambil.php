<?php

namespace App\Notifications\Pustaka;

use App\Models\BookReservation;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Services\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabar "buku siap diambil" untuk anggota yang sedang mengantre.
 *
 * SENGAJA TERPISAH dari KabarPinjaman. Sebelumnya kelas itu dipakai dengan
 * model Loan buatan yang belum tersimpan — dan karena notifikasi berantrean
 * diserialisasi oleh Laravel, model tanpa id gagal dimuat ulang
 * (ModelNotFoundException) tepat saat pesan akan dikirim.
 *
 * Notifikasi ini memegang BookReservation yang SUDAH tersimpan, jadi aman
 * dikirim lewat antrean.
 */
class BukuSiapDiambil extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public function __construct(
        public readonly BookReservation $reservasi,
    ) {}

    public function kategori(): string
    {
        return Notifikasi::PUSTAKA;
    }

    public function judulPusat(): string
    {
        return 'Bukumu siap diambil';
    }

    public function pesanPusat(): string
    {
        return 'Buku yang kamu antrekan sudah tersedia di sekretariat. Reservasi hanya ditahan sementara — ambil sebelum antrean berpindah ke kader berikutnya.';
    }

    public function tautanPusat(): ?string
    {
        return '/pustaka';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reservasi = $this->reservasi;
        $buku = $reservasi->buku;
        $nama = $reservasi->anggota?->nama_lengkap ?? 'Kader';

        $pesan = (new MailMessage)
            ->subject('Buku Siap Diambil — '.($buku?->judulTeks() ?? 'Perpustakaan Rayon'))
            ->greeting('Halo, '.$nama.'!')
            ->line('Giliranmu pada daftar tunggu sudah tiba. Buku berikut sudah menunggu di sekretariat rayon:')
            ->line('**'.($buku?->judulTeks() ?? '—').'**');

        if ($buku?->penulis) {
            $pesan->line('Penulis: '.$buku->penulis);
        }

        if ($reservasi->kedaluwarsa_pada) {
            $pesan->line('Mohon diambil paling lambat **'.$reservasi->kedaluwarsa_pada->translatedFormat('d F Y, H:i').' WIB**.')
                ->line('Bila lewat batas itu, antriannya kami lewati agar kader lain dapat kebagian.');
        }

        return $pesan
            ->line('Rayon ini tidak menerapkan denda. Pengingat ini semata agar buku dapat bergilir.')
            ->line('Terima kasih.');
    }
}
