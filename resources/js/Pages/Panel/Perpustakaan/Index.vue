<script setup lang="ts">
/*
 * Katalog buku & eksemplar.
 *
 * Status eksemplar yang sedang dipinjam TIDAK dapat diubah dari sini —
 * statusnya ditentukan oleh proses pengembalian di halaman Peminjaman.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Eksemplar {
    id: number;
    kode_eksemplar: string;
    status: string;
    label_status: string;
    kondisi: string;
    rak: string | null;
    nilai: number;
    catatan: string | null;
    sedang_dipinjam: boolean;
}

interface Buku {
    id: number;
    judul: Record<string, string> | null;
    slug: string | null;
    sinopsis: Record<string, string> | null;
    penulis: string | null;
    penerbit: string | null;
    tahun_terbit: number | null;
    isbn: string | null;
    ddc: string | null;
    kategori: string | null;
    bahasa: string;
    jumlah_halaman: number | null;
    cover_media_id: number | null;
    rak: string | null;
    is_public: boolean;
    aktif: boolean;
    total_eksemplar: number;
    tersedia: number;
    eksemplar: Eksemplar[];
}

const props = defineProps<{
    daftar: Buku[];
    cari: string;
    statusEksemplar: Record<string, string>;
    kondisi: Record<string, string>;
    pilihanCover: { id: number; nama: string; url: string }[];
}>();

const sunting = ref<number | null>(null);
const kelolaEksemplar = ref<number | null>(null);

const form = useForm({
    judul: { id: '', en: '' } as Record<string, string>,
    sinopsis: { id: '', en: '' } as Record<string, string>,
    penulis: '',
    penerbit: '',
    tahun_terbit: null as number | null,
    isbn: '',
    ddc: '',
    kategori: '',
    bahasa: 'id',
    jumlah_halaman: null as number | null,
    cover_media_id: null as number | null,
    rak: '',
    is_public: true,
    aktif: true,
});

const formEksemplar = useForm({ jumlah: 1, rak: '', nilai: 0, tanggal_perolehan: '', catatan: '' });

function reset(): void {
    sunting.value = null;
    form.reset();
    form.clearErrors();
}

function mulaiSunting(buku: Buku): void {
    sunting.value = buku.id;
    form.clearErrors();
    form.judul = { id: buku.judul?.id ?? '', en: buku.judul?.en ?? '' };
    form.sinopsis = { id: buku.sinopsis?.id ?? '', en: buku.sinopsis?.en ?? '' };
    form.penulis = buku.penulis ?? '';
    form.penerbit = buku.penerbit ?? '';
    form.tahun_terbit = buku.tahun_terbit;
    form.isbn = buku.isbn ?? '';
    form.ddc = buku.ddc ?? '';
    form.kategori = buku.kategori ?? '';
    form.bahasa = buku.bahasa;
    form.jumlah_halaman = buku.jumlah_halaman;
    form.cover_media_id = buku.cover_media_id;
    form.rak = buku.rak ?? '';
    form.is_public = buku.is_public;
    form.aktif = buku.aktif;
}

function simpan(): void {
    const opsi = { preserveScroll: true, onSuccess: () => reset() };

    if (sunting.value) {
        form.put(`/panel/perpustakaan/${sunting.value}`, opsi);
    } else {
        form.post('/panel/perpustakaan', opsi);
    }
}

function hapus(buku: Buku): void {
    if (!confirm(`Hapus buku "${buku.judul?.id}"? Buku yang punya riwayat peminjaman tidak akan terhapus.`)) {
        return;
    }

    router.delete(`/panel/perpustakaan/${buku.id}`, { preserveScroll: true });
}

function tambahEksemplar(buku: Buku): void {
    formEksemplar.post(`/panel/perpustakaan/${buku.id}/eksemplar`, {
        preserveScroll: true,
        onSuccess: () => formEksemplar.reset(),
    });
}

function ubahStatus(eksemplar: Eksemplar, status: string): void {
    router.put(`/panel/perpustakaan/eksemplar/${eksemplar.id}`, {
        status,
        rak: eksemplar.rak,
        catatan: eksemplar.catatan,
    }, { preserveScroll: true });
}

function hapusEksemplar(eksemplar: Eksemplar): void {
    if (!confirm(`Hapus eksemplar ${eksemplar.kode_eksemplar}?`)) {
        return;
    }

    router.delete(`/panel/perpustakaan/eksemplar/${eksemplar.id}`, { preserveScroll: true });
}

function cariBuku(): void {
    router.get('/panel/perpustakaan', { cari: (document.getElementById('cari-buku') as HTMLInputElement).value }, { preserveScroll: false });
}
</script>

<template>
    <PanelLayout>
        <Head title="Katalog Buku" />

        <div class="mx-auto max-w-5xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Katalog Buku</h1>
                    <p class="mt-1 text-sm text-muted">
                        Ketersediaan dihitung dari eksemplar, bukan dari judul. Satu judul boleh punya beberapa salinan.
                    </p>
                </div>

                <a href="/panel/peminjaman" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">Peminjaman →</a>
            </div>

            <form class="brutal flex flex-wrap items-end gap-3 bg-paper p-4" @submit.prevent="cariBuku">
                <label class="block flex-1">
                    <span class="text-xs font-bold uppercase text-muted">Cari judul, penulis, atau ISBN</span>
                    <input id="cari-buku" type="search" :value="props.cari" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>
                <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Cari</button>
                <a href="/panel/perpustakaan" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold">Reset</a>
            </form>

            <!-- Formulir -->
            <form class="brutal bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">{{ sunting ? 'Sunting Buku' : 'Tambah Buku' }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Judul (Indonesia) *</span>
                        <input v-model="form.judul.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors['judul.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors['judul.id'] }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Judul (Inggris)</span>
                        <input v-model="form.judul.en" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Penulis</span>
                        <input v-model="form.penulis" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Penerbit</span>
                        <input v-model="form.penerbit" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Tahun terbit</span>
                        <input v-model.number="form.tahun_terbit" type="number" min="1500" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">ISBN</span>
                        <input v-model="form.isbn" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">DDC</span>
                        <input v-model="form.ddc" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                        <input v-model="form.kategori" type="text" placeholder="mis. Politik" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Rak</span>
                        <input v-model="form.rak" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Jumlah halaman</span>
                        <input v-model.number="form.jumlah_halaman" type="number" min="1" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Cover</span>
                        <select v-model="form.cover_media_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— tanpa cover —</option>
                            <option v-for="gambar in props.pilihanCover" :key="gambar.id" :value="gambar.id">{{ gambar.nama }}</option>
                        </select>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Sinopsis (Indonesia)</span>
                        <textarea v-model="form.sinopsis.id" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Sinopsis (Inggris)</span>
                        <textarea v-model="form.sinopsis.en" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input v-model="form.is_public" type="checkbox" class="h-4 w-4"> Tampil di katalog publik
                    </label>
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input v-model="form.aktif" type="checkbox" class="h-4 w-4"> Aktif
                    </label>
                </div>

                <div class="mt-4 flex gap-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        {{ sunting ? 'Simpan Perubahan' : 'Tambah Buku' }}
                    </button>
                    <button v-if="sunting" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="reset">Batal</button>
                </div>
            </form>

            <!-- Daftar buku -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Daftar Buku ({{ props.daftar.length }})</h2>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada buku pada katalog.</p>

                <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="buku in props.daftar" :key="buku.id" class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ buku.judul?.id }}
                                    <span v-if="!buku.aktif" class="ml-2 text-xs font-normal text-muted">(nonaktif)</span>
                                    <span v-if="!buku.is_public" class="ml-2 text-xs font-normal text-muted">(tidak publik)</span>
                                </p>
                                <p class="text-xs text-muted">
                                    <template v-if="buku.penulis">{{ buku.penulis }} · </template>
                                    <template v-if="buku.penerbit">{{ buku.penerbit }} </template>
                                    <template v-if="buku.tahun_terbit">({{ buku.tahun_terbit }}) · </template>
                                    <template v-if="buku.isbn">ISBN {{ buku.isbn }} · </template>
                                    <template v-if="buku.rak">Rak {{ buku.rak }} · </template>
                                    {{ buku.total_eksemplar }} eksemplar ·
                                    <span :class="buku.tersedia > 0 ? 'font-bold text-success' : 'text-accent-600'">
                                        {{ buku.tersedia }} tersedia
                                    </span>
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="kelolaEksemplar = kelolaEksemplar === buku.id ? null : buku.id">
                                    {{ kelolaEksemplar === buku.id ? 'Tutup Eksemplar' : `Eksemplar (${buku.total_eksemplar})` }}
                                </button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(buku)">Sunting</button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(buku)">Hapus</button>
                            </div>
                        </div>

                        <div v-if="kelolaEksemplar === buku.id" class="mt-4 border-t-2 border-ink/10 pt-4">
                            <form class="grid gap-3 sm:grid-cols-4" @submit.prevent="tambahEksemplar(buku)">
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Jumlah eksemplar baru</span>
                                    <input v-model.number="formEksemplar.jumlah" type="number" min="1" max="50" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Rak</span>
                                    <input v-model="formEksemplar.rak" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Nilai (Rp)</span>
                                    <input v-model.number="formEksemplar.nilai" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                </label>
                                <div class="flex items-end">
                                    <button type="submit" :disabled="formEksemplar.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Tambah</button>
                                </div>
                            </form>

                            <table class="mt-4 w-full text-sm">
                                <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                                    <tr>
                                        <th class="py-2">Kode</th>
                                        <th class="py-2">Status</th>
                                        <th class="py-2">Rak</th>
                                        <th class="py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="salinan in buku.eksemplar" :key="salinan.id" class="border-b-2 border-ink/10">
                                        <td class="py-2 font-mono text-xs">{{ salinan.kode_eksemplar }}</td>
                                        <td class="py-2">
                                            <span :class="['border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', salinan.sedang_dipinjam ? 'bg-accent-100' : 'bg-success/30']">
                                                {{ salinan.label_status }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-xs">{{ salinan.rak ?? '—' }}</td>
                                        <td class="py-2">
                                            <div class="flex justify-end gap-2">
                                                <select
                                                    :value="salinan.status"
                                                    :disabled="salinan.sedang_dipinjam"
                                                    class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold disabled:opacity-50"
                                                    @change="ubahStatus(salinan, ($event.target as HTMLSelectElement).value)"
                                                >
                                                    <option v-for="(label, nilai) in props.statusEksemplar" :key="nilai" :value="nilai">{{ label }}</option>
                                                </select>
                                                <button type="button" class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold" @click="hapusEksemplar(salinan)">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
