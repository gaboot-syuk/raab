<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agenda publik unit (biro & LSO).
     *
     * Berbeda dari kalender internal: hanya yang ditandai `publik` yang tampil
     * di halaman LSO, dan agenda yang sudah lewat otomatis masuk arsip.
     */
    public function up(): void
    {
        Schema::create('unit_agendas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')->nullable()->constrained('organisation_units')->nullOnDelete();

            $table->json('judul');
            $table->json('deskripsi')->nullable();

            $table->dateTime('mulai');
            $table->dateTime('selesai')->nullable();
            $table->string('lokasi', 190)->nullable();
            $table->boolean('publik')->default(true)->index();

            $table->timestamps();

            $table->index(['unit_id', 'mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_agendas');
    }
};
