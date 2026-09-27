<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slider pada "Tampilan Utama" beranda.
     */
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->json('judul');
            $table->json('subjudul')->nullable();
            $table->json('label_tombol')->nullable();
            $table->string('tautan_tombol')->nullable();

            $table->unsignedBigInteger('media_id')->nullable()->index();

            $table->unsignedInteger('urutan')->default(0)->index();
            $table->boolean('aktif')->default(true)->index();
            $table->timestamp('mulai_pada')->nullable();
            $table->timestamp('berakhir_pada')->nullable();

            $table->unsignedBigInteger('dibuat_oleh')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};
