<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel notifikasi dalam aplikasi.
 *
 * Skemanya sengaja memakai bentuk baku Laravel (`notifiable` polimorfik,
 * `data` JSON, `read_at`) supaya kanal `database` bekerja apa adanya — tidak
 * ada gunanya membuat tabel sendiri lalu harus menulis ulang seluruh
 * pengirimannya.
 *
 * SATU BARIS = SATU PENERIMA. Notifikasi yang ditujukan ke lima pengurus
 * tersimpan sebagai lima baris, sehingga tiap orang bisa menandai bacaannya
 * sendiri. Itu memang lebih boros, tetapi "sudah dibaca oleh A" dan "belum
 * dibaca oleh B" tidak bisa diwakili satu baris tanpa tabel penghubung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Lencana "belum dibaca" di sidebar dieksekusi pada SETIAP halaman,
            // jadi indeksnya harus ada sejak awal.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_belum_dibaca_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
