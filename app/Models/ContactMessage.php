<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nama',
    'email',
    'telepon',
    'asal',
    'jenis',
    'subjek',
    'pesan',
    'status',
    'catatan_internal',
    'dibalas_oleh',
    'dibalas_pada',
    'ip',
    'user_agent',
])]
class ContactMessage extends Model
{
    use SoftDeletes;

    public const STATUS_BARU = 'baru';
    public const STATUS_DIBACA = 'dibaca';
    public const STATUS_DIBALAS = 'dibalas';
    public const STATUS_ARSIP = 'arsip';

    /**
     * Jenis pesan beserta label yang tampil di panel.
     *
     * @var array<string, string>
     */
    public const JENIS = [
        'umum' => 'Pertanyaan Umum',
        'kerjasama' => 'Ajakan Kerja Sama',
        'undangan' => 'Undangan Kegiatan',
        'lainnya' => 'Lainnya',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_BARU => 'Baru',
        self::STATUS_DIBACA => 'Sudah Dibaca',
        self::STATUS_DIBALAS => 'Sudah Dibalas',
        self::STATUS_ARSIP => 'Diarsipkan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dibalas_pada' => 'datetime',
        ];
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BARU);
    }

    public function scopeTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Pesan dianggap perlu tindakan bila belum dibalas atau diarsipkan.
     */
    public function perluTindakan(): bool
    {
        return in_array($this->status, [self::STATUS_BARU, self::STATUS_DIBACA], true);
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst($this->jenis);
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }
}
