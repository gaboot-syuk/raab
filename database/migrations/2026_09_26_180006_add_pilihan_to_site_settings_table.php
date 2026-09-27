<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar pilihan untuk pengaturan bertipe dropdown.
     *
     * Sebelumnya daftar pilihan hendak ditulis pada kolom `tipe`
     * (mis. "pilihan:none,deepl,google"), tetapi kolom itu terlalu pendek dan
     * mencampur "jenis" dengan "nilai yang sah". Dipisahkan ke kolom sendiri
     * agar jelas dan tidak terbatas panjangnya.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('pilihan')->nullable()->after('tipe');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('pilihan');
        });
    }
};
