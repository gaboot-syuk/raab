<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gambar untuk agenda unit.
     *
     * Disimpan sebagai RUJUKAN ke tabel `media`, bukan sebagai berkas di kolom
     * sendiri — mengikuti pola poster Event. Dengan begitu penggantian ukuran,
     * pembersihan berkas saat agenda dihapus, dan pemilihan gambar dari
     * pustaka media semuanya ditangani satu tempat.
     *
     * Kolomnya nullable: agenda tanpa gambar tetap sah dan tetap tampil.
     */
    public function up(): void
    {
        Schema::table('unit_agendas', function (Blueprint $table) {
            $table->unsignedBigInteger('gambar_media_id')->nullable()->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('unit_agendas', function (Blueprint $table) {
            $table->dropColumn('gambar_media_id');
        });
    }
};
