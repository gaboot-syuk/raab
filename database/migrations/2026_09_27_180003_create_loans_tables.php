<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Peminjaman buku, perpanjangan, dan antrian reservasi.
     *
     * SATU TABEL UNTUK DUA JENIS PEMINJAM:
     *  - internal  → peminjamnya anggota (kader/alumni), `member_id` terisi
     *  - eksternal → dicatat Sekretaris atas nama pihak luar; `member_id` kosong
     *                TETAPI `penanggung_jawab_id` WAJIB terisi
     *
     * TANPA DENDA. Keterlambatan hanya dicatat dan diingatkan lewat email —
     * keputusan pemilik produk, jadi jangan tambahkan perhitungan denda.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            $table->string('kode_pinjam', 40)->unique();

            // Yang dipinjam: buku (lewat eksemplar) ATAU aset inventaris.
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            $table->string('jenis', 16)->default('internal')->index();  // internal|eksternal

            /*
             * Jumlah unit yang dipinjam.
             *
             * Untuk buku selalu 1 (satu eksemplar fisik). Untuk aset inventaris
             * boleh lebih — inilah sebabnya kolom ini ada, bukan sekadar 1
             * yang tersirat dari `book_copy_id`.
             */
            $table->unsignedInteger('jumlah')->default(1);

            // Peminjam internal
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();

            // Peminjam eksternal
            $table->string('peminjam_nama', 160)->nullable();
            $table->string('peminjam_kontak', 120)->nullable();
            $table->string('peminjam_instansi', 190)->nullable();

            // WAJIB untuk peminjaman eksternal — ditegakkan di layanan.
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('members')->nullOnDelete();

            $table->string('status', 24)->default('diajukan')->index();
            // diajukan|disetujui|ditolak|dipinjam|dikembalikan|hilang|batal

            $table->unsignedTinyInteger('perpanjangan_ke')->default(0);

            $table->dateTime('diajukan_pada')->nullable();
            $table->dateTime('jatuh_tempo')->nullable()->index();

            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disetujui_pada')->nullable();

            $table->foreignId('diserahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diserahkan_pada')->nullable();

            $table->dateTime('dikembalikan_pada')->nullable();
            $table->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->string('kondisi_keluar', 24)->default('baik');
            $table->string('kondisi_masuk', 24)->nullable();

            $table->text('catatan_peminjam')->nullable();
            $table->text('catatan_petugas')->nullable();

            // Kapan pengingat terakhir dikirim, agar tidak berulang tiap hari.
            $table->date('ingat_h1_pada')->nullable();
            $table->date('ingat_terlambat_pada')->nullable();

            $table->timestamps();

            $table->index(['member_id', 'status']);
        });

        Schema::create('loan_extensions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();

            $table->text('alasan')->nullable();
            $table->string('status', 24)->default('diajukan')->index(); // diajukan|disetujui|ditolak

            $table->dateTime('jatuh_tempo_lama')->nullable();
            $table->dateTime('jatuh_tempo_baru')->nullable();

            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diproses_pada')->nullable();
            $table->text('catatan_petugas')->nullable();

            $table->timestamps();
        });

        /*
         * Antrian reservasi buku.
         *
         * Dibuat saat semua eksemplar sedang dipinjam. Begitu satu eksemplar
         * kembali, yang paling depan otomatis berstatus `siap` dan diberi masa
         * berlaku 2×24 jam; lewat dari itu antriannya dilewati.
         */
        Schema::create('book_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();

            $table->string('status', 24)->default('menunggu')->index();
            // menunggu|siap|diambil|kedaluwarsa|batal

            // Urutan antrian saat dibuat; yang lebih kecil dilayani lebih dulu.
            $table->unsignedInteger('posisi')->default(0)->index();

            $table->dateTime('siap_pada')->nullable();
            $table->dateTime('kedaluwarsa_pada')->nullable()->index();
            $table->dateTime('diambil_pada')->nullable();

            $table->date('ingat_siap_pada')->nullable();

            $table->text('catatan')->nullable();

            $table->timestamps();

            /*
             * SENGAJA TIDAK ADA indeks unik (book_id, member_id, status):
             * indeks semacam itu akan mengunci status `menunggu` selamanya,
             * sehingga anggota yang antriannya sudah kedaluwarsa TIDAK BISA
             * memesan ulang judul yang sama. Aturan "satu antrian aktif per
             * orang per judul" ditegakkan di layanan, tempat aturan itu dapat
             * menjelaskan alasannya kepada anggota.
             */
            $table->index(['book_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_reservations');
        Schema::dropIfExists('loan_extensions');
        Schema::dropIfExists('loans');
    }
};
