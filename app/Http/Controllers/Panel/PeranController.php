<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\KatalogIzin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran & Izin: menyesuaikan hak akses setiap peran.
 *
 * Peran "superadmin" dikunci (selalu memegang seluruh izin) supaya panel tidak
 * pernah kehilangan kemampuan pemulihan. Status kader/alumni bukan peran di
 * sini, melainkan kolom status pada tabel members.
 */
class PeranController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const LABEL_PERAN = [
        'superadmin' => 'Superadmin',
        'sekretaris' => 'Sekretaris',
        'bendahara' => 'Bendahara',
        'konten_manager' => 'Konten Manager',
    ];

    public function index(): Response
    {
        $peran = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'nama' => $role->name,
                'label' => self::LABEL_PERAN[$role->name] ?? Str::headline($role->name),
                'jumlah_pengguna' => $role->users_count,
                'terkunci' => $role->name === 'superadmin',
                'izin' => $role->permissions->pluck('name')->values(),
            ]);

        // Katalog izin dikelompokkan berdasarkan awalan sebelum titik.
        $katalog = Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $izin): string => Str::before($izin->name, '.'))
            ->map(fn ($grup, string $prefiks): array => [
                'prefiks' => $prefiks,
                'label' => KatalogIzin::labelKelompok($prefiks),
                'izin' => $grup->map(fn (Permission $izin): array => [
                    'nama' => $izin->name,
                    'label' => KatalogIzin::labelIzin($izin->name),
                ])->values()->all(),
            ])
            ->sortBy('label')
            ->values();

        return Inertia::render('Panel/Peran/Index', [
            'peran' => $peran,
            'katalog' => $katalog,
            'jumlahIzin' => Permission::query()->count(),
        ]);
    }

    public function perbarui(Request $request, Role $peran): RedirectResponse
    {
        if ($peran->name === 'superadmin') {
            return back()->with('galat', 'Peran Superadmin selalu memegang seluruh izin dan tidak dapat diubah.');
        }

        $data = $request->validate([
            'izin' => ['array'],
            'izin.*' => ['string', 'exists:permissions,name'],
        ]);

        $peran->syncPermissions($data['izin'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity()->causedBy($request->user())
            ->log('Mengubah izin peran: '.$peran->name.' ('.count($data['izin'] ?? []).' izin)');

        return back()->with('sukses', 'Izin peran '.$peran->name.' disimpan ('.count($data['izin'] ?? []).' izin).');
    }
}
