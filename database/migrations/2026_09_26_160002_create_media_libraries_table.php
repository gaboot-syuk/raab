<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wadah (holder) untuk pustaka media.
     *
     * Spatie MediaLibrary menempelkan setiap berkas pada sebuah model. Karena
     * pustaka media bersifat global (bukan milik satu halaman tertentu), dipakai
     * satu baris tunggal sebagai wadah. Pengelompokan dilakukan lewat kolom
     * "collection" bawaan paket (mis. gambar, dokumen, logo).
     */
    public function up(): void
    {
        Schema::create('media_libraries', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_libraries');
    }
};
