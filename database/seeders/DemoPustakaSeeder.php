<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Member;
use App\Models\User;
use App\Services\Inventaris;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh untuk Fase 6 (inventaris & perpustakaan).
 *
 * Idempoten: aman dijalankan berulang kali. Stok aset TIDAK ditulis langsung —
 * selalu lewat App\Services\Inventaris supaya riwayat mutasinya ikut terbentuk,
 * persis seperti yang terjadi di aplikasi sungguhan.
 */
class DemoPustakaSeeder extends Seeder
{
    public function run(): void
    {
        $pencatat = User::query()->where('email', 'ketua@raab.test')->first()
            ?? User::query()->orderBy('id')->first();

        $bidang = $this->kategori('perlengkapan-bidang', ['id' => 'Perlengkapan Bidang', 'en' => 'Office Equipment'], 1);
        $sarana = $this->kategori('sarana-prasarana', ['id' => 'Sarana Prasarana', 'en' => 'Facilities'], 2);

        $inventaris = app(Inventaris::class);

        $aset = [
            [
                'kode' => 'INV-001',
                'nama' => ['id' => 'Tenda Kegiatan', 'en' => 'Activity Tent'],
                'keterangan' => ['id' => 'Tenda 4x6 untuk kegiatan lapangan', 'en' => '4x6 tent for field activities'],
                'category_id' => $sarana->id,
                'satuan' => 'unit',
                'jumlah' => 4,
                'jumlah_minimum' => 2,
                'kondisi' => InventoryItem::KONDISI_BAIK,
                'lokasi' => 'Gudang Sekretariat',
                'nilai' => 850000,
                'is_public' => true,
            ],
            [
                'kode' => 'INV-002',
                'nama' => ['id' => 'Sound System Portable', 'en' => 'Portable Sound System'],
                'keterangan' => ['id' => 'Speaker aktif + 2 mic nirkabel', 'en' => 'Active speaker + 2 wireless mics'],
                'category_id' => $sarana->id,
                'satuan' => 'set',
                'jumlah' => 2,
                'jumlah_minimum' => 1,
                'kondisi' => InventoryItem::KONDISI_BAIK,
                'lokasi' => 'Ruang Sekretariat',
                'nilai' => 2400000,
                'is_public' => true,
            ],
            [
                'kode' => 'INV-003',
                'nama' => ['id' => 'Laptop Kesekretariatan', 'en' => 'Secretariat Laptop'],
                'keterangan' => ['id' => 'Untuk pengelolaan administrasi rayon', 'en' => 'For branch administration'],
                'category_id' => $bidang->id,
                'satuan' => 'unit',
                'jumlah' => 1,
                'jumlah_minimum' => 1,
                'kondisi' => InventoryItem::KONDISI_BAIK,
                'lokasi' => 'Ruang Sekretariat',
                'nilai' => 7200000,
                'is_public' => false,
            ],
            [
                'kode' => 'INV-004',
                'nama' => ['id' => 'Meja Lipat', 'en' => 'Folding Table'],
                'keterangan' => ['id' => 'Meja lipat untuk registrasi peserta', 'en' => 'Folding table for participant registration'],
                'category_id' => $bidang->id,
                'satuan' => 'buah',
                'jumlah' => 6,
                'jumlah_minimum' => 4,
                'kondisi' => InventoryItem::KONDISI_RUSAK_RINGAN,
                'lokasi' => 'Gudang Sekretariat',
                'nilai' => 175000,
                'is_public' => true,
            ],
        ];

        foreach ($aset as $data) {
            // Kolom JSON `nama` NOT NULL, jadi tidak boleh lewat firstOrCreate —
            // barisnya dibuat dulu, terjemahannya dipasang, baru disimpan.
            $item = InventoryItem::query()->where('kode', $data['kode'])->first();

            if (! $item) {
                $item = new InventoryItem;
                $item->kode = $data['kode'];
                $item->category_id = $data['category_id'];
                $item->satuan = $data['satuan'];
                $item->jumlah = 0;
                $item->jumlah_minimum = $data['jumlah_minimum'];
                $item->kondisi = $data['kondisi'];
                $item->lokasi = $data['lokasi'];
                $item->nilai = $data['nilai'];
                $item->is_public = $data['is_public'];
                $item->aktif = true;
            }

            $item->setTranslations('nama', $data['nama']);

            if (isset($data['keterangan'])) {
                $item->setTranslations('keterangan', $data['keterangan']);
            }

            $item->save();

            // Stok awal hanya dicatat sekali; kalau aset sudah punya mutasi,
            // berarti seeder ini pernah jalan.
            if ($item->mutasi()->exists()) {
                continue;
            }

            $inventaris->catat($item, 'masuk', $data['jumlah'], [
                'catatan' => 'Pencatatan awal inventaris rayon.',
                'kondisi_baru' => $data['kondisi'],
            ], $pencatat);
        }

        // Satu contoh barang rusak berikut penanggung jawabnya, supaya jejak
        // "siapa yang bertanggung jawab" terlihat di panel.
        $meja = InventoryItem::query()->where('kode', 'INV-004')->first();

        if ($meja && ! $meja->mutasi()->where('jenis', 'rusak')->exists()) {
            $inventaris->catat($meja, 'rusak', 1, [
                'penanggung_jawab_nama' => 'Pengurus Bidang Organisasi',
                'catatan' => 'Engsel patah saat penurunan panggung.',
            ], $pencatat);
        }

        $this->seedBuku();
        $this->seedAnggotaPeminjam();
    }

    /**
     * Buat/ambil kategori berdasarkan slug, lengkap dengan terjemahannya.
     */
    private function kategori(string $slug, array $nama, int $urutan): InventoryCategory
    {
        $kategori = InventoryCategory::query()->where('slug', $slug)->first();

        if (! $kategori) {
            $kategori = new InventoryCategory;
            $kategori->slug = $slug;
            $kategori->urutan = $urutan;
            $kategori->aktif = true;
        }

        $kategori->setTranslations('nama', $nama);
        $kategori->save();

        return $kategori;
    }

    private function seedBuku(): void
    {
        $buku = [
            [
                'judul' => ['id' => 'Dasar-Dasar Ilmu Politik', 'en' => 'Foundations of Political Science'],
                'sinopsis' => ['id' => 'Pengantar klasik ilmu politik untuk mahasiswa.', 'en' => 'A classic introduction to political science.'],
                'penulis' => 'Miriam Budiardjo',
                'penerbit' => 'Gramedia',
                'tahun_terbit' => 2008,
                'isbn' => '9789792229411',
                'ddc' => '320',
                'kategori' => 'Politik',
                'jumlah_halaman' => 560,
                'rak' => 'A-1',
                'eksemplar' => 3,
            ],
            [
                'judul' => ['id' => 'Sejarah Indonesia Modern', 'en' => 'A Modern History of Indonesia'],
                'sinopsis' => ['id' => 'Menelusuri lintasan sejarah Indonesia sejak kolonialisme.', 'en' => 'Tracing Indonesian history from colonialism.'],
                'penulis' => 'M. C. Ricklefs',
                'penerbit' => 'Serambi',
                'tahun_terbit' => 2008,
                'isbn' => '9789791113933',
                'ddc' => '959.8',
                'kategori' => 'Sejarah',
                'jumlah_halaman' => 704,
                'rak' => 'B-2',
                'eksemplar' => 2,
            ],
            [
                'judul' => ['id' => 'Sosiologi Suatu Pengantar', 'en' => 'Sociology: An Introduction'],
                'sinopsis' => ['id' => 'Rujukan dasar sosiologi bagi kader baru.', 'en' => 'A basic sociology reference for new cadres.'],
                'penulis' => 'Soerjono Soekanto',
                'penerbit' => 'Rajawali Pers',
                'tahun_terbit' => 2012,
                'isbn' => '9789797695850',
                'ddc' => '301',
                'kategori' => 'Sosial',
                'jumlah_halaman' => 498,
                'rak' => 'B-1',
                // Sengaja hanya satu eksemplar: dipakai untuk membuktikan
                // antrian otomatis naik saat buku dikembalikan.
                'eksemplar' => 1,
            ],
        ];

        foreach ($buku as $data) {
            $judulId = $data['judul']['id'];
            $slug = Book::slugUnik($judulId);

            $book = Book::query()->where('isbn', $data['isbn'])->first();

            if (! $book) {
                $book = new Book;
                $book->slug = $slug;
                $book->isbn = $data['isbn'];
                $book->penulis = $data['penulis'];
                $book->penerbit = $data['penerbit'];
                $book->tahun_terbit = $data['tahun_terbit'];
                $book->ddc = $data['ddc'];
                $book->kategori = $data['kategori'];
                $book->bahasa = 'id';
                $book->jumlah_halaman = $data['jumlah_halaman'];
                $book->rak = $data['rak'];
                $book->is_public = true;
                $book->aktif = true;
            }

            // setTranslations SEBELUM save(): kolom JSON-nya NOT NULL.
            $book->setTranslations('judul', $data['judul']);
            $book->setTranslations('sinopsis', $data['sinopsis']);
            $book->save();

            $inisial = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $judulId) ?: 'BK', 0, 3));

            for ($i = 1; $i <= $data['eksemplar']; $i++) {
                BookCopy::query()->firstOrCreate(
                    ['kode_eksemplar' => sprintf('%s-%03d-%02d', $inisial, $book->id, $i)],
                    [
                        'book_id' => $book->id,
                        'kondisi' => 'baik',
                        'status' => BookCopy::STATUS_TERSEDIA,
                        'rak' => $data['rak'],
                        'nilai' => 120000,
                    ],
                );
            }
        }
    }

    private function seedAnggotaPeminjam(): void
    {
        $contoh = [
            ['nama' => 'Ahmad Fauzi', 'email' => 'ahmad@raab.test'],
            ['nama' => 'Siti Nurhaliza', 'email' => 'siti@raab.test'],
        ];

        foreach ($contoh as $data) {
            $user = User::query()->where('email', $data['email'])->first();

            if (! $user) {
                $user = new User;
                $user->name = $data['nama'];
                $user->email = $data['email'];
                $user->password = Hash::make('rahasia123');
                $user->status = 'aktif';
                $user->email_verified_at = now();
                $user->save();
            }

            // Relasinya `users hasOne members` — jadi kuncinya ada di
            // members.user_id, bukan kolom member_id pada tabel users.
            $member = Member::query()->where('user_id', $user->id)->first();

            if (! $member) {
                $member = new Member;
                $member->user_id = $user->id;
                $member->nama_lengkap = $data['nama'];
                $member->jalur = Member::JALUR_KADER;
                $member->status = Member::STATUS_AKTIF;
                $member->profil_publik = true;
                // privasi disimpan sebagai JSON berisi izin per kolom.
                $member->privasi = ['telepon' => false, 'email' => false];
                $member->save();
            }
        }
    }
}
