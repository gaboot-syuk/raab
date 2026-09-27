<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Kolom isian tambahan pada formulir pendaftaran.
 *
 * Dibuat panitia lewat panel, tanpa perlu mengubah kode — misalnya "Ukuran
 * kaos" atau "Riwayat organisasi".
 */
#[Fillable(['event_id', 'kunci', 'label', 'tipe', 'pilihan', 'wajib', 'urutan', 'aktif'])]
class EventField extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const TIPE_TEKS = 'teks';

    public const TIPE_AREA = 'area';

    public const TIPE_ANGKA = 'angka';

    public const TIPE_TANGGAL = 'tanggal';

    public const TIPE_PILIHAN = 'pilihan';

    public const TIPE_CENTANG = 'centang';

    /**
     * @var array<string, string>
     */
    public const TIPE = [
        self::TIPE_TEKS => 'Teks pendek',
        self::TIPE_AREA => 'Teks panjang',
        self::TIPE_ANGKA => 'Angka',
        self::TIPE_TANGGAL => 'Tanggal',
        self::TIPE_PILIHAN => 'Pilihan',
        self::TIPE_CENTANG => 'Centang (ya/tidak)',
    ];

    /**
     * Nama kolom yang tidak boleh dipakai panitia karena sudah dipakai
     * formulir bawaan — menimpanya akan membuat data pendaftar tertukar.
     *
     * @var array<int, string>
     */
    public const KUNCI_TERLARANG = [
        'nama_lengkap', 'nama_panggilan', 'email', 'telepon', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'nim', 'fakultas', 'program_studi',
        'angkatan', 'instansi', 'alamat', 'catatan_peserta',
    ];

    public array $translatable = ['label'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pilihan' => 'array',
            'wajib' => 'boolean',
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(EventRegistrationAnswer::class);
    }

    /**
     * Daftar pilihan untuk bahasa tertentu.
     *
     * @return array<int, string>
     */
    public function pilihanUntuk(string $bahasa = 'id'): array
    {
        $pilihan = $this->pilihan ?? [];

        // Daftar boleh berupa larik sederhana (dipakai semua bahasa) atau
        // dipisah per bahasa bila panitia memerlukannya.
        if (isset($pilihan[$bahasa]) && is_array($pilihan[$bahasa])) {
            return array_values(array_filter($pilihan[$bahasa], fn ($isi) => is_string($isi) && trim($isi) !== ''));
        }

        return array_values(array_filter($pilihan, fn ($isi) => is_string($isi) && trim($isi) !== ''));
    }

    public function labelTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('label', $bahasa, false)
            ?: $this->getTranslation('label', 'id', false)
            ?: $this->kunci);
    }
}
