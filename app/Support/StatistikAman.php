<?php

namespace App\Support;

/**
 * Penyamaran angka statistik di halaman publik.
 *
 * MASALAH YANG DISELESAIKAN
 *
 * Direktori publik bisa disaring sampai tinggal satu orang. Rincian statistik
 * seperti "Angkatan 2024: 1" lalu memberi tahu siapa pun bahwa hanya ada satu
 * kader dengan ciri itu — cukup untuk mempersempit seseorang sampai ketemu,
 * tanpa namanya pernah tampil sekali pun. Nama memang dihapus, tetapi angka
 * yang terlalu kecil mengembalikan identitas itu lewat pintu belakang.
 *
 * Karena itu kelompok yang jumlahnya di bawah ambang tidak ditampilkan
 * angkanya, melainkan "<5".
 *
 * KENAPA AMBANGNYA TIDAK BISA DIUBAH DARI PANEL
 *
 * Nilai yang bisa diturunkan pengurus tanpa jejak bukan lagi pengaman. Ambang
 * ini sengaja tetap di kode supaya menurunkannya harus lewat perubahan kode
 * yang terlihat dan tercatat di riwayat git.
 */
final class StatistikAman
{
    /**
     * Jumlah terkecil yang boleh ditampilkan apa adanya.
     */
    public const AMBANG = 5;

    /**
     * Angka siap tampil: nilai aslinya, atau "<5" bila kelompoknya terlalu kecil.
     */
    public static function samar(int $jumlah): string
    {
        return $jumlah < self::AMBANG ? '<'.self::AMBANG : (string) $jumlah;
    }

    /**
     * Apakah rincian ini terlalu kecil untuk ditampilkan angkanya.
     */
    public static function terlaluKecil(int $jumlah): bool
    {
        return $jumlah < self::AMBANG;
    }
}
