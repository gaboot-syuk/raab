<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `anonim` dibuang.
 *
 * Semula dipakai untuk membiarkan pengirim memilih namanya disembunyikan di
 * papan publik. Setelah menyesuaikan diri dengan dokumen alur bisnis, papan
 * publik memang TIDAK PERNAH memuat nama pengirim sama sekali — jadi sakelar
 * itu menyiratkan pilihan yang tidak pernah ada artinya, dan justru membuat
 * orang mengira namanya bakal dipajang kalau ia tidak mencentangnya.
 *
 * Nama, email, dan telepon tetap tersimpan penuh untuk pengurus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aspirations', function (Blueprint $table): void {
            $table->dropColumn('anonim');
        });
    }

    public function down(): void
    {
        Schema::table('aspirations', function (Blueprint $table): void {
            $table->boolean('anonim')->default(false)->after('telepon_pengirim');
        });
    }
};
