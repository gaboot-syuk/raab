<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periode kepengurusan rayon (mis. 2024/2025).
     *
     * Satu periode ditandai aktif; data periode lama tetap tersimpan utuh
     * sehingga bagan struktur per periode dapat dibuka kembali (Fase 4).
     */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 60);              // "2024/2025"
            $table->unsignedSmallInteger('tahun_mulai');
            $table->unsignedSmallInteger('tahun_selesai');
            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->boolean('aktif')->default(false)->index();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['tahun_mulai', 'tahun_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
