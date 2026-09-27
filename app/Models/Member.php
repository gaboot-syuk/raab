<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Identitas keanggotaan — jantung seluruh modul setelah Fase 2.
 *
 * PERBEDAAN STATUS vs PERAN
 * - `status` di sini adalah status KEANGGOTAAN (menunggu/aktif/alumni/nonaktif).
 * - Peran kepengurusan (superadmin, sekretaris, …) dipegang Spatie Permission
 *   pada model User. Keduanya berdampingan, bukan saling menggantikan.
 *
 * PRIVASI
 * Data sensitif hanya ditampilkan di halaman publik bila pemiliknya
 * mengizinkan (lihat bolehTampil()).
 */
#[Fillable([
    'user_id',
    'nomor_anggota',
    'slug',
    'jalur',
    'status',
    'nama_lengkap',
    'nama_panggilan',
    'jenis_kelamin',
    'tempat_lahir',
    'tanggal_lahir',
    'nim',
    'fakultas',
    'program_studi',
    'angkatan',
    'alamat',
    'telepon',
    'email_kontak',
    'foto_media_id',
    'unit_id',
    'keahlian',
    'sosmed',
    'privasi',
    'profil_publik',
    'sidik_data',
    'diverifikasi_oleh',
    'diverifikasi_pada',
    'catatan_verifikasi',
])]
class Member extends Model
{
    use SoftDeletes;

    public const JALUR_KADER = 'kader';
    public const JALUR_ALUMNI = 'alumni';

    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_AKTIF = 'aktif';
    public const STATUS_ALUMNI = 'alumni';
    public const STATUS_NONAKTIF = 'nonaktif';
    public const STATUS_DITOLAK = 'ditolak';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_MENUNGGU => 'Menunggu Verifikasi',
        self::STATUS_AKTIF => 'Kader Aktif',
        self::STATUS_ALUMNI => 'Alumni',
        self::STATUS_NONAKTIF => 'Nonaktif',
        self::STATUS_DITOLAK => 'Ditolak',
    ];

    /**
     * @var array<string, string>
     */
    public const JALUR = [
        self::JALUR_KADER => 'Kader Aktif',
        self::JALUR_ALUMNI => 'Alumni',
    ];

    /**
     * Kolom yang boleh tampil di halaman publik, beserta labelnya.
     *
     * @var array<string, string>
     */
    public const KOLOM_PUBLIK = [
        'instansi' => 'Instansi',
        'program_studi' => 'Program Studi',
        'angkatan' => 'Angkatan',
        'kota_domisili' => 'Domisili',
        'telepon' => 'Nomor Telepon',
        'email' => 'Email',
        'sosmed' => 'Media Sosial',
        'keahlian' => 'Keahlian',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'angkatan' => 'integer',
            'keahlian' => 'array',
            'sosmed' => 'array',
            'privasi' => 'array',
            'profil_publik' => 'boolean',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'unit_id');
    }

    public function kartu(): HasOne
    {
        return $this->hasOne(MemberCard::class);
    }

    public function profilAlumni(): HasOne
    {
        return $this->hasOne(AlumniProfile::class);
    }

    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(MemberStatusHistory::class)->latest();
    }

    public function pengajuan(): HasMany
    {
        return $this->hasMany(MemberApplication::class);
    }

    /**
     * Penugasan kepengurusan yang pernah dan sedang dipegang anggota ini.
     */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    /**
     * Karya tulis yang pernah dimuat atas nama anggota ini.
     */
    public function karya(): HasMany
    {
        return $this->hasMany(Article::class, 'user_id', 'user_id');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AKTIF);
    }

    public function scopeAlumni(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ALUMNI);
    }

    /**
     * Anggota yang boleh muncul di direktori publik.
     */
    public function scopeDapatTampilPublik(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_AKTIF, self::STATUS_ALUMNI]);
    }

    /**
     * Anggota yang membuka halaman profilnya untuk umum.
     *
     * Sengaja terpisah dari `dapatTampilPublik()`: muncul di direktori (nama,
     * angkatan, unit) berbeda dari menyediakan halaman profil lengkap.
     */
    public function scopeProfilTerbuka(Builder $query): Builder
    {
        return $query->where('profil_publik', true)->dapatTampilPublik();
    }

    /**
     * Cari berdasarkan slug; halaman publik tidak boleh membocorkan id.
     */
    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /* ------------------------------------------------------------------ */
    /* Bantuan                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Slug publik dibuat sendiri agar setiap anggota punya alamat yang stabil
     * tanpa harus disentuh pengurus.
     */
    protected static function booted(): void
    {
        static::saving(function (self $member): void {
            if (filled($member->slug) || blank($member->nama_lengkap)) {
                return;
            }

            $member->slug = self::slugUnik($member->nama_lengkap, $member->id);
        });
    }

    /**
     * Slug unik dari nama lengkap, mis. "ahmad-fauzi" lalu "ahmad-fauzi-2".
     */
    public static function slugUnik(string $nama, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($nama);

        if ($dasar === '') {
            $dasar = 'anggota';
        }

        $slug = $dasar;
        $urutan = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists()
        ) {
            $slug = $dasar.'-'.$urutan;
            $urutan++;
        }

        return $slug;
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }

    public function labelJalur(): string
    {
        return self::JALUR[$this->jalur] ?? ucfirst($this->jalur);
    }

    public function telahDiverifikasi(): bool
    {
        return in_array($this->status, [self::STATUS_AKTIF, self::STATUS_ALUMNI], true);
    }

    /**
     * Apakah sebuah kolom boleh ditampilkan di halaman publik?
     *
     * Bawaan: tertutup. Pemilik data harus menyatakan setuju — kecuali untuk
     * kolom yang memang bersifat publik (angkatan, program studi, keahlian).
     */
    public function bolehTampil(string $kolom): bool
    {
        if (! $this->telahDiverifikasi()) {
            return false;
        }

        $bawaanTerbuka = ['angkatan', 'program_studi', 'keahlian', 'sosmed'];

        $privasi = $this->privasi ?? [];

        if (array_key_exists($kolom, $privasi)) {
            return (bool) $privasi[$kolom];
        }

        return in_array($kolom, $bawaanTerbuka, true);
    }

    /**
     * Persentase kelengkapan profil (untuk mengingatkan kader melengkapi data).
     */
    public function kelengkapanProfil(): int
    {
        $wajib = [
            'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'nim', 'fakultas', 'program_studi', 'angkatan', 'alamat', 'telepon',
        ];

        $terisi = 0;

        foreach ($wajib as $kolom) {
            if (filled($this->{$kolom})) {
                $terisi++;
            }
        }

        return (int) round($terisi / count($wajib) * 100);
    }

    /**
     * Data ringkas untuk direktori publik.
     *
     * TIDAK memuat identitas apa pun — dan itu disengaja lebih kuat daripada
     * penyaringan tampilan: nama, slug, NIM, telepon, email, alamat, keahlian,
     * serta tautan media sosial tidak pernah diambil dari basis data untuk
     * halaman ini. Yang dihapus hanya di tampilan tetap bisa bocor lewat
     * cache, log, atau berkas HTML yang tersimpan; yang tidak pernah diambil
     * tidak bisa bocor sama sekali.
     *
     * `slug` juga sengaja tidak ikut. Tautan ke /prestasi/kader/{slug} memuat
     * nama orang di dalam alamatnya, sehingga menampilkannya sama saja
     * menuliskan namanya.
     *
     * Yang tersisa adalah atribut akademis dan organisatoris yang memang sudah
     * publik sejak awal.
     *
     * @return array<string, mixed>
     */
    public function untukDirektori(): array
    {
        return [
            'angkatan' => $this->angkatan,
            'fakultas' => $this->fakultas,
            'program_studi' => $this->bolehTampil('program_studi') ? $this->program_studi : null,
            'unit' => $this->unit?->nama,
        ];
    }
}
