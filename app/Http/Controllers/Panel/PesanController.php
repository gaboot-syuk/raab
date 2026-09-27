<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kotak masuk pesan dari formulir "Kontak Rayon".
 *
 * Seluruh isi pesan dikirim sekaligus ke panel (hanya 15 baris per halaman),
 * sehingga membuka detail tidak perlu permintaan tambahan. Status "dibaca"
 * ditandai dari sisi panel saat pesan dibuka.
 */
class PesanController extends Controller
{
    public function index(Request $request): Response
    {
        $saring = $request->string('status')->toString() ?: 'semua';
        $cari = trim($request->string('cari')->toString());

        $daftar = ContactMessage::query()
            ->when(
                $saring !== 'semua',
                fn ($q) => $q->where('status', $saring),
            )
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('nama', 'like', "%{$cari}%")
                    ->orWhere('email', 'like', "%{$cari}%")
                    ->orWhere('subjek', 'like', "%{$cari}%")
                    ->orWhere('pesan', 'like', "%{$cari}%"),
            ))
            ->terbaru()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ContactMessage $pesan): array => [
                'id' => $pesan->id,
                'nama' => $pesan->nama,
                'email' => $pesan->email,
                'telepon' => $pesan->telepon,
                'asal' => $pesan->asal,
                'jenis' => $pesan->jenis,
                'label_jenis' => $pesan->labelJenis(),
                'subjek' => $pesan->subjek,
                'pesan' => $pesan->pesan,
                'status' => $pesan->status,
                'label_status' => $pesan->labelStatus(),
                'catatan_internal' => $pesan->catatan_internal,
                'dibuat_pada' => $pesan->created_at?->translatedFormat('d M Y H:i'),
                'dibalas_pada' => $pesan->dibalas_pada?->translatedFormat('d M Y H:i'),
            ]);

        return Inertia::render('Panel/Pesan/Index', [
            'daftar' => $daftar,
            'saring' => $saring,
            'cari' => $cari,
            'jumlah' => [
                'semua' => ContactMessage::query()->count(),
                'baru' => ContactMessage::query()->where('status', ContactMessage::STATUS_BARU)->count(),
                'dibalas' => ContactMessage::query()->where('status', ContactMessage::STATUS_DIBALAS)->count(),
                'arsip' => ContactMessage::query()->where('status', ContactMessage::STATUS_ARSIP)->count(),
            ],
            'pilihanStatus' => ContactMessage::STATUS,
        ]);
    }

    public function perbarui(Request $request, ContactMessage $pesan): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:baru,dibaca,dibalas,arsip'],
            'catatan_internal' => ['nullable', 'string', 'max:2000'],
        ]);

        $pesan->status = $data['status'];
        $pesan->catatan_internal = $data['catatan_internal'] ?? $pesan->catatan_internal;

        if ($data['status'] === ContactMessage::STATUS_DIBALAS) {
            $pesan->dibalas_oleh = $request->user()?->id;
            $pesan->dibalas_pada ??= now();
        }

        $pesan->save();

        return back()->with('sukses', 'Pesan dari '.$pesan->nama.' diperbarui.');
    }

    public function hapus(ContactMessage $pesan): RedirectResponse
    {
        $nama = $pesan->nama;
        $pesan->delete();

        return back()->with('sukses', 'Pesan dari '.$nama.' dihapus.');
    }
}
