<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Album foto unit (biro & LSO) dan kegiatan publik unit.
     *
     * Album boleh menempel pada unit tertentu, atau berdiri sendiri sebagai
     * galeri rayon (mis. dokumentasi Mapaba) dengan `unit_id` kosong.
     */
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')->nullable()->constrained('organisation_units')->nullOnDelete();

            $table->json('judul');          // dwibahasa
            $table->json('slug');
            $table->json('deskripsi')->nullable();

            $table->date('tanggal')->nullable()->index();
            $table->string('lokasi', 160)->nullable();
            $table->boolean('publik')->default(true)->index();
            $table->unsignedInteger('urutan')->default(0);

            $table->timestamps();

            $table->index(['unit_id', 'publik']);
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // Foto dapat berasal dari Pustaka Media atau URL luar.
            $table->unsignedBigInteger('media_id')->nullable();
            $table->string('url', 500)->nullable();

            $table->json('keterangan')->nullable();
            $table->unsignedInteger('urutan')->default(0);

            $table->timestamps();

            $table->index(['gallery_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('galleries');
    }
};
