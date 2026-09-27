<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Hibah & dukungan alumni — dana, barang, atau jasa.
 *
 * SATU TABEL, TIGA CARA PENERIMAAN:
 *  - `dana`   → melahirkan transaksi kas masuk (sumber `hibah`)
 *  - `barang` → melahirkan mutasi inventaris `masuk` (stok aset bertambah)
 *  - `jasa`   → dicatat sebagai kesediaan, boleh ditautkan ke kegiatan
 *
 * Kolom tautannya (`transaction_id`, `inventory_movement_id`, `event_id`)
 * disimpan di sini supaya hubungan sebab-akibatnya bisa ditelusuri dari kedua
 * arah: dari hibah ke kas/inventaris, dan sebaliknya.
 *
 * OPSI ANONIM. Bila `anonim` benar, nama pemberi disembunyikan dari laporan
 * Bendahara; hanya Superadmin yang boleh membukanya. Ini disengaja: menghormati
 * permintaan penyumbang lebih penting daripada kelengkapan laporan.
 */
#[Fillable([
    'nomor_hibah',
    'member_id',
    'alumni_profile_id',
    'nama_pemberi',
    'kontak',
    'jenis',
    'judul',
    'deskripsi',
    'estimasi_nilai',
    'nilai_diterima',
    'tanggal_rencana',
    'diterima_pada',
    'status',
    'anonim',
    'alasan_tolak',
    'catatan_bendahara',
    'dicatat_oleh',
    'diverifikasi_oleh',
    'diverifikasi_pada',
    'transaction_id',
    'inventory_movement_id',
    'event_id',
    'kondisi_barang',
    'bukti_media_id',
])]
class Donation extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const JENIS_DANA = 'dana';

    public const JENIS_BARANG = 'barang';

    public const JENIS_JASA = 'jasa';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_DANA => 'Dana',
        self::JENIS_BARANG => 'Barang',
        self::JENIS_JASA => 'Jasa',
    ];

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DIJANJIKAN = 'dijanjikan';

    public const STATUS_DITERIMA = 'diterima';

    public const STATUS_DIVERIFIKASI = 'diverifikasi';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_DIAJUKAN => 'Diajukan',
        self::STATUS_DIJANJIKAN => 'Dijanjikan',
        self::STATUS_DITERIMA => 'Diterima',
        self::STATUS_DIVERIFIKASI => 'Diverifikasi',
        self::STATUS_DITOLAK => 'Ditolak',
        self::STATUS_DIBATALKAN => 'Dibatalkan',
    ];

    public array $translatable = ['judul', 'deskripsi'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimasi_nilai' => 'integer',
            'nilai_diterima' => 'integer',
            'tanggal_rencana' => 'date',
            'diterima_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
            'anonim' => 'boolean',
        ];
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function alumni(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class, 'alumni_profile_id');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class, 'transaction_id');
    }

    public function mutasi(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function scopeJenis(Builder $query, string $jenis): Builder
    {
        return $query->where('jenis', $jenis);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function judulTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('judul', $bahasa, false)
            ?: $this->getTranslation('judul', 'id', false)
            ?: '—');
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /**
     * Nama pemberi untuk ditampilkan. Bila anonim dan penonton bukan Superadmin,
     * identitasnya diganti keterangan netral.
     */
    public function namaPemberi(?User $penonton = null): string
    {
        if (! $this->anonim || $penonton?->hasRole('superadmin')) {
            return $this->nama_pemberi;
        }

        return 'Hamba Allah (anonim)';
    }

    public function nilaiTercatat(): int
    {
        return (int) ($this->nilai_diterima ?? $this->estimasi_nilai);
    }

    /**
     * Nomor hibah berurutan per tahun, mis. HIB-2026-0001.
     */
    public static function nomorHibahBaru(): string
    {
        $tahun = now()->year;
        $awalan = 'HIB-'.$tahun.'-';

        $terakhir = self::query()
            ->where('nomor_hibah', 'like', $awalan.'%')
            ->orderByDesc('nomor_hibah')
            ->value('nomor_hibah');

        $urutan = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return $awalan.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }
}
