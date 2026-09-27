<script setup lang="ts">
/*
 * Kategori keuangan bertingkat.
 *
 * Sub-kategori harus SEJENIS dengan induknya — kalau tidak, laporan penerimaan
 * dan pengeluaran akan saling bercampur dan angka rekapnya menyesatkan.
 *
 * Kategori yang sudah dipakai transaksi atau anggaran tidak dapat dihapus,
 * hanya dinonaktifkan: laporan lama harus tetap punya label.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Kategori {
    id: number;
    kode: string;
    nama: string;
    label_lengkap: string;
    jenis: string;
    label_jenis: string;
    parent_id: number | null;
    induk: string | null;
    urutan: number;
    aktif: boolean;
    jumlah_transaksi: number;
    terpakai: boolean;
}

const props = defineProps<{
    daftar: Kategori[];
    pilihanJenis: Record<string, string>;
    pilihanInduk: { id: number; nama: string; jenis: string }[];
}>();

const sunting = ref<number | null>(null);

const form = useForm({
    nama: '',
    jenis: 'keluar',
    parent_id: null as number | null,
    urutan: 0,
    keterangan: '',
});

const formUbah = useForm({
    nama: '',
    jenis: 'keluar',
    parent_id: null as number | null,
    urutan: 0,
    aktif: true,
    keterangan: '',
});

const indukTersedia = computed(() => props.pilihanInduk.filter((i) => i.jenis === form.jenis));
const indukTersediaUbah = computed(() => props.pilihanInduk.filter((i) => i.jenis === formUbah.jenis));

function simpan(): void {
    form.post('/panel/keuangan/kategori', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function mulaiSunting(k: Kategori): void {
    sunting.value = k.id;
    formUbah.clearErrors();
    formUbah.nama = k.nama;
    formUbah.jenis = k.jenis;
    formUbah.parent_id = k.parent_id;
    formUbah.urutan = k.urutan;
    formUbah.aktif = k.aktif;
    formUbah.keterangan = '';
}

function perbarui(k: Kategori): void {
    formUbah.put(`/panel/keuangan/kategori/${k.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            sunting.value = null;
            formUbah.reset();
        },
    });
}

function hapus(k: Kategori): void {
    if (!confirm(`Hapus kategori ${k.label_lengkap}?`)) {
        return;
    }

    router.delete(`/panel/keuangan/kategori/${k.id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Kategori Keuangan" />

        <div class="mx-auto max-w-5xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Kategori Keuangan</h1>
                    <p class="mt-1 text-sm text-muted">
                        Sub-kategori harus sejenis dengan induknya. Kategori yang sudah dipakai tidak dihapus, hanya dinonaktifkan.
                    </p>
                </div>
                <a href="/panel/keuangan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Ikhtisar</a>
            </div>

            <form class="brutal grid gap-4 bg-paper p-5 sm:grid-cols-3" @submit.prevent="simpan">
                <h2 class="font-display text-lg sm:col-span-3">Tambah Kategori</h2>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                    <input v-model="form.nama" type="text" placeholder="ATK" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                    <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Induk (untuk sub-kategori)</span>
                    <select v-model="form.parent_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— jadikan kategori induk —</option>
                        <option v-for="i in indukTersedia" :key="i.id" :value="i.id">{{ i.nama }}</option>
                    </select>
                    <span v-if="form.errors.parent_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.parent_id }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                    <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                    <input v-model="form.keterangan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <div class="flex items-end sm:col-span-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Tambah Kategori
                    </button>
                </div>
            </form>

            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Daftar Kategori</h2>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada kategori.</p>

                <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="k in props.daftar" :key="k.id" class="py-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    <span v-if="k.induk" class="text-muted">{{ k.induk }} › </span>{{ k.nama }}
                                    <span class="ml-2 font-mono text-xs font-normal text-muted">{{ k.kode }}</span>
                                    <span class="ml-2 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">{{ k.label_jenis }}</span>
                                    <span v-if="!k.aktif" class="ml-1 border-2 border-ink bg-accent-100 px-2 py-0.5 text-[10px] font-bold uppercase">Nonaktif</span>
                                </p>
                                <p class="text-xs text-muted">
                                    {{ k.jumlah_transaksi }} transaksi
                                    <template v-if="k.terpakai"> · sudah dipakai, tidak dapat dihapus</template>
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(k)">
                                    {{ sunting === k.id ? 'Tutup' : 'Sunting' }}
                                </button>
                                <button v-if="!k.terpakai" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(k)">
                                    Hapus
                                </button>
                            </div>
                        </div>

                        <form v-if="sunting === k.id" class="mt-3 grid gap-3 border-t-2 border-ink/10 pt-3 sm:grid-cols-3" @submit.prevent="perbarui(k)">
                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                                <input v-model="formUbah.nama" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <span v-if="formUbah.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ formUbah.errors.nama }}</span>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                                <select v-model="formUbah.jenis" :disabled="k.jumlah_transaksi > 0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm disabled:opacity-60">
                                    <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                                </select>
                                <span v-if="k.jumlah_transaksi > 0" class="text-[11px] text-muted">Tidak dapat diubah karena sudah dipakai.</span>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Induk</span>
                                <select v-model="formUbah.parent_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option :value="null">— jadikan kategori induk —</option>
                                    <option v-for="i in indukTersediaUbah.filter((x) => x.id !== k.id)" :key="i.id" :value="i.id">{{ i.nama }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                                <input v-model.number="formUbah.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>

                            <label class="flex items-center gap-2 self-end text-sm font-bold">
                                <input v-model="formUbah.aktif" type="checkbox" class="h-4 w-4"> Aktif
                            </label>

                            <div class="flex items-end gap-2">
                                <button type="submit" :disabled="formUbah.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                    Simpan
                                </button>
                                <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="sunting = null">Batal</button>
                            </div>
                        </form>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
