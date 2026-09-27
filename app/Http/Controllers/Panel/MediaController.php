<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pustaka Media: unggah, telusuri, ubah keterangan, dan hapus berkas.
 *
 * Berkas disimpan pada disk "public" (storage/app/public) dan diakses melalui
 * symlink public/storage. Konversi thumbnail bersifat opsional: hanya berjalan
 * bila ekstensi GD/Imagick tersedia di server.
 */
class MediaController extends Controller
{
    /**
     * Batas ukuran unggahan per berkas (KB).
     */
    private const MAKS_KB = 10240; // 10 MB

    /**
     * Jenis berkas yang diizinkan.
     *
     * SVG sengaja TIDAK diizinkan karena dapat memuat skrip dan disajikan
     * langsung dari domain situs (risiko XSS tersimpan).
     *
     * @var array<int, string>
     */
    private const MIME = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public function index(Request $request): Response
    {
        $koleksi = $request->string('koleksi')->toString() ?: 'semua';
        $tipe = $request->string('tipe')->toString() ?: 'semua';
        $cari = trim($request->string('cari')->toString());

        $daftar = Media::query()
            ->when($koleksi !== 'semua', fn ($q) => $q->where('collection_name', $koleksi))
            ->when($tipe === 'gambar', fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($tipe === 'dokumen', fn ($q) => $q->where('mime_type', 'not like', 'image/%'))
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('name', 'like', "%{$cari}%")
                    ->orWhere('file_name', 'like', "%{$cari}%")
                    ->orWhere('custom_properties->alt', 'like', "%{$cari}%"),
            ))
            ->orderByDesc('created_at')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Media $media): array => $this->petakan($media));

        return Inertia::render('Panel/Media/Index', [
            'daftar' => $daftar,
            'saring' => ['koleksi' => $koleksi, 'tipe' => $tipe, 'cari' => $cari],
            'pilihanKoleksi' => MediaLibrary::KOLEKSI,
            'batasMb' => (int) (self::MAKS_KB / 1024),
            'totalUkuran' => $this->ukuranTerbaca((int) Media::query()->sum('size')),
        ]);
    }

    public function unggah(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'berkas' => ['required', 'array', 'min:1', 'max:20'],
            'berkas.*' => ['file', 'max:'.self::MAKS_KB, 'mimetypes:'.implode(',', self::MIME)],
            'koleksi' => ['required', Rule::in(array_keys(MediaLibrary::KOLEKSI))],
            'alt' => ['nullable', 'string', 'max:190'],
        ], [], [
            'berkas' => 'berkas',
            'koleksi' => 'koleksi',
        ]);

        $induk = MediaLibrary::induk();
        $jumlah = 0;

        foreach ($request->file('berkas') as $berkas) {
            $media = $induk
                ->addMedia($berkas)
                ->usingName(pathinfo($berkas->getClientOriginalName(), PATHINFO_FILENAME))
                ->toMediaCollection($data['koleksi'], 'public');

            if (! empty($data['alt'])) {
                $media->setCustomProperty('alt', $data['alt']);
                $media->save();
            }

            $jumlah++;
        }

        return back()->with('sukses', $jumlah.' berkas berhasil diunggah ke koleksi '.MediaLibrary::KOLEKSI[$data['koleksi']].'.');
    }

    public function perbarui(Request $request, Media $media): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'alt' => ['nullable', 'string', 'max:190'],
            'collection_name' => ['required', Rule::in(array_keys(MediaLibrary::KOLEKSI))],
        ]);

        $media->name = $data['name'];
        $media->setCustomProperty('alt', $data['alt'] ?? '');

        // Memindahkan koleksi cukup dengan mengubah nama collection-nya.
        if ($media->collection_name !== $data['collection_name']) {
            $media->collection_name = $data['collection_name'];
        }

        $media->save();

        return back()->with('sukses', 'Keterangan berkas diperbarui.');
    }

    public function hapus(Media $media): RedirectResponse
    {
        $nama = $media->file_name;

        // Menghapus baris media sekaligus menghapus berkas fisiknya.
        $media->delete();

        return back()->with('sukses', 'Berkas '.$nama.' dihapus.');
    }

    /**
     * Ringkasan satu berkas untuk dikirim ke panel.
     *
     * @return array<string, mixed>
     */
    private function petakan(Media $media): array
    {
        $gambar = str_starts_with((string) $media->mime_type, 'image/');

        return [
            'id' => $media->id,
            'nama' => $media->name,
            'nama_berkas' => $media->file_name,
            'koleksi' => $media->collection_name,
            'label_koleksi' => MediaLibrary::KOLEKSI[$media->collection_name] ?? $media->collection_name,
            'mime' => $media->mime_type,
            'gambar' => $gambar,
            'alt' => (string) $media->getCustomProperty('alt', ''),
            'ukuran' => $this->ukuranTerbaca((int) $media->size),
            // Thumbnail hanya ada bila konversi dijalankan (GD/Imagick tersedia).
            'url' => $media->hasGeneratedConversion('kecil')
                ? $media->getUrl('kecil')
                : $media->getUrl(),
            'diunggah_pada' => $media->created_at?->translatedFormat('d M Y H:i'),
            'dipakai' => $this->dipakai($media),
        ];
    }

    /**
     * Cek apakah berkas sudah dirujuk modul lain (mis. slider).
     *
     * Saat ini baru slider; modul lain menyusul pada fase berikutnya.
     */
    private function dipakai(Media $media): array
    {
        $rujukan = [];

        $jumlahSlider = \App\Models\Slider::query()->where('media_id', $media->id)->count();

        if ($jumlahSlider > 0) {
            $rujukan[] = 'Slider beranda ('.$jumlahSlider.')';
        }

        return $rujukan;
    }

    /**
     * Ubah jumlah byte menjadi teks yang mudah dibaca.
     */
    private function ukuranTerbaca(int $byte): string
    {
        if ($byte >= 1048576) {
            return number_format($byte / 1048576, 1, ',', '.').' MB';
        }

        if ($byte >= 1024) {
            return number_format($byte / 1024, 0, ',', '.').' KB';
        }

        return $byte.' B';
    }
}
