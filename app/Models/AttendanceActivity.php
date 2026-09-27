<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Kegiatan yang presensinya dicatat.
 *
 * STATUS
 *  - `draf`    : masih disusun panitia, kader belum melihatnya
 *  - `terbuka` : presensi sedang berjalan — hanya pada keadaan inilah
 *                kehadiran boleh dicatat
 *  - `selesai` : presensi ditutup, rekapnya dianggap final
 *  - `batal`   : kegiatan tidak jadi berlangsung
 *
 * KEGIATAN YANG DIBATALKAN TIDAK PERNAH DIHAPUS. Menghapusnya akan menghapus
 * pula kehadiran yang mungkin sudah tercatat, dan riwayat yang hilang tidak
 * bisa dipertanggungjawabkan di akhir kepengurusan.
 */
#[Fillable([
    'kode',
    'judul',
    'deskripsi',
    'jenis',
    'unit_id',
    'mulai',
    'selesai',
    'lokasi',
    'mode_presensi',
    'status',
    'qr_token',
    'qr_berlaku_sampai',
    'poin',
    'wajib',
    'dibuat_oleh',
])]
class AttendanceActivity extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    /**
     * Kolom yang disimpan sebagai JSON dwibahasa.
     *
     * @var array<int, string>
     */
    public array $translatable = ['judul', 'deskripsi'];

    public const STATUS_DRAF = 'draf';

    public const STATUS_TERBUKA = 'terbuka';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_BATAL = 'batal';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_DRAF => 'Draf',
        self::STATUS_TERBUKA => 'Presensi Terbuka',
        self::STATUS_SELESAI => 'Selesai',
        self::STATUS_BATAL => 'Dibatalkan',
    ];

    public const JENIS_RAPAT = 'rapat';

    public const JENIS_KAJIAN = 'kajian';

    public const JENIS_PELATIHAN = 'pelatihan';

    public const JENIS_SOSIAL = 'sosial';

    public const JENIS_LAINNYA = 'lainnya';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_RAPAT => 'Rapat',
        self::JENIS_KAJIAN => 'Kajian',
        self::JENIS_PELATIHAN => 'Pelatihan',
        self::JENIS_SOSIAL => 'Kegiatan Sosial',
        self::JENIS_LAINNYA => 'Lainnya',
    ];

    public const MODE_MANUAL = 'manual';

    public const MODE_QR = 'qr';

    public const MODE_KEDUANYA = 'keduanya';

    /**
     * @var array<string, string>
     */
    public const MODE = [
        self::MODE_MANUAL => 'Manual (oleh pengurus)',
        self::MODE_QR => 'QR (dipindai kader)',
        self::MODE_KEDUANYA => 'Manual & QR',
    ];

    /**
     * Berapa lama token QR berlaku sejak diputar (menit).
     */
    public const QR_BERLAKU_MENIT = 180;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'qr_berlaku_sampai' => 'datetime',
            'poin' => 'integer',
            'wajib' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'unit_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function rsvp(): HasMany
    {
        return $this->hasMany(AttendanceRsvp::class, 'activity_id');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'activity_id');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERBUKA);
    }

    public function scopeBukanBatal(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_BATAL);
    }

    public function scopeJenis(Builder $query, string $jenis): Builder
    {
        return $query->where('jenis', $jenis);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderByDesc('mulai');
    }

    /* ------------------------------------------------------------------ */
    /* Turunan                                                             */
    /* ------------------------------------------------------------------ */

    public function judulTeks(): string
    {
        return $this->getTranslation('judul', 'id') ?: 'Kegiatan';
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }

    public function labelMode(): string
    {
        return self::MODE[$this->mode_presensi] ?? $this->mode_presensi;
    }

    /**
     * Presensi sedang berjalan?
     */
    public function sedangTerbuka(): bool
    {
        return $this->status === self::STATUS_TERBUKA;
    }

    /**
     * Boleh dicatat lewat QR? Butuh mode QR DAN token yang belum kedaluwarsa.
     */
    public function qrAktif(): bool
    {
        if (! $this->sedangTerbuka()) {
            return false;
        }

        if (! in_array($this->mode_presensi, [self::MODE_QR, self::MODE_KEDUANYA], true)) {
            return false;
        }

        if ($this->qr_token === null) {
            return false;
        }

        return $this->qr_berlaku_sampai === null || $this->qr_berlaku_sampai->isFuture();
    }

    /**
     * Kode kegiatan yang enak dibacakan: KEG-2026-0001.
     */
    public static function kodeBaru(): string
    {
        $tahun = now()->format('Y');
        $urutan = self::query()->whereYear('created_at', $tahun)->count() + 1;

        do {
            $kode = 'KEG-'.$tahun.'-'.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
            $urutan++;
        } while (self::query()->where('kode', $kode)->exists());

        return $kode;
    }

    /**
     * Token QR baru. Panjang 48 karakter supaya tidak dapat ditebak dari kode
     * kegiatan.
     */
    public static function tokenQrBaru(): string
    {
        return Str::random(48);
    }
}
