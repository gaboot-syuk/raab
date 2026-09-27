<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event kaderisasi (Mapaba, PKD, dan kegiatan lain yang memakai pendaftaran).
     *
     * Kuota `null` berarti TIDAK TERBATAS — dibedakan dengan jelas dari 0 yang
     * berarti tertutup, karena keduanya sering tertukar dan berakibat pendaftaran
     * tertutup tanpa alasan yang terlihat.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->string('jenis', 24)->index();        // mapaba | pkd | lain
            $table->json('judul');
            $table->json('slug');
            $table->json('deskripsi')->nullable();
            $table->json('syarat')->nullable();

            $table->unsignedBigInteger('poster_media_id')->nullable();

            $table->unsignedInteger('kuota')->nullable();   // null = tak terbatas

            $table->dateTime('pendaftaran_dibuka')->nullable();
            $table->dateTime('pendaftaran_ditutup')->nullable();

            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->string('lokasi', 190)->nullable();
            $table->unsignedInteger('biaya')->default(0);

            $table->boolean('aktif')->default(true)->index();
            $table->unsignedInteger('urutan')->default(0);

            $table->timestamps();

            $table->index(['jenis', 'aktif']);
        });

        /*
         * Kolom isian tambahan per event — panitia dapat menambah pertanyaan
         * sendiri (mis. "Ukuran kaos") tanpa perlu mengubah kode.
         */
        Schema::create('event_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->string('kunci', 60);                 // nama kolom pada jawaban
            $table->json('label');
            $table->string('tipe', 24)->default('teks'); // teks|area|angka|tanggal|pilihan|centang

            // Daftar pilihan untuk tipe `pilihan`, mis. {"id": ["S","M","L","XL"]}
            $table->json('pilihan')->nullable();

            $table->boolean('wajib')->default(false);
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);

            $table->timestamps();

            $table->unique(['event_id', 'kunci']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_fields');
        Schema::dropIfExists('events');
    }
};
