<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'phone',
    'whatsapp',
    'password',
    'locale',
    'preferensi_tema',
    'preferensi_notifikasi',
    'status',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    public const STATUS_DITANGGUHKAN = 'ditangguhkan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'preferensi_notifikasi' => 'array',
        ];
    }

    public function aktif(): bool
    {
        return $this->status === self::STATUS_AKTIF;
    }

    /**
     * Identitas keanggotaan pengguna (kader aktif atau alumni).
     *
     * Berbeda dari peran kepengurusan: seorang pengurus bisa saja belum
     * diverifikasi sebagai anggota, dan sebaliknya.
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * Apakah pengguna sudah diverifikasi sebagai anggota (kader aktif/alumni)?
     */
    public function anggotaTerverifikasi(): bool
    {
        return (bool) $this->member?->telahDiverifikasi();
    }

    /**
     * Peran yang boleh masuk area pengurus.
     *
     * Satu daftar untuk seluruh aplikasi. Kalau daftar ini ditulis ulang di
     * beberapa tempat, cepat atau lambat ada yang tertinggal saat peran baru
     * ditambahkan — dan yang tertinggal biasanya justru penjagaannya.
     *
     * @var array<int, string>
     */
    public const PERAN_PANEL = ['superadmin', 'sekretaris', 'bendahara', 'konten_manager'];

    /**
     * Apakah pengguna ini pengurus?
     *
     * Kader dan alumni TIDAK termasuk. Panel pengurus bukan "halaman setelah
     * masuk" — ia halaman kerja pengurus. Mengarahkan kader ke sana berarti
     * memberinya halaman yang kosong dan bukan miliknya, lalu membiarkannya
     * menyimpulkan bahwa aplikasinya rusak.
     */
    public function pengurus(): bool
    {
        return $this->hasAnyRole(self::PERAN_PANEL);
    }

    /**
     * Ke mana pengguna ini seharusnya diarahkan setelah masuk.
     *
     * Menjadi satu-satunya tempat keputusan ini diambil, supaya halaman masuk,
     * pendaftaran, dan verifikasi email tidak masing-masing menjawabnya
     * sendiri-sendiri dan berbeda hasil.
     */
    public function berandaSetelahMasuk(): string
    {
        return $this->pengurus() ? '/panel' : '/dasbor';
    }
}

