<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Alamat publik unit: `/lso/{slug}`, dan nanti `/biro/{slug}`.
     *
     * Memakai slug (bukan id) supaya alamatnya enak dibagikan; diisi otomatis
     * dari nama unit untuk data yang sudah ada.
     */
    public function up(): void
    {
        Schema::table('organisation_units', function (Blueprint $table) {
            $table->string('slug', 140)->nullable()->unique()->after('nama');
        });

        $terpakai = [];

        foreach (DB::table('organisation_units')->orderBy('id')->get(['id', 'nama']) as $baris) {
            $dasar = Str::slug((string) $baris->nama);

            if ($dasar === '') {
                $dasar = 'unit';
            }

            $slug = $dasar;
            $urutan = 2;

            while (in_array($slug, $terpakai, true)) {
                $slug = $dasar.'-'.$urutan;
                $urutan++;
            }

            $terpakai[] = $slug;

            DB::table('organisation_units')->where('id', $baris->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('organisation_units', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
