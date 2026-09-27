<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buku besar poin kontribusi.
     *
     * POIN TIDAK DISIMPAN SEBAGAI SATU ANGKA DI TABEL ANGGOTA. Ia disimpan
     * sebagai baris-baris peristiwa, sama seperti buku kas: yang tercatat
     * adalah "apa yang terjadi", lalu totalnya dijumlahkan saat dibutuhkan.
     *
     * Alasannya sama dengan alasan pada kas — satu angka yang ditimpa terus
     * tidak bisa menjelaskan dirinya sendiri ketika kader bertanya "kok poin
     * saya 12?". Dengan buku besar, jawabannya ada: 5 dari rapat, 5 dari
     * kajian, 2 penyesuaian karena jadi pemateri.
     *
     * KOLOM `sidik` (fingerprint) bersifat UNIK dan itulah kunci utama tabel
     * ini. Isinya semacam "presensi:kegiatan:12:anggota:5". Berkat itu, poin
     * untuk satu peristiwa tidak mungkin tercatat dua kali walau tombol
     * sinkronisasi ditekan berkali-kali atau dua pengurus menekannya
     * bersamaan.
     */
    public function up(): void
    {
        Schema::create('contribution_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            /*
             * presensi | artikel | prestasi | manual
             *
             * Disimpan sebagai string bebas, bukan enum, supaya menambah sumber
             * baru (mis. "aspirasi") cukup menambah satu kunci di model tanpa
             * migrasi baru.
             */
            $table->string('sumber', 24)->index();

            // Sidik peristiwa. UNIK — inti dari janji "poin tidak pernah ganda".
            $table->string('sidik', 120)->unique();

            // Poin boleh NEGATIF: penyesuaian manual bisa mengurangi, misalnya
            // ketika pemateri seharusnya tidak mendapat poin kehadiran penuh.
            $table->smallInteger('poin');

            // Periode dalam bentuk YYYY-MM, disalin dari tanggal peristiwanya
            // supaya papan peringkat tidak perlu menjalin banyak tabel.
            $table->string('periode_label', 7)->index();

            $table->string('keterangan', 500);
            $table->dateTime('terjadi_pada')->index();

            // Diisi bila poin datang dari kegiatan (presensi).
            $table->foreignId('activity_id')->nullable()->constrained('attendance_activities')->nullOnDelete();

            // Diisi bila poin diberikan manusia (penyesuaian).
            $table->foreignId('diberikan_oleh')->nullable()->constrained('users')->nullOnDelete();

            // Pembatalan, bukan penghapusan — sama seperti void pada kas.
            $table->dateTime('dibatalkan_pada')->nullable();
            $table->string('alasan_pembatalan', 300)->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['member_id', 'periode_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_points');
    }
};
