<?php

namespace App\Providers;

use App\Listeners\CatatPekerjaanGagal;
use App\Listeners\KecilkanGambar;
use App\Support\KataSandi;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Kebijakan kata sandi bawaan untuk SELURUH aplikasi.
         *
         * Fortify (lupa sandi, atur ulang sandi, ubah sandi) memakai
         * `Password::defaults()` bila tidak diberi aturan sendiri — jadi
         * menetapkannya di sini membuat perubahannya berlaku di semua jalur
         * sekaligus, termasuk jalur yang belum ada hari ini.
         */
        Password::defaults(fn () => KataSandi::aturan());

        /*
         * Kegagalan antrean dicatat ke log.
         *
         * Tanpanya, surat yang gagal terkirim hanya meninggalkan baris di tabel
         * `failed_jobs` — tempat yang tidak akan dibuka siapa pun kecuali ada
         * yang sudah tahu ada masalah.
         */
        Event::listen(JobFailed::class, CatatPekerjaanGagal::class);

        /*
         * Gambar dikecilkan tepat setelah berkasnya tersimpan.
         *
         * Foto ponsel 4–6 MB menjadi sekitar 300 KB tanpa beda yang terlihat di
         * layar, sehingga jatah penyimpanan menampung puluhan ribu gambar alih-
         * alih dua ribuan. Dipasang sebagai pendengar peristiwa supaya berlaku
         * untuk SELURUH jalur unggah, termasuk yang belum ada hari ini.
         */
        Event::listen(MediaHasBeenAddedEvent::class, KecilkanGambar::class);
    }
}
