<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biro (8) dan Lembaga Semi Otonom (5) di dalam rayon.
     *
     * Dibuat pada Fase 2 karena keanggotaan perlu mencatat asal unit kader;
     * pengelolaan penuhnya (galeri, agenda, pengurus unit) menyusul di Fase 4.
     */
    public function up(): void
    {
        Schema::create('organisation_units', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 16)->index();       // biro | lso
            $table->string('nama', 120);
            $table->string('singkatan', 40)->nullable();
            $table->json('deskripsi')->nullable();      // dwibahasa
            $table->string('warna', 24)->nullable();    // token warna token desain
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();

            $table->unique(['jenis', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_units');
    }
};
