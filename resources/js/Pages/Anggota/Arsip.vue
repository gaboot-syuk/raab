<script setup lang="ts">
/*
 * Arsip dokumen di area anggota.
 *
 * Halamannya sama untuk kader maupun alumni; yang berbeda hanya dokumen mana
 * yang muncul — dan itu ditentukan server, bukan oleh penyembunyian di sini.
 * Hak akses penonton ditampilkan apa adanya supaya jelas MENGAPA sebuah
 * dokumen tidak muncul.
 */
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Dokumen {
    id: number;
    slug: string;
    judul: string;
    keterangan: string | null;
    label_kategori: string;
    nomor: string | null;
    tanggal_teks: string | null;
    audiens_teks: string[];
    nama_berkas: string | null;
    ukuran: string | null;
    punya_berkas: boolean;
    tautan_unduh: string;
    pengunggah: string | null;
}

const props = defineProps<{
    daftar: Dokumen[];
    kategori: string;
    pilihanKategori: Record<string, string>;
    total: number;
    audiensSaya: string[];
    catatan: string;
}>();

const kategoriSaring = ref(props.kategori);

const labelAudiens: Record<string, string> = {
    publik: 'Umum',
    kader: 'Kader Aktif',
    alumni: 'Alumni',
    pengurus: 'Pengurus',
};

function saring(): void {
    router.get('/arsip-internal', { kategori: kategoriSaring.value }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Arsip Dokumen" />

    <AnggotaLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div>
                <h1 class="font-display text-2xl">Arsip Dokumen</h1>
                <p class="text-sm text-muted">{{ props.total }} dokumen yang boleh kamu buka.</p>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

            <div class="brutal bg-paper p-4">
                <p class="text-[10px] font-bold uppercase text-muted">Hak aksesmu</p>
                <p class="mt-1 flex flex-wrap gap-1">
                    <span
                        v-for="a in props.audiensSaya"
                        :key="a"
                        class="border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase"
                    >{{ labelAudiens[a] ?? a }}</span>
                </p>
            </div>

            <label class="block max-w-xs">
                <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                <select v-model="kategoriSaring" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="saring">
                    <option value="">Semua kategori</option>
                    <option v-for="(label, kunci) in props.pilihanKategori" :key="kunci" :value="kunci">{{ label }}</option>
                </select>
            </label>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-6">
                <span class="font-display text-lg">Belum ada dokumen untukmu.</span>
                <span class="mt-1 block text-sm text-muted">Dokumen yang dibuka untuk hak aksesmu akan muncul di sini.</span>
            </p>

            <ul v-else class="space-y-3">
                <li v-for="d in props.daftar" :key="d.id" class="brutal bg-paper p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                        {{ d.label_kategori }}
                        <template v-if="d.nomor"> · {{ d.nomor }}</template>
                        <template v-if="d.tanggal_teks"> · {{ d.tanggal_teks }}</template>
                    </p>

                    <h2 class="mt-1 font-display text-lg leading-tight">{{ d.judul }}</h2>

                    <p v-if="d.keterangan" class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ d.keterangan }}</p>

                    <p class="mt-2 text-xs text-muted">
                        <template v-if="d.punya_berkas">
                            {{ d.nama_berkas }}
                            <template v-if="d.ukuran"> ({{ d.ukuran }})</template>
                        </template>
                        <template v-else>Belum ada berkas yang dilampirkan.</template>
                        <template v-if="d.pengunggah"> · Diunggah {{ d.pengunggah }}</template>
                    </p>

                    <p class="mt-2 text-xs">
                        <span class="font-bold uppercase text-[10px]">Terbuka untuk:</span>
                        <span
                            v-for="a in d.audiens_teks"
                            :key="a"
                            class="ml-1 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase"
                        >{{ a }}</span>
                    </p>

                    <a
                        v-if="d.punya_berkas"
                        :href="d.tautan_unduh"
                        class="brutal-sm brutal-hover mt-3 inline-block bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                    >Unduh</a>
                </li>
            </ul>
        </div>
    </AnggotaLayout>
</template>
