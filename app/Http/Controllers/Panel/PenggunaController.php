<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Pengelolaan akun pengurus: membuat akun, menetapkan peran, menonaktifkan.
 *
 * Peran yang dapat diberikan dibatasi pada peran organisasi (superadmin,
 * sekretaris, bendahara, konten_manager). Status kader/alumni bukan peran
 * Spatie, melainkan kolom pada tabel members (lihat docs/02-role-permission.md).
 */
class PenggunaController extends Controller
{
    /**
     * Peran yang boleh diberikan lewat panel.
     *
     * @var array<int, string>
     */
    private const PERAN_PANEL = User::PERAN_PANEL;

    public function index(Request $request): Response
    {
        $cari = trim($request->string('cari')->toString());

        $daftar = User::query()
            ->with('roles:id,name')
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('name', 'like', "%{$cari}%")
                    ->orWhere('email', 'like', "%{$cari}%"),
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'nama' => $user->name,
                'email' => $user->email,
                'peran' => $user->roles->pluck('name')->all(),
                'terverifikasi' => $user->hasVerifiedEmail(),
                'dibuat_pada' => $user->created_at?->translatedFormat('d M Y'),
                'adalah_saya' => $user->id === $request->user()?->id,
            ]);

        return Inertia::render('Panel/Pengguna/Index', [
            'daftar' => $daftar,
            'cari' => $cari,
            'semuaPeran' => Role::query()
                ->whereIn('name', self::PERAN_PANEL)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:filter', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'peran' => ['required', 'array', 'min:1'],
            'peran.*' => [Rule::in(self::PERAN_PANEL)],
            'verifikasi_sekarang' => ['boolean'],
        ], [], [
            'name' => 'nama',
            'password' => 'kata sandi',
            'peran' => 'peran',
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => ($data['verifikasi_sekarang'] ?? true) ? now() : null,
        ]);

        $user->syncRoles($data['peran']);

        activity()->causedBy($request->user())->performedOn($user)
            ->log('Membuat akun pengurus: '.$user->email);

        return back()->with('sukses', 'Akun '.$user->name.' berhasil dibuat.');
    }

    public function perbarui(Request $request, User $pengguna): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:filter', 'max:190', Rule::unique('users', 'email')->ignore($pengguna->id)],
            'peran' => ['required', 'array', 'min:1'],
            'peran.*' => [Rule::in(self::PERAN_PANEL)],
            'verifikasi_email' => ['boolean'],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->numbers()],
        ], [], [
            'name' => 'nama',
            'password' => 'kata sandi',
            'peran' => 'peran',
        ]);

        // Superadmin tidak boleh menanggalkan peran superadmin miliknya sendiri,
        // supaya panel tidak pernah kehilangan satu-satunya pengelola.
        if ($pengguna->id === $request->user()?->id
            && $pengguna->hasRole('superadmin')
            && ! in_array('superadmin', $data['peran'], true)) {
            return back()->with('galat', 'Kamu tidak dapat menghapus peran superadmin milikmu sendiri.');
        }

        $pengguna->name = $data['name'];
        $pengguna->email = $data['email'];

        if (($data['verifikasi_email'] ?? false) && ! $pengguna->hasVerifiedEmail()) {
            $pengguna->email_verified_at = now();
        }

        if (! empty($data['password'])) {
            $pengguna->password = Hash::make($data['password']);
        }

        $pengguna->save();
        $pengguna->syncRoles($data['peran']);

        activity()->causedBy($request->user())->performedOn($pengguna)
            ->log('Memperbarui akun pengurus: '.$pengguna->email);

        return back()->with('sukses', 'Akun '.$pengguna->name.' diperbarui.');
    }

    public function hapus(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->id === $request->user()?->id) {
            return back()->with('galat', 'Kamu tidak dapat menghapus akunmu sendiri.');
        }

        $nama = $pengguna->name;
        $pengguna->delete();

        return back()->with('sukses', 'Akun '.$nama.' dihapus.');
    }
}
