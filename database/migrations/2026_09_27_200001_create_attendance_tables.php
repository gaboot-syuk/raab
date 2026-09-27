<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kegiatan & presensi kader.
     *
     * Tiga tabel, dan pembagiannya sengaja dibuat tajam:
     *
     *  - `attendance_activities` KEGIATANNYA (judul, waktu, lokasi, poin).
     *  - `attendance_rsvps`      JANJI HADIR sebelum kegiatan berlangsung.
     *  - `attendance_records`    KEHADIRAN yang benar-benar tercatat.
     *
     * Janji hadir dan kehadiran nyata TIDAK disatukan dalam satu tabel. Kalau
     * disatukan, rekap kehadiran akan ikut menghitung orang yang berjanji tapi
     * tidak datang — dan angka itu lalu dipakai untuk poin kontribusi.
     *
     * `attendance_records` memakai indeks unik (activity_id, member_id). Itulah
     * yang membuat satu orang hanya bisa presensi SEKALI per kegiatan — bukan
     * pemeriksaan di kode, melainkan aturan di basis data, sehingga tidak bisa
     * dilewati oleh permintaan yang datang bersamaan.
     */
    public function up(): void
    {
        Schema::create('attendance_activities', function (Blueprint $table) {
            $table->id();

            $table->string('kode', 40)->unique();
            $table->json('judul');
            $table->json('deskripsi')->nullable();

            // rapat|kajian|pelatihan|sosial|lainnya
            $table->string('jenis', 24)->default('rapat')->index();

            $table->foreignId('unit_id')->nullable()->constrained('organisation_units')->nullOnDelete();

            $table->dateTime('mulai');
            $table->dateTime('selesai')->nullable();
            $table->string('lokasi', 190)->nullable();

            // manual|qr|keduanya — menentukan cara kehadiran boleh dicatat.
            $table->string('mode_presensi', 16)->default('keduanya');

            // draf|terbuka|selesai|batal
            $table->string('status', 16)->default('draf')->index();

            /*
             * Token QR diputar setiap kali kegiatan dibuka. Token lama mati,
             * sehingga tangkapan layar QR dari kegiatan sebelumnya tidak bisa
             * dipakai untuk mencuri kehadiran.
             */
            $table->string('qr_token', 64)->nullable()->index();
            $table->dateTime('qr_berlaku_sampai')->nullable();

            // Poin yang didapat kader bila hadir. Diambil apa adanya dari
            // kegiatan, bukan dihitung dari jenis — panitia yang menentukan.
            $table->unsignedSmallInteger('poin')->default(0);

            // Kegiatan wajib ditandai agar ketidakhadirannya bisa ditindaklanjuti.
            $table->boolean('wajib')->default(false)->index();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'mulai']);
        });

        Schema::create('attendance_rsvps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activity_id')->constrained('attendance_activities')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            // hadir|tidak_hadir|mungkin
            $table->string('status', 16)->index();
            $table->string('catatan', 500)->nullable();

            $table->timestamps();

            // Satu anggota satu jawaban per kegiatan — jawaban terakhir yang menang.
            $table->unique(['activity_id', 'member_id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activity_id')->constrained('attendance_activities')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            // hadir|terlambat|izin|sakit|alpa
            $table->string('status', 24)->index();

            // manual|qr — supaya bisa ditelusuri cara kehadirannya tercatat.
            $table->string('metode', 16)->index();
            $table->string('catatan', 500)->nullable();

            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dicatat_pada');

            $table->timestamps();

            // ATURAN INTI: satu orang, satu catatan per kegiatan.
            $table->unique(['activity_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_rsvps');
        Schema::dropIfExists('attendance_activities');
    }
};
