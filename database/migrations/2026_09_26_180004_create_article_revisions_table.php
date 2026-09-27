<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi artikel — menyimpan 10 versi terakhir per artikel.
     *
     * Dipakai untuk melihat perubahan isi dan memulihkan naskah sebelumnya.
     * Versi lama dipangkas otomatis oleh model (lihat ArticleRevision::simpan()).
     */
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->json('judul');
            $table->json('konten')->nullable();
            $table->string('status', 24)->nullable();
            $table->string('catatan', 255)->nullable();

            $table->timestamps();

            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};
