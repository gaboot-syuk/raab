<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kartu kader digital.
     *
     * Kartu diterbitkan otomatis saat kader diverifikasi (Fase 2) dan
     * dicabut saat status berubah menjadi alumni/nonaktif. Token dipakai
     * untuk halaman verifikasi publik /verifikasi-kader/{token} (Fase 8).
     */
    public function up(): void
    {
        Schema::create('member_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained()->cascadeOnDelete();

            $table->string('nomor_kartu', 40)->unique();
            $table->string('token', 64)->unique();

            $table->string('status', 16)->default('aktif')->index();  // aktif | dicabut
            $table->date('berlaku_sampai')->nullable();

            $table->timestamp('diterbitkan_pada')->nullable();
            $table->timestamp('dicabut_pada')->nullable();
            $table->text('alasan_pencabutan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_cards');
    }
};
