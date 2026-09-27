<?php

namespace App\Providers;

use App\Listeners\CatatPekerjaanGagal;
use App\Support\KataSandi;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }
}
