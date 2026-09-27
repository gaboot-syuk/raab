<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arsip dokumen.
     *
     * KENDALI AKSES ADA DI `akses` (JSON), bukan di nama kategori. Alasannya:
     * "AD/ART" dan "template surat" bukan pembeda yang bisa dipercaya — AD/ART
     * lazimnya justru boleh dibaca siapa pun, sedangkan notulen rapat internal
     * kadang memuat hal yang belum boleh keluar. Jadi yang menentukan siapa
     * boleh mengunduh adalah daftar audiensnya, bukan kategorinya.
     *
     * BERKASNYA TIDAK DISALIN KE SINI. Ia hidup di pustaka media (Spatie) dan
     * hanya id-nya yang disimpan, supaya satu berkas tidak tersimpan dua kali
     * dengan dua hak akses yang bisa berbeda.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 191)->unique();

            // Judul & keterangan disimpan dua bahasa (id, en).
            $table->json('judul');
            $table->json('keterangan')->nullable();

            // ad_art|template_surat|hasil_rapat|lainnya
            $table->string('kategori', 32)->index();

            // Nomor dokumen resmi, mis. "012/AD/RAAB/IX/2026". Boleh kosong.
            $table->string('nomor', 64)->nullable();

            $table->date('tanggal_dokumen')->nullable()->index();

            // ["publik","kader","alumni","pengurus"] — minimal satu.
            $table->json('akses');

            // Berkas di pustaka media. Null = dokumen masih berupa tautan saja.
            $table->unsignedBigInteger('media_id')->nullable()->index();
            $table->string('tautan_luar', 500)->nullable();

            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
