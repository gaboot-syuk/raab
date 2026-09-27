<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Aspirasi kader & pengunjung.
 *
 * IDENTITAS TERSIMPAN LENGKAP, TAPI TIDAK PERNAH TAYANG.
 * Pengurus perlu menghubungi pengirimnya; publik tidak perlu tahu siapa dia.
 * Pemisahan itu dijaga di dua tempat: kolom identitas tidak pernah ikut di
 * query papan publik, dan isi suratnya dibersihkan dari nomor/email/NIK oleh
 * `App\Services\Aspirasi::redaksi()` sebelum ditampilkan.
 *
 * STATUS
 *  - `baru`     : belum dibaca siapa pun
 *  - `dibaca`   : sudah dilihat pengurus
 *  - `diproses` : sedang ditindaklanjuti
 *  - `selesai`  : sudah ditanggapi dan beres
 *  - `ditolak`  : tidak dapat ditindaklanjuti, wajib beralasan lewat tanggapan
 *
 * IDENTITAS. Nama, email, dan telepon pengirim tersimpan lengkap untuk
 * pengurus, tetapi TIDAK PERNAH tayang di papan publik — papan hanya memuat
 * nomor tiket, kategori, isi yang sudah dibersihkan, dan tanggapan resmi.
 */
#[Fillable([
    'nomor_tiket',
    'token_lacak',
    'member_id',
    'nama_pengirim',
    'email_pengirim',
    'telepon_pengirim',
    'kategori',
    'judul',
    'isi',
    'status',
    'tanggapan',
    'ditanggapi_oleh',
    'ditanggapi_pada',
    'tampil_publik',
])]
class Aspiration extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['judul'];

    public const STATUS_BARU = 'baru';

    public const STATUS_DIBACA = 'dibaca';

    public const STATUS_DIPROSES = 'diproses';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DITOLAK = 'ditolak';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_BARU => 'Baru',
        self::STATUS_DIBACA => 'Sudah Dibaca',
        self::STATUS_DIPROSES => 'Sedang Diproses',
        self::STATUS_SELESAI => 'Selesai',
        self::STATUS_DITOLAK => 'Tidak Dapat Ditindaklanjuti',
    ];

    /**
     * Status yang dianggap masih menunggu tindakan pengurus.
     *
     * @var array<int, string>
     */
    public const BELUM_SELESAI = [
        self::STATUS_BARU,
        self::STATUS_DIBACA,
        self::STATUS_DIPROSES,
    ];

    public const KATEGORI_AKADEMIK = 'akademik';

    public const KATEGORI_FASILITAS = 'fasilitas';

    public const KATEGORI_KEGIATAN = 'kegiatan';

    public const KATEGORI_LAYANAN = 'layanan';

    public const KATEGORI_KEUANGAN = 'keuangan';

    public const KATEGORI_LAINNYA = 'lainnya';

    /**
     * @var array<string, string>
     */
    public const KATEGORI = [
        self::KATEGORI_AKADEMIK => 'Akademik',
        self::KATEGORI_FASILITAS => 'Fasilitas',
        self::KATEGORI_KEGIATAN => 'Kegiatan',
        self::KATEGORI_LAYANAN => 'Layanan',
        self::KATEGORI_KEUANGAN => 'Keuangan',
        self::KATEGORI_LAINNYA => 'Lainnya',
    ];

    /**
     * Panjang minimum isi aspirasi. Terlalu pendek biasanya bukan aspirasi,
     * melainkan keluhan sekali baris yang tidak bisa ditindaklanjuti.
     */
    public const MIN_ISI = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tampil_publik' => 'boolean',
            'ditanggapi_pada' => 'datetime',
        ];
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function penanggap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditanggapi_oleh');
    }

    public function scopePapanPublik(Builder $query): Builder
    {
        return $query->where('tampil_publik', true);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeKategori(Builder $query, string $kategori): Builder
    {
        return $query->where('kategori', $kategori);
    }

    public function judulTeks(): string
    {
        return $this->getTranslation('judul', 'id') ?: 'Aspirasi';
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function labelKategori(): string
    {
        return self::KATEGORI[$this->kategori] ?? $this->kategori;
    }

    public function sudahDitanggapi(): bool
    {
        return $this->tanggapan !== null && trim($this->tanggapan) !== '';
    }

    /**
     * Nomor tiket yang enak dibacakan di rapat: ASP-2026-0007.
     */
    public static function nomorTiketBaru(): string
    {
        $tahun = now()->format('Y');
        $urutan = self::query()->whereYear('created_at', $tahun)->count() + 1;

        do {
            $nomor = 'ASP-'.$tahun.'-'.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
            $urutan++;
        } while (self::query()->where('nomor_tiket', $nomor)->exists());

        return $nomor;
    }

    public static function tokenLacakBaru(): string
    {
        return Str::random(48);
    }
}
