<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aspirasi kader & pengunjung.
     *
     * DUA HAL YANG BERLAWANAN DI SATU TABEL, dan itu memang disengaja:
     *
     *  - IDENTITAS disimpan lengkap — nama, email, telepon — karena pengurus
     *    harus bisa menghubungi pengirimnya untuk menindaklanjuti;
     *  - yang TAYANG di papan publik hanya judul, isi, status, dan tanggapan
     *    resmi. Nama pengirim tidak pernah ikut, dan isi suratnya dibersihkan
     *    dari nomor telepon, email, serta NIK sebelum ditampilkan.
     *
     * `nomor_tiket` untuk menyebut aspirasi di rapat, `token_lacak` untuk
     * memeriksa keadaan tanpa harus punya akun. Keduanya DIPISAH: nomor tiket
     * berurutan dan mudah ditebak, jadi ia tidak boleh cukup untuk membuka isi
     * surat orang lain.
     */
    public function up(): void
    {
        Schema::create('aspirations', function (Blueprint $table) {
            $table->id();

            $table->string('nomor_tiket', 32)->unique();
            $table->string('token_lacak', 64)->unique();

            // Bila pengirimnya kader yang sedang masuk. Boleh null: form ini
            // juga terbuka bagi pengunjung yang belum punya akun.
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();

            /* Identitas — WAJIB diisi, dan tidak pernah ditampilkan ke publik. */
            $table->string('nama_pengirim', 190);
            $table->string('email_pengirim', 190);
            $table->string('telepon_pengirim', 40)->nullable();

            /*
             * Sakelar milik pengirim: minta namanya tidak disebut di papan.
             * Nama TETAP tersimpan lengkap untuk pengurus — yang berubah hanya
             * penayangannya.
             */
            $table->boolean('anonim')->default(false);

            // akademik|fasilitas|kegiatan|layanan|keuangan|lainnya
            $table->string('kategori', 24)->index();

            $table->json('judul');
            $table->text('isi');

            // baru|dibaca|diproses|selesai|ditolak
            $table->string('status', 24)->default('baru')->index();

            $table->text('tanggapan')->nullable();
            $table->foreignId('ditanggapi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ditanggapi_pada')->nullable();

            /*
             * Boleh tampil di papan publik. Bisa dimatikan pengurus untuk
             * aspirasi yang isinya memuat data pribadi orang lain, atau
             * pengirim yang memang tidak ingin disiarkan sama sekali.
             */
            $table->boolean('tampil_publik')->default(true)->index();

            $table->timestamps();

            $table->index(['status', 'kategori']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspirations');
    }
};
