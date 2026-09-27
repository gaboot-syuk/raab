<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

/**
 * Mencatat pekerjaan antrean yang gagal.
 *
 * Hampir semua pekerjaan di aplikasi ini adalah PENGIRIMAN SURAT: pengingat
 * peminjaman, pemberitahuan verifikasi, pengumuman. Ketika pengiriman gagal,
 * kegagalannya terjadi di latar belakang — tidak ada yang melihat layar merah,
 * tidak ada yang melapor, dan surat itu tidak pernah sampai. Satu-satunya jejak
 * yang tersisa adalah baris di tabel `failed_jobs`, yang tidak akan dibuka
 * siapa pun kecuali ada yang memberitahunya.
 *
 * Karena itu kegagalan dicatat ke log aplikasi juga, lengkap dengan NAMA
 * pekerjaannya supaya bisa dibedakan: surat apa, ke antrean mana, karena apa.
 */
class CatatPekerjaanGagal
{
    public function handle(JobFailed $event): void
    {
        Log::error('Pekerjaan antrean gagal.', [
            'pekerjaan' => $this->namaPekerjaan($event),
            'antrean' => $this->namaAntrean($event),
            'sambungan' => $event->connectionName,
            'galat' => $event->exception->getMessage(),
        ]);
    }

    private function namaPekerjaan(JobFailed $event): string
    {
        try {
            return $event->job->resolveName();
        } catch (\Throwable) {
            // Pekerjaan bisa rusak justru karena muatannya tidak terbaca.
            // Nama yang tidak diketahui tidak boleh menggagalkan pencatatan.
            return '(tidak diketahui)';
        }
    }

    private function namaAntrean(JobFailed $event): string
    {
        try {
            return (string) $event->job->getQueue();
        } catch (\Throwable) {
            return '(tidak diketahui)';
        }
    }
}
