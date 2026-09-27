<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Katalog buku & eksemplarnya.
     *
     * Judul buku dan eksemplar fisik dipisah karena satu judul dapat memiliki
     * beberapa salinan. PEMINJAMAN SELALU MENYENTUH EKSEMPLAR, bukan judulnya —
     * itu sebabnya ketersediaan dihitung dari `book_copies`, bukan dari `books`.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->json('judul');
            $table->json('slug');
            $table->json('sinopsis')->nullable();

            $table->string('penulis', 190)->nullable()->index();
            $table->string('penerbit', 190)->nullable();
            $table->unsignedSmallInteger('tahun_terbit')->nullable();
            $table->string('isbn', 32)->nullable()->index();
            $table->string('ddc', 32)->nullable();       // nomor klasifikasi Dewey
            $table->string('kategori', 120)->nullable()->index();
            $table->string('bahasa', 12)->default('id');
            $table->unsignedSmallInteger('jumlah_halaman')->nullable();

            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->string('rak', 60)->nullable();

            $table->boolean('is_public')->default(true)->index();
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();
        });

        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')->constrained()->cascadeOnDelete();

            $table->string('kode_eksemplar', 60)->unique();
            $table->string('kondisi', 24)->default('baik');   // baik|rusak_ringan|rusak_berat
            $table->string('status', 24)->default('tersedia')->index(); // tersedia|dipinjam|perbaikan|hilang
            $table->string('rak', 60)->nullable();
            $table->date('tanggal_perolehan')->nullable();
            $table->unsignedBigInteger('nilai')->default(0);
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['book_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('books');
    }
};
