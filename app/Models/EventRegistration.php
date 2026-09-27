<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pendaftar sebuah event — TANPA akun.
 *
 * Urutan status yang masuk akal:
 *   menunggu → terverifikasi → hadir
 *              ↘ ditolak
 *   (batal dapat dilakukan pendaftar maupun panitia)
 *
 * `hadir` adalah kolom tersendiri di samping `status` karena kehadiran dicatat
 * saat hari-H dan tidak boleh menghapus jejak bahwa peserta pernah DIVERIFIKASI.
 */
#[Fillable([
    'event_id',
    'kode_pendaftaran',
    'nama_lengkap',
    'email',
    'telepon',
    'jenis_kelamin',
    'tempat_lahir',
    'tanggal_lahir',
    'nim',
    'fakultas',
    'program_studi',
    'angkatan',
    'instansi',
    'alamat',
    'status',
    'catatan_peserta',
    'catatan_panitia',
    'hadir',
    'sidik_data',
    'diverifikasi_oleh',
    'diverifikasi_pada',
    'member_id',
    'dipromosikan_pada',
    'ip',
    'user_agent',
])]
class EventRegistration extends Model
{
    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_TERVERIFIKASI = 'terverifikasi';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_HADIR = 'hadir';

    public const STATUS_BATAL = 'batal';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_MENUNGGU => 'Menunggu Verifikasi',
        self::STATUS_TERVERIFIKASI => 'Terverifikasi',
        self::STATUS_DITOLAK => 'Ditolak',
        self::STATUS_HADIR => 'Hadir',
        self::STATUS_BATAL => 'Dibatalkan',
    ];

    /**
     * Status yang dianggap lolos seleksi — dipakai saat promosi ke anggota.
     *
     * @var array<int, string>
     */
    public const STATUS_LOLOS = [self::STATUS_TERVERIFIKASI, self::STATUS_HADIR];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'angkatan' => 'integer',
            'hadir' => 'boolean',
            'diverifikasi_pada' => 'datetime',
            'dipromosikan_pada' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(EventRegistrationAnswer::class);
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeTerverifikasi(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_LOLOS);
    }

    /**
     * Belum dipromosikan menjadi anggota.
     */
    public function scopeBelumDipromosikan(Builder $query): Builder
    {
        return $query->whereNull('member_id');
    }

    /* ------------------------------------------------------------------ */
    /* Bantuan                                                             */
    /* ------------------------------------------------------------------ */

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }

    public function sudahLolos(): bool
    {
        return in_array($this->status, self::STATUS_LOLOS, true);
    }

    public function sudahDipromosikan(): bool
    {
        return $this->member_id !== null;
    }

    /**
     * Isian pendaftar dalam bentuk larik — dipakai saat promosi ke anggota dan
     * saat ekspor, supaya tidak ada pemetaan kolom yang ditulis dua kali.
     *
     * @return array<string, mixed>
     */
    public function dataPendaftar(): array
    {
        return [
            'nama_lengkap' => $this->nama_lengkap,
            'email_kontak' => $this->email,
            'telepon' => $this->telepon,
            'jenis_kelamin' => $this->jenis_kelamin,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir?->format('Y-m-d'),
            'nim' => $this->nim,
            'fakultas' => $this->fakultas,
            'program_studi' => $this->program_studi,
            'angkatan' => $this->angkatan,
            'alamat' => $this->alamat,
        ];
    }
}
