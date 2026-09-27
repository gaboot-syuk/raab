<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Peminjaman buku atau aset.
 *
 * TANPA DENDA. Keterlambatan dicatat dan diingatkan lewat email, tidak
 * dikenakan biaya apa pun — ini keputusan produk, bukan kelalaian.
 */
#[Fillable([
    'kode_pinjam',
    'book_copy_id',
    'inventory_item_id',
    'jenis',
    'jumlah',
    'member_id',
    'peminjam_nama',
    'peminjam_kontak',
    'peminjam_instansi',
    'penanggung_jawab_id',
    'status',
    'perpanjangan_ke',
    'diajukan_pada',
    'jatuh_tempo',
    'disetujui_oleh',
    'disetujui_pada',
    'diserahkan_oleh',
    'diserahkan_pada',
    'dikembalikan_pada',
    'diterima_oleh',
    'kondisi_keluar',
    'kondisi_masuk',
    'catatan_peminjam',
    'catatan_petugas',
    'ingat_h1_pada',
    'ingat_terlambat_pada',
])]
class Loan extends Model
{
    public const JENIS_INTERNAL = 'internal';

    public const JENIS_EKSTERNAL = 'eksternal';

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_DIPINJAM = 'dipinjam';

    public const STATUS_DIKEMBALIKAN = 'dikembalikan';

    public const STATUS_HILANG = 'hilang';

    public const STATUS_BATAL = 'batal';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_DIAJUKAN => 'Menunggu Persetujuan',
        self::STATUS_DISETUJUI => 'Disetujui, Belum Diambil',
        self::STATUS_DIPINJAM => 'Sedang Dipinjam',
        self::STATUS_DIKEMBALIKAN => 'Sudah Dikembalikan',
        self::STATUS_DITOLAK => 'Ditolak',
        self::STATUS_HILANG => 'Hilang',
        self::STATUS_BATAL => 'Dibatalkan',
    ];

    /**
     * Status yang berarti barang SEDANG TIDAK DI RAK.
     *
     * Dipakai untuk menghitung stok tersedia: begitu disetujui, unit sudah
     * dijanjikan kepada peminjam sehingga tidak boleh dijanjikan lagi.
     *
     * @var array<int, string>
     */
    public const STATUS_BERJALAN = [self::STATUS_DISETUJUI, self::STATUS_DIPINJAM];

    public const KONDISI_BAIK = 'baik';

    public const KONDISI_RUSAK_RINGAN = 'rusak_ringan';

    public const KONDISI_RUSAK_BERAT = 'rusak_berat';

    public const KONDISI_HILANG = 'hilang';

    /**
     * Kondisi barang saat DISERAHKAN.
     *
     * Barang tidak mungkin "hilang" saat masih di tangan petugas — karena itu
     * hilang sengaja tidak ada di daftar ini.
     *
     * @var array<string, string>
     */
    public const KONDISI_KELUAR = [
        self::KONDISI_BAIK => 'Baik',
        self::KONDISI_RUSAK_RINGAN => 'Rusak Ringan',
        self::KONDISI_RUSAK_BERAT => 'Rusak Berat',
    ];

    /**
     * Kondisi barang saat DIKEMBALIKAN — di sini "hilang" masuk akal.
     *
     * @var array<string, string>
     */
    public const KONDISI_MASUK = [
        self::KONDISI_BAIK => 'Baik',
        self::KONDISI_RUSAK_RINGAN => 'Rusak Ringan',
        self::KONDISI_RUSAK_BERAT => 'Rusak Berat',
        self::KONDISI_HILANG => 'Hilang',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'perpanjangan_ke' => 'integer',
            'diajukan_pada' => 'datetime',
            'jatuh_tempo' => 'datetime',
            'disetujui_pada' => 'datetime',
            'diserahkan_pada' => 'datetime',
            'dikembalikan_pada' => 'datetime',
            'ingat_h1_pada' => 'date',
            'ingat_terlambat_pada' => 'date',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function eksemplar(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'penanggung_jawab_id');
    }

    public function perpanjangan(): HasMany
    {
        return $this->hasMany(LoanExtension::class)->latest('id');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeBerjalan(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_BERJALAN);
    }

    public function scopeUntukAnggota(Builder $query, int $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Pinjaman yang sudah lewat jatuh tempo dan belum kembali.
     */
    public function scopeTerlambat(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DIPINJAM)
            ->whereNotNull('jatuh_tempo')
            ->where('jatuh_tempo', '<', now()->startOfDay());
    }

    /**
     * Jatuh tempo besok — dasar pengingat H-1.
     */
    public function scopeJatuhTempoBesok(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DIPINJAM)
            ->whereNotNull('jatuh_tempo')
            ->whereBetween('jatuh_tempo', [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()]);
    }

    /* ------------------------------------------------------------------ */
    /* Bantuan                                                             */
    /* ------------------------------------------------------------------ */

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function labelJenis(): string
    {
        return $this->jenis === self::JENIS_EKSTERNAL ? 'Eksternal' : 'Internal';
    }

    public function apaYangDipinjam(): string
    {
        if ($this->book_copy_id) {
            $judul = $this->eksemplar?->buku?->judulTeks();

            return $judul
                ? $judul.' ('.$this->eksemplar?->kode_eksemplar.')'
                : 'Buku #'.$this->book_copy_id;
        }

        if ($this->inventory_item_id) {
            return $this->aset?->namaTeks().' × '.$this->jumlah;
        }

        return '—';
    }

    /**
     * Nama peminjam — anggota atau pihak luar yang dicatat Sekretaris.
     */
    public function namaPeminjam(): string
    {
        return $this->anggota?->nama_lengkap ?: ($this->peminjam_nama ?: 'Tanpa Nama');
    }

    public function sedangDipinjam(): bool
    {
        return in_array($this->status, self::STATUS_BERJALAN, true);
    }

    public function terlambat(): bool
    {
        return $this->status === self::STATUS_DIPINJAM
            && $this->jatuh_tempo !== null
            && $this->jatuh_tempo->isPast();
    }

    public function hariTerlambat(): int
    {
        if (! $this->terlambat()) {
            return 0;
        }

        return (int) $this->jatuh_tempo->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Sisa hari sampai jatuh tempo. Negatif berarti sudah lewat.
     */
    public function sisaHari(): ?int
    {
        if ($this->jatuh_tempo === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->jatuh_tempo->startOfDay(), false);
    }

    /**
     * Masih boleh diperpanjang?
     *
     * Batasnya diambil dari pengaturan situs supaya pengurus dapat
     * mengubahnya tanpa menyentuh kode.
     */
    public function bolehDiperpanjang(): bool
    {
        $maks = (int) \App\Support\Pengaturan::angka('pinjaman_perpanjangan_maks', 1);

        return $this->status === self::STATUS_DIPINJAM
            && $this->perpanjangan_ke < $maks
            && ! $this->terlambat();
    }

    /**
     * Kode pinjam yang enak dibacakan: PJM-2026-0007.
     */
    public static function kodeBaru(): string
    {
        $tahun = now()->format('Y');
        $urutan = self::query()->whereYear('created_at', $tahun)->count() + 1;

        do {
            $kode = 'PJM-'.$tahun.'-'.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
            $urutan++;
        } while (self::query()->where('kode_pinjam', $kode)->exists());

        return $kode;
    }

    public static function durasiHariDefault(): int
    {
        return max(1, (int) \App\Support\Pengaturan::angka('pinjaman_masa_hari', 7));
    }
}
