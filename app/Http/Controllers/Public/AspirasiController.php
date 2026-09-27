<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Aspiration;
use App\Services\Aspirasi;
use App\Services\Captcha;
use App\Support\Pengaturan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Aspirasi — halaman publik: papan, formulir, dan pelacakan.
 *
 * HALAMAN INI MEMUAT DUA HAL BERBEDA dan sengaja dipisah tegas:
 *  - PAPAN, yang memuat aspirasi yang sudah ditanggapi — sudah dibersihkan dari
 *    identitas maupun data pribadi di badan suratnya;
 *  - FORMULIR, yang justru MEWAJIBKAN identitas supaya pengurus bisa
 *    menindaklanjuti.
 *
 * Keduanya tidak pernah bertukar data. Formulir tidak pernah menerima isi
 * papan, dan papan tidak pernah menerima kolom identitas.
 */
class AspirasiController extends Controller
{
    public function __construct(private Aspirasi $aspirasi) {}

    public function index(Request $request): View
    {
        $kategori = $request->string('kategori')->toString();

        return view('public.aspirasi', $this->dataHalaman(
            kategori: $kategori,
            hasilLacak: $request->session()->get('hasil_lacak'),
            pesanLacak: $request->session()->get('galat_lacak'),
        ));
    }

    /**
     * Terima aspirasi baru.
     *
     * IDENTITAS WAJIB, dan ALASANNYA BUKAN UNTUK DIPAJANG: pengurus butuh cara
     * menghubungi pengirimnya. Papan publik tetap tidak pernah memuat nama,
     * email, maupun telepon siapa pun.
     */
    public function kirim(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_pengirim' => ['required', 'string', 'max:190'],
            'email_pengirim' => ['required', 'email', 'max:190'],
            'telepon_pengirim' => ['nullable', 'string', 'max:40'],
            'kategori' => ['required', Rule::in(array_keys(Aspiration::KATEGORI))],
            'judul' => ['required', 'string', 'max:180'],
            'isi' => ['required', 'string', 'max:5000'],
            'tampil_publik' => ['boolean'],
            // Honeypot: kolom ini tidak pernah diisi manusia.
            'situs_web' => ['nullable', 'size:0'],
            // Captcha: tidak apa-apa bila penyedianya belum dinyalakan.
            Captcha::KOLOM => Captcha::aturan(),
        ], [
            'situs_web.size' => 'Kiriman ditolak.',
        ], [
            'nama_pengirim' => 'nama',
            'email_pengirim' => 'email',
            'telepon_pengirim' => 'nomor telepon',
        ]);

        $anggota = $request->user()?->member;

        try {
            $aspirasi = $this->aspirasi->kirim($data, $anggota);
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first())->withInput();
        }

        /*
         * Token pelacakan ditampilkan SEKALI lewat session, bukan ditulis di
         * URL: tautan berisi token akan tersimpan di riwayat peramban dan log
         * server, dan siapa pun yang memegangnya bisa membaca isi surat itu.
         *
         * Redirect disusun LANGSUNG di sini, bukan lewat trait `jalankan()`:
         * trait itu membuang nilai kembalian aksi dan menyusun redirect-nya
         * sendiri, sehingga pesan tambahan seperti ini akan hilang.
         */
        return back()
            ->with('sukses', 'Aspirasi '.$aspirasi->nomor_tiket.' sudah kami terima. Simpan nomor tiket dan token di bawah untuk melacak tindak lanjutnya.')
            ->with('nomor_tiket_baru', $aspirasi->nomor_tiket)
            ->with('token_baru', $aspirasi->token_lacak);
    }

    /**
     * Lacak tindak lanjut — butuh nomor tiket DAN token.
     *
     * Hasilnya dikirim balik lewat session lalu dialihkan (pola
     * redirect-setelah-POST), bukan dirender langsung: kalau dirender langsung
     * dari POST, menekan tombol segarkan di peramban akan memunculkan
     * peringatan pengiriman ulang formulir.
     */
    public function lacak(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nomor_tiket' => ['required', 'string', 'max:32'],
            'token_lacak' => ['required', 'string', 'max:64'],
        ], [], [
            'nomor_tiket' => 'nomor tiket',
            'token_lacak' => 'token pelacakan',
        ]);

        $hasil = $this->aspirasi->lacak($data['nomor_tiket'], $data['token_lacak']);

        if ($hasil === null) {
            // Nomor tiket yang tidak ditemukan TIDAK diberi tahu apakah nomornya
            // ada — kalau diberi tahu, nomor tiket bisa dipanen satu per satu.
            return back()
                ->withInput($request->only('nomor_tiket'))
                ->with('galat_lacak', 'Tidak ada aspirasi yang cocok dengan nomor tiket dan token itu. Periksa kembali keduanya.');
        }

        return back()->with('hasil_lacak', [
            'nomor_tiket' => $hasil->nomor_tiket,
            'kategori' => $hasil->labelKategori(),
            'judul' => $hasil->judulTeks(),
            'status' => $hasil->labelStatus(),
            'dibuat' => $hasil->created_at?->translatedFormat('d F Y'),
            'ditanggapi_pada' => $hasil->ditanggapi_pada?->translatedFormat('d F Y'),
            // Isi milik pengirim sendiri, jadi ditampilkan APA ADANYA —
            // penyamaran hanya berlaku untuk yang tayang di papan publik.
            'isi' => $hasil->isi,
            'tanggapan' => $hasil->tanggapan,
        ]);
    }

    /**
     * Data yang selalu ada di halaman publik aspirasi.
     *
     * @return array<string, mixed>
     */
    private function dataHalaman(string $kategori, ?array $hasilLacak, ?string $pesanLacak): array
    {
        return [
            'situs' => Pengaturan::semua(),
            'papan' => $this->aspirasi->papan($kategori !== '' ? $kategori : null),
            'ringkasan' => $this->aspirasi->rekapPublik(),
            'pilihanKategori' => Aspiration::KATEGORI,
            'saringan' => $kategori,
            'hasilLacak' => $hasilLacak,
            'pesanLacak' => $pesanLacak,
            'punyaTokenBaru' => session('token_baru'),
            'nomorTiketBaru' => session('nomor_tiket_baru'),
            'minIsi' => Aspiration::MIN_ISI,
        ];
    }
}
