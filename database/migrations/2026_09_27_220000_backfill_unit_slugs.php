<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Mengisi slug unit yang masih kosong.
     *
     * KENAPA INI PERLU, PADAHAL SUDAH ADA MIGRASI SLUG
     * -------------------------------------------------
     * Migrasi `add_slug_to_organisation_units` menambahkan kolomnya lalu
     * mengisi baris yang SUDAH ADA saat itu. Baris yang dibuat sesudahnya
     * bergantung pada pengait model — dan ternyata ada jalur yang tidak
     * melewatinya, sehingga unit di hosting punya slug NULL.
     *
     * Akibatnya bukan sekadar alamat yang aneh. `/lso` memanggil
     * route('public.lso.detail', null), dan Laravel tidak mengabaikan
     * parameternya: ia melempar UrlGenerationException. Satu unit tanpa slug
     * mematikan SELURUH halaman daftar LSO — halaman publik yang tidak ada
     * hubungannya dengan data yang belum lengkap itu.
     *
     * Migrasi ini idempoten dan dijalankan setiap deploy lewat
     * `migrate --force`, jadi data yang telanjur rusak ikut sembuh tanpa perlu
     * akses shell — yang memang tidak tersedia di paket gratis.
     */
    public function up(): void
    {
        $terpakai = DB::table('organisation_units')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->pluck('slug')
            ->all();

        $kosong = DB::table('organisation_units')
            ->where(fn ($q) => $q->whereNull('slug')->orWhere('slug', ''))
            ->orderBy('id')
            ->get(['id', 'nama']);

        foreach ($kosong as $baris) {
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
        // Sengaja tidak membatalkan apa pun.
        //
        // Mengosongkan kembali slug hanya akan mematikan halaman publik yang
        // baru saja pulih, dan tidak mengembalikan apa yang hilang — slugnya
        // sendiri tidak pernah dipakai untuk hal lain.
    }
};
