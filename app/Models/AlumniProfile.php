<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil tambahan bagi anggota berstatus alumni.
 *
 * Kolom `kontak_publik` mengatur bagian mana yang boleh tampil di direktori
 * alumni — pemilik data yang memutuskan, bukan pengurus.
 */
#[Fillable([
    'member_id',
    'tahun_lulus',
    'instansi',
    'jabatan',
    'bidang',
    'kota_domisili',
    'latitude',
    'longitude',
    'kontak_publik',
    'bersedia_mentor',
    'topik_mentor',
    'catatan',
])]
class AlumniProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kontak_publik' => 'array',
            'bersedia_mentor' => 'boolean',
            'tahun_lulus' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Apakah bagian tertentu boleh tampil di direktori publik?
     */
    public function bolehTampil(string $bagian): bool
    {
        $pengaturan = $this->kontak_publik ?? [];

        // Bawaan: tertutup. Alumni memilih sendiri apa yang dibagikan.
        return (bool) ($pengaturan[$bagian] ?? false);
    }
}
