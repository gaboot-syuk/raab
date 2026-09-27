<script setup lang="ts">
/*
 * Kategori, aset, dan mutasi stok.
 *
 * JUMLAH ASET TIDAK DAPAT DISUNTING LANGSUNG — hanya lewat mutasi. Kolom
 * jumlah pada formulir di bawah hanya ada saat aset BARU didaftarkan, dan
 * nilainya dicatat sebagai mutasi "barang masuk".
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Aset {
    id: number;
    kode: string;
    nama: Record<string, string> | null;
    keterangan: Record<string, string> | null;
    kategori_id: number | null;
    kategori: string | null;
    satuan: string;
    jumlah: number;
    jumlah_minimum: number;
    jumlah_dipinjam: number;
    jumlah_tersedia: number;
    kondisi: string;
    label_kondisi: string;
    lokasi: string | null;
    nilai: number;
    is_public: boolean;
    aktif: boolean;
    stok_menipis: boolean;
}

interface Riwayat {
    id: number;
    aset: string | null;
    kode_aset: string | null;
    label_jenis: string;
    jumlah: number;
    jumlah_sebelum: number;
    jumlah_sesudah: number;
    penanggung_jawab: string | null;
    pencatat: string | null;
    catatan: string | null;
    terjadi_pada: string | null;
}

const props = defineProps<{
    daftar: Aset[];
    pilihanKategori: { id: number; nama: string; slug: string; aktif: boolean }[];
    jenisMutasi: Record<string, string>;
    kondisi: Record<string, string>;
    pilihanAnggota: { id: number; label: string }[];
    riwayat: Riwayat[];
    saring: { kategori: string; cari: string; menipis: string };
}>();

const tab = ref<'aset' | 'kategori' | 'riwayat'>('aset');
const sunting = ref<number | null>(null);
const kelolaKategori = ref(false);
const mutasiUntuk = ref<Aset | null>(null);

const form = useForm({
    kode: '',
    nama: { id: '', en: '' } as Record<string, string>,
    keterangan: { id: '', en: '' } as Record<string, string>,
    category_id: null as number | null,
    satuan: 'buah',
    jumlah_awal: 0,
    jumlah_minimum: 0,
    kondisi: 'baik',
    lokasi: '',
    nilai: 0,
    is_public: false,
    aktif: true,
});

const formMutasi = useForm({
    jenis: 'masuk',
    jumlah: 1,
    penanggung_jawab_id: null as number | null,
    penanggung_jawab_nama: '',
    catatan: '',
    terjadi_pada: '',
});

const formKategori = useForm({ nama: { id: '', en: '' } as Record<string, string>, urutan: 0, aktif: true });

function reset(): void {
    sunting.value = null;
    form.reset();
    form.clearErrors();
}

function mulaiSunting(aset: Aset): void {
    sunting.value = aset.id;
    form.clearErrors();
    form.kode = aset.kode;
    form.nama = { id: aset.nama?.id ?? '', en: aset.nama?.en ?? '' };
    form.keterangan = { id: aset.keterangan?.id ?? '', en: aset.keterangan?.en ?? '' };
    form.category_id = aset.kategori_id;
    form.satuan = aset.satuan;
    form.jumlah_minimum = aset.jumlah_minimum;
    form.kondisi = aset.kondisi;
    form.lokasi = aset.lokasi ?? '';
    form.nilai = aset.nilai;
    form.is_public = aset.is_public;
    form.aktif = aset.aktif;
}

function simpan(): void {
    const opsi = { preserveScroll: true, onSuccess: () => reset() };

    if (sunting.value) {
        form.put(`/panel/inventaris/${sunting.value}`, opsi);
    } else {
        form.post('/panel/inventaris', opsi);
    }
}

function hapus(aset: Aset): void {
    if (!confirm(`Hapus aset "${aset.nama?.id}"?`)) {
        return;
    }

    router.delete(`/panel/inventaris/${aset.id}`, { preserveScroll: true });
}

function bukaMutasi(aset: Aset): void {
    mutasiUntuk.value = mutasiUntuk.value?.id === aset.id ? null : aset;
    formMutasi.reset();
    formMutasi.clearErrors();
}

function catatMutasi(): void {
    if (!mutasiUntuk.value) {
        return;
    }

    formMutasi.post(`/panel/inventaris/${mutasiUntuk.value.id}/mutasi`, {
        preserveScroll: true,
        onSuccess: () => formMutasi.reset(),
    });
}

function simpanKategori(): void {
    formKategori.post('/panel/inventaris/kategori', {
        preserveScroll: true,
        onSuccess: () => formKategori.reset(),
    });
}

function hapusKategori(id: number): void {
    if (!confirm('Hapus kategori ini?')) {
        return;
    }

    router.delete(`/panel/inventaris/kategori/${id}`, { preserveScroll: true });
}

function saringkan(): void {
    router.get('/panel/inventaris', {
        kategori: props.saring.kategori,
        cari: props.saring.cari,
        menipis: props.saring.menipis,
    }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Inventaris" />

        <div class="mx-auto max-w-5xl space-y-6">
            <PesanHasil />

            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Inventaris</h1>
                <p class="mt-1 text-sm text-muted">
                    Jumlah stok hanya berubah lewat mutasi — supaya setiap perubahan punya jejak dan penanggung jawab.
                    Barang rusak dan hilang <span class="font-bold">wajib</span> menyebut penanggung jawab.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" :class="['brutal-sm px-4 py-2 text-sm font-bold', tab === 'aset' ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt']" @click="tab = 'aset'">Aset ({{ props.daftar.length }})</button>
                <button type="button" :class="['brutal-sm px-4 py-2 text-sm font-bold', tab === 'kategori' ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt']" @click="tab = 'kategori'">Kategori ({{ props.pilihanKategori.length }})</button>
                <button type="button" :class="['brutal-sm px-4 py-2 text-sm font-bold', tab === 'riwayat' ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt']" @click="tab = 'riwayat'">Riwayat Mutasi</button>
            </div>

            <!-- ============================ ASET ============================ -->
            <template v-if="tab === 'aset'">
                <form class="brutal bg-paper p-5" @submit.prevent="simpan">
                    <h2 class="font-display text-lg">{{ sunting ? 'Sunting Aset' : 'Daftarkan Aset' }}</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kode *</span>
                            <input v-model="form.kode" type="text" placeholder="INV-001" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.kode" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.kode }}</span>
                        </label>

                        <label class="block sm:col-span-1 lg:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Nama (Indonesia) *</span>
                            <input v-model="form.nama.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors['nama.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors['nama.id'] }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                            <select v-model="form.category_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— tanpa kategori —</option>
                                <option v-for="k in props.pilihanKategori" :key="k.id" :value="k.id">{{ k.nama }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Satuan</span>
                            <input v-model="form.satuan" type="text" placeholder="buah / set / lembar" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label v-if="!sunting" class="block">
                            <span class="text-xs font-bold uppercase text-muted">Stok awal</span>
                            <input v-model.number="form.jumlah_awal" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span class="text-xs text-muted">Dicatat sebagai mutasi "barang masuk".</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Stok minimum</span>
                            <input v-model.number="form.jumlah_minimum" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kondisi *</span>
                            <select v-model="form.kondisi" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, nilai) in props.kondisi" :key="nilai" :value="nilai">{{ label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Lokasi</span>
                            <input v-model="form.lokasi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Nilai per satuan (Rp)</span>
                            <input v-model.number="form.nilai" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block sm:col-span-2 lg:col-span-3">
                            <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                            <input v-model="form.keterangan.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
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
                            {{ sunting ? 'Simpan Perubahan' : 'Daftarkan Aset' }}
                        </button>
                        <button v-if="sunting" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="reset">Batal</button>
                    </div>
                </form>

                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Daftar Aset</h2>

                    <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada aset.</p>

                    <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="aset in props.daftar" :key="aset.id" class="py-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold">
                                        {{ aset.nama?.id }}
                                        <span class="ml-2 font-mono text-xs font-normal text-muted">{{ aset.kode }}</span>
                                        <span v-if="aset.stok_menipis" class="ml-2 border-2 border-ink bg-accent-100 px-2 py-0.5 text-[10px] font-bold uppercase">Stok menipis</span>
                                        <span v-if="!aset.aktif" class="ml-2 text-xs font-normal text-muted">(nonaktif)</span>
                                    </p>
                                    <p class="text-xs text-muted">
                                        {{ aset.kategori ?? 'Tanpa kategori' }} · {{ aset.lokasi ?? 'lokasi belum diisi' }} · {{ aset.label_kondisi }}
                                    </p>
                                    <p class="mt-1 text-sm">
                                        <span class="font-display text-lg">{{ aset.jumlah }}</span> {{ aset.satuan }} tercatat
                                        · <span class="font-bold">{{ aset.jumlah_tersedia }}</span> siap dipakai
                                        <template v-if="aset.jumlah_dipinjam > 0"> · {{ aset.jumlah_dipinjam }} sedang dipinjam</template>
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="bukaMutasi(aset)">
                                        {{ mutasiUntuk?.id === aset.id ? 'Tutup' : 'Catat Mutasi' }}
                                    </button>
                                    <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(aset)">Sunting</button>
                                    <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(aset)">Hapus</button>
                                </div>
                            </div>

                            <form v-if="mutasiUntuk?.id === aset.id" class="mt-4 grid gap-3 border-t-2 border-ink/10 pt-4 sm:grid-cols-3" @submit.prevent="catatMutasi">
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Jenis mutasi *</span>
                                    <select v-model="formMutasi.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                        <option v-for="(label, nilai) in props.jenisMutasi" :key="nilai" :value="nilai">{{ label }}</option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Jumlah *</span>
                                    <input v-model.number="formMutasi.jumlah" type="number" min="1" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <span v-if="formMutasi.errors.jumlah" class="mt-1 block text-xs font-bold text-accent-600">{{ formMutasi.errors.jumlah }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Tanggal kejadian</span>
                                    <input v-model="formMutasi.terjadi_pada" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Penanggung jawab (anggota)</span>
                                    <select v-model="formMutasi.penanggung_jawab_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                        <option :value="null">— bukan anggota —</option>
                                        <option v-for="orang in props.pilihanAnggota" :key="orang.id" :value="orang.id">{{ orang.label }}</option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">atau nama manual</span>
                                    <input v-model="formMutasi.penanggung_jawab_nama" type="text" placeholder="wajib untuk rusak/hilang" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <span v-if="formMutasi.errors.penanggung_jawab_nama" class="mt-1 block text-xs font-bold text-accent-600">{{ formMutasi.errors.penanggung_jawab_nama }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Catatan</span>
                                    <input v-model="formMutasi.catatan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                </label>

                                <div class="flex items-end sm:col-span-3">
                                    <button type="submit" :disabled="formMutasi.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                        Catat Mutasi
                                    </button>
                                </div>
                            </form>
                        </li>
                    </ul>
                </section>
            </template>

            <!-- ========================== KATEGORI ========================== -->
            <template v-else-if="tab === 'kategori'">
                <form class="brutal bg-paper p-5" @submit.prevent="simpanKategori">
                    <h2 class="font-display text-lg">Tambah Kategori</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Nama (Indonesia) *</span>
                            <input v-model="formKategori.nama.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formKategori.errors['nama.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ formKategori.errors['nama.id'] }}</span>
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                            <input v-model.number="formKategori.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>
                    </div>
                    <button type="submit" :disabled="formKategori.processing" class="brutal-sm mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Tambah Kategori
                    </button>
                </form>

                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Kategori</h2>
                    <ul v-if="props.pilihanKategori.length" class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="k in props.pilihanKategori" :key="k.id" class="flex items-center justify-between gap-3 py-2.5">
                            <span class="font-bold">{{ k.nama }}</span>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapusKategori(k.id)">Hapus</button>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">Belum ada kategori.</p>
                </section>
            </template>

            <!-- =========================== RIWAYAT =========================== -->
            <section v-else class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Riwayat Mutasi</h2>

                <p v-if="!props.riwayat.length" class="mt-3 text-sm text-muted">Belum ada mutasi.</p>

                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Waktu</th>
                            <th class="py-2">Aset</th>
                            <th class="py-2">Mutasi</th>
                            <th class="py-2 text-right">Stok</th>
                            <th class="py-2">Penanggung jawab</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in props.riwayat" :key="m.id" class="border-b-2 border-ink/10">
                            <td class="py-2 text-xs">{{ m.terjadi_pada }}</td>
                            <td class="py-2">
                                {{ m.aset }}
                                <span class="ml-1 font-mono text-xs text-muted">{{ m.kode_aset }}</span>
                            </td>
                            <td class="py-2">
                                {{ m.label_jenis }}
                                <span class="text-xs text-muted">({{ m.jumlah }})</span>
                                <span v-if="m.catatan" class="block text-xs text-muted">{{ m.catatan }}</span>
                            </td>
                            <td class="py-2 text-right text-xs">{{ m.jumlah_sebelum }} → {{ m.jumlah_sesudah }}</td>
                            <td class="py-2 text-xs">{{ m.penanggung_jawab ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </PanelLayout>
</template>
