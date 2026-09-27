<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris buku kas.
 *
 * TIGA JANJI YANG DIJAGA KELAS INI:
 *  1. TIDAK PERNAH DIHAPUS. Salah input diselesaikan dengan `void` + alasan.
 *  2. Saldo akun hanya bergerak saat status berubah jadi `terkonfirmasi`.
 *  3. Setiap pergerakan menyimpan `saldo_sebelum` & `saldo_sesudah`, sehingga
 *     angka lama tetap bisa diaudit walau akunnya sudah berubah berkali-kali.
 *
 * Status `draft` berarti transaksi sudah tercatat tetapi BELUM menyentuh saldo —
 * berguna saat Bendahara memasukkan nota sebelum uangnya benar-benar bergerak.
 */
#[Fillable([
    'account_id',
    'category_id',
    'nomor_voucher',
    'tanggal',
    'jenis',
    'jumlah',
    'keterangan',
    'sumber',
    'event_id',
    'budget_id',
    'due_payment_id',
    'donation_id',
    'bukti_media_id',
    'status',
    'dicatat_oleh',
    'dikonfirmasi_oleh',
    'dikonfirmasi_pada',
    'void_oleh',
    'void_pada',
    'void_alasan',
    'saldo_sebelum',
    'saldo_sesudah',
])]
class FinanceTransaction extends Model
{
    public const JENIS_MASUK = 'masuk';

    public const JENIS_KELUAR = 'keluar';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_MASUK => 'Kas Masuk',
        self::JENIS_KELUAR => 'Kas Keluar',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_TERKONFIRMASI = 'terkonfirmasi';

    public const STATUS_VOID = 'void';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_TERKONFIRMASI => 'Terkonfirmasi',
        self::STATUS_VOID => 'Void',
    ];

    public const SUMBER_IURAN = 'iuran';

    public const SUMBER_HIBAH = 'hibah';

    public const SUMBER_DONASI = 'donasi';

    public const SUMBER_DANA_KEGIATAN = 'dana_kegiatan';

    public const SUMBER_USAHA = 'usaha';

    public const SUMBER_LAIN = 'lain';

    /**
     * @var array<string, string>
     */
    public const SUMBER = [
        self::SUMBER_IURAN => 'Iuran Anggota',
        self::SUMBER_HIBAH => 'Hibah Alumni',
        self::SUMBER_DONASI => 'Donasi',
        self::SUMBER_DANA_KEGIATAN => 'Dana Kegiatan',
        self::SUMBER_USAHA => 'Usaha Rayon',
        self::SUMBER_LAIN => 'Lain-lain',
    ];

    /**
     * Nominal yang wajib disertai bukti/nota.
     */
    public const BATAS_WAJIB_BUKTI = 100000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah' => 'integer',
            'dikonfirmasi_pada' => 'datetime',
            'void_pada' => 'datetime',
            'saldo_sebelum' => 'integer',
            'saldo_sesudah' => 'integer',
        ];
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function anggaran(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function pengkonfirmasi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikonfirmasi_oleh');
    }

    public function pemvoid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_oleh');
    }

    public function scopeTerkonfirmasi(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERKONFIRMASI);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Arah pengaruh ke saldo: masuk menambah, keluar mengurangi.
     */
    public function arah(): int
    {
        return $this->jenis === self::JENIS_MASUK ? 1 : -1;
    }

    public function terkonfirmasi(): bool
    {
        return $this->status === self::STATUS_TERKONFIRMASI;
    }

    public function diVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }

    public function labelSumber(): string
    {
        return self::SUMBER[$this->sumber] ?? $this->sumber;
    }

    public function wajibBukti(): bool
    {
        return $this->jumlah > self::BATAS_WAJIB_BUKTI;
    }

    /**
     * Nomor voucher berurutan per tahun, mis. VCH-2026-0001.
     */
    public static function nomorVoucherBaru(): string
    {
        $tahun = now()->year;
        $awalan = 'VCH-'.$tahun.'-';

        $terakhir = self::query()
            ->where('nomor_voucher', 'like', $awalan.'%')
            ->orderByDesc('nomor_voucher')
            ->value('nomor_voucher');

        $urutan = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return $awalan.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }
}
