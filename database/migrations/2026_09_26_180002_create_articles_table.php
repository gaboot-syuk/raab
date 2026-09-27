<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artikel: berita, opini, kajian, esai, sastra, dan berita acara.
     *
     * ALUR STATUS
     *   draf → menunggu_review → (terbit | perlu_revisi → menunggu_review | ditolak)
     *
     * CATATAN PENTING
     * - Seluruh kolom isi bersifat dwibahasa (json), mengikuti pola halaman statis.
     * - `dijadwalkan_pada` memungkinkan penerbitan otomatis oleh penjadwal.
     * - Kolom `nomor_dokumen` … `penandatangan` hanya dipakai berita acara.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // penulis
            $table->foreignId('kategori_id')->nullable()->constrained('article_categories')->nullOnDelete();
            $table->unsignedBigInteger('cover_media_id')->nullable();

            $table->string('tipe', 24)->index();        // berita|opini|kajian|esai|sastra|berita_acara
            $table->string('status', 24)->default('draf')->index();

            $table->json('judul');
            $table->json('slug');
            $table->json('ringkasan')->nullable();
            $table->json('konten')->nullable();
            $table->json('seo_judul')->nullable();
            $table->json('seo_deskripsi')->nullable();

            // --- Penjadwalan & tampilan ---
            $table->timestamp('dijadwalkan_pada')->nullable()->index();
            $table->timestamp('terbit_pada')->nullable()->index();
            $table->boolean('unggulan')->default(false)->index();
            $table->unsignedBigInteger('dilihat')->default(0);
            $table->unsignedSmallInteger('waktu_baca_menit')->nullable();

            // --- Alur review ---
            $table->text('catatan_review')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->timestamp('direview_pada')->nullable();

            // --- Berita acara (tipe berita_acara) ---
            $table->string('nomor_dokumen', 120)->nullable()->index();
            $table->date('tanggal_agenda')->nullable();
            $table->text('agenda')->nullable();
            $table->text('keputusan')->nullable();
            $table->string('penandatangan', 160)->nullable();
            $table->string('jabatan_penandatangan', 160)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipe', 'status', 'terbit_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
