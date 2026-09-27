<?php

namespace App\Notifications\Concerns;

use App\Services\Notifikasi;

/**
 * Menjadikan sebuah notifikasi ikut masuk ke pusat notifikasi dalam aplikasi.
 *
 * SEBELUMNYA SEMUA notifikasi di aplikasi ini hanya lewat email. Akibatnya
 * kabar seperti "naskahmu perlu direvisi" hanya singgah di kotak surat, dan
 * hilang begitu saja dari pandangan orang yang sedang bekerja di dalam
 * aplikasi. Trait ini membuat setiap notifikasi muncul di dua tempat sekaligus.
 *
 * KELAS YANG MEMAKAI TRAIT INI CUKUP MENGISI EMPAT METODE KECIL, dan tidak
 * boleh lagi menulis `via()` sendiri — di situlah aturan "email atau tidak"
 * hidup, dan menuliskannya ulang di tiap kelas berarti cepat atau lambat ada
 * satu kelas yang lupa menghormati preferensi pengguna.
 */
trait MasukPusatNotifikasi
{
    /**
     * Kategori notifikasi; menentukan sakelar preferensi mana yang berlaku.
     */
    abstract public function kategori(): string;

    /**
     * Judul pendek untuk daftar di pusat notifikasi.
     */
    abstract public function judulPusat(): string;

    /**
     * Satu-dua kalimat isi pesan.
     */
    abstract public function pesanPusat(): string;

    /**
     * Tautan ke halaman yang bersangkutan, bila ada.
     */
    abstract public function tautanPusat(): ?string;

    /**
     * TIDAK BOLEH ditimpa kecuali benar-benar diperlukan — lihat catatan kelas.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return Notifikasi::kanal($notifiable, $this->kategori());
    }

    /**
     * Bentuk data yang disimpan di tabel `notifications`.
     *
     * Bentuknya SERAGAM untuk semua kategori supaya halaman pusat notifikasi
     * tidak perlu tahu-menahu soal kelas notifikasi mana pun — ia hanya membaca
     * kunci yang sama dari setiap baris.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kategori' => $this->kategori(),
            'ikon' => Notifikasi::ikon($this->kategori()),
            'judul' => $this->judulPusat(),
            'pesan' => $this->pesanPusat(),
            'tautan' => $this->tautanPusat(),
        ];
    }
}
