<script setup lang="ts">
/*
 * Kategori & tag artikel. Keduanya alat pengelompokan, jadi digabung dalam
 * satu halaman agar pengurus tidak berpindah-pindah tempat.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Kategori {
    id: number;
    nama_id: string | null;
    nama_en: string | null;
    slug_id: string | null;
    urutan: number;
    aktif: boolean;
    jumlah_terbit: number;
}

const props = defineProps<{
    kategori: Kategori[];
    tag: { id: number; nama: string | null; dipakai: number }[];
}>();

const kolom = 'brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm';

const formBaru = useForm({ nama_id: '', nama_en: '', urutan: null as number | null });
const disunting = ref<Kategori | null>(null);
const formUbah = useForm({ nama_id: '', nama_en: '', urutan: 0, aktif: true });

function simpanBaru() {
    formBaru.post('/panel/kategori-artikel', {
        preserveScroll: true,
        onSuccess: () => formBaru.reset(),
    });
}

function bukaUbah(k: Kategori) {
    disunting.value = k;
    formUbah.nama_id = k.nama_id ?? '';
    formUbah.nama_en = k.nama_en ?? '';
    formUbah.urutan = k.urutan;
    formUbah.aktif = k.aktif;
    formUbah.clearErrors();
}

function simpanUbah() {
    if (!disunting.value) {
        return;
    }

    formUbah.put(`/panel/kategori-artikel/${disunting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (disunting.value = null),
    });
}

function hapusKategori(k: Kategori) {
    if (!confirm(`Hapus kategori "${k.nama_id}"? Artikelnya TIDAK terhapus, hanya kehilangan kategori.`)) {
        return;
    }

    router.delete(`/panel/kategori-artikel/${k.id}`, { preserveScroll: true });
}

function hapusTag(id: number, nama: string | null) {
    if (!confirm(`Hapus tag "${nama}"? Tag akan dilepas dari semua artikel.`)) {
        return;
    }

    router.delete(`/panel/tag-artikel/${id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Kategori & Tag" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Kategori &amp; Tag</h1>
                <p class="mt-1 text-sm text-muted">
                    Kategori mengelompokkan artikel publikasi; tag menandai tema yang melintasi kategori.
                </p>
            </div>

            <PesanHasil />

            <!-- Tambah kategori -->
            <form class="brutal space-y-4 bg-paper p-5" @submit.prevent="simpanBaru">
                <h2 class="font-display text-lg">Tambah Kategori</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="nama_id" class="block text-sm font-bold">Nama (Indonesia) *</label>
                        <input id="nama_id" v-model="formBaru.nama_id" type="text" required maxlength="120" :class="kolom">
                    </div>
                    <div>
                        <label for="nama_en" class="block text-sm font-bold">Nama (Inggris)</label>
                        <input id="nama_en" v-model="formBaru.nama_en" type="text" maxlength="120" :class="kolom">
                    </div>
                    <div>
                        <label for="urutan" class="block text-sm font-bold">Urutan</label>
                        <input id="urutan" v-model.number="formBaru.urutan" type="number" min="0" max="999" :class="kolom">
                    </div>
                </div>
                <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formBaru.processing">
                    Tambah Kategori
                </button>
            </form>

            <!-- Daftar kategori -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Kategori ({{ props.kategori.length }})</h2>

                <p v-if="!props.kategori.length" class="mt-3 text-sm text-muted">Belum ada kategori.</p>

                <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="k in props.kategori" :key="k.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="font-bold">{{ k.nama_id }} <span class="text-xs text-muted">(#{{ k.urutan }})</span></p>
                            <p class="text-xs text-muted">
                                {{ k.nama_en || 'Versi Inggris belum diisi' }} ·
                                {{ k.jumlah_terbit }} artikel terbit ·
                                <span v-if="!k.aktif" class="font-bold">nonaktif</span>
                                <span v-else>aktif</span>
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <button type="button" class="brutal-sm bg-paper px-2 py-1 text-xs font-bold" @click="bukaUbah(k)">Ubah</button>
                            <button type="button" class="brutal-sm bg-accent-100 px-2 py-1 text-xs font-bold" @click="hapusKategori(k)">Hapus</button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Daftar tag -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Tag Terpakai ({{ props.tag.length }})</h2>
                <p class="mt-1 text-xs text-muted">Tag dibuat otomatis saat kamu menuliskannya pada artikel.</p>

                <p v-if="!props.tag.length" class="mt-3 text-sm text-muted">Belum ada tag.</p>

                <ul v-else class="mt-3 flex flex-wrap gap-2">
                    <li v-for="t in props.tag" :key="t.id" class="flex items-center gap-1 border-2 border-ink bg-paper-alt px-2 py-1 text-xs font-bold">
                        #{{ t.nama }} <span class="text-muted">({{ t.dipakai }})</span>
                        <button type="button" class="ml-1 text-danger" :title="`Hapus tag ${t.nama}`" @click="hapusTag(t.id, t.nama)">✕</button>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Modal ubah kategori -->
        <div v-if="disunting" class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4" @click.self="disunting = null">
            <form class="brutal w-full max-w-md bg-paper p-5" @submit.prevent="simpanUbah">
                <h2 class="font-display text-lg">Ubah Kategori</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="ubah-nama-id" class="block text-sm font-bold">Nama (Indonesia) *</label>
                        <input id="ubah-nama-id" v-model="formUbah.nama_id" type="text" required maxlength="120" :class="kolom">
                    </div>
                    <div>
                        <label for="ubah-nama-en" class="block text-sm font-bold">Nama (Inggris)</label>
                        <input id="ubah-nama-en" v-model="formUbah.nama_en" type="text" maxlength="120" :class="kolom">
                    </div>
                    <div>
                        <label for="ubah-urutan" class="block text-sm font-bold">Urutan</label>
                        <input id="ubah-urutan" v-model.number="formUbah.urutan" type="number" min="0" max="999" :class="kolom">
                    </div>
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input v-model="formUbah.aktif" type="checkbox" class="h-4 w-4 border-2 border-ink">
                        Kategori aktif (tampil di situs)
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="disunting = null">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formUbah.processing">Simpan</button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
