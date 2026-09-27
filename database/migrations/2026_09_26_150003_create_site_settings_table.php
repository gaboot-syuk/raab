<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan situs (key–value) — sumber tunggal untuk identitas rayon:
     * nama, komisariat, cabang, alamat, jam operasional, titik peta, kontak, SEO.
     *
     * Nilai bersifat JSON karena dapat menyimpan dua bahasa.
     * Alamat & detail lain sengaja placeholder selama MVP.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('grup', 50)->default('umum')->index();
            $table->string('kunci', 100)->unique();
            $table->json('nilai')->nullable();
            $table->string('tipe', 20)->default('teks'); // teks, area, angka, boolean, url, gambar
            $table->string('label');
            $table->text('keterangan')->nullable();
            $table->boolean('publik')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
