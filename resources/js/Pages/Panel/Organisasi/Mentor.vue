<script setup lang="ts">
/*
 * Daftar alumni yang bersedia menjadi mentor/pemateri.
 *
 * Kontak di sini HANYA untuk pengurus; di direktori publik kontak muncul bila
 * alumni mengizinkannya. Karena itu halaman ini dijaga izin `mentors.view`.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Mentor {
    id: number;
    nama: string | null;
    slug: string | null;
    nomor_anggota: string | null;
    unit: string | null;
    tahun_lulus: number | null;
    instansi: string | null;
    jabatan: string | null;
    bidang: string | null;
    kota_domisili: string | null;
    topik_mentor: string | null;
    telepon: string | null;
    email: string | null;
}

const props = defineProps<{
    daftar: Mentor[];
    saring: { cari: string; topik: string };
    jumlah: number;
}>();

const cari = ref(props.saring.cari);
const topik = ref(props.saring.topik);

function saringkan(): void {
    router.get('/panel/organisasi/mentor', { cari: cari.value, topik: topik.value }, { preserveState: true });
}

function bersihkan(): void {
    cari.value = '';
    topik.value = '';
    saringkan();
}
</script>

<template>
    <PanelLayout>
        <Head title="Daftar Mentor" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Alumni Siap Jadi Mentor</h1>
                <p class="mt-1 text-sm text-muted">
                    Daftar ini diisi sendiri oleh alumni dari halaman profil mereka. Hanya yang menandai kesediaannya yang muncul.
                </p>
            </div>

            <form class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-4" @submit.prevent="saringkan">
                <label class="block sm:col-span-2">
                    <span class="text-xs font-bold uppercase text-muted">Cari nama</span>
                    <input v-model="cari" type="search" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Topik / bidang</span>
                    <input v-model="topik" type="search" placeholder="mis. jurnalistik" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Saring</button>
                    <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="bersihkan">Ulang</button>
                </div>
            </form>

            <p class="text-sm text-muted">
                Ditemukan <span class="font-bold text-ink">{{ props.jumlah }}</span> alumni bersedia menjadi mentor.
            </p>

            <p v-if="!props.daftar.length" class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                Belum ada alumni yang menandai kesediaannya menjadi mentor.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="mentor in props.daftar" :key="mentor.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-display text-lg">{{ mentor.nama }}</p>
                        <p v-if="mentor.tahun_lulus" class="text-xs font-bold text-muted">Lulus {{ mentor.tahun_lulus }}</p>
                    </div>

                    <p class="mt-1 text-sm text-muted">
                        <template v-if="mentor.instansi">{{ mentor.instansi }}</template>
                        <template v-if="mentor.jabatan"> — {{ mentor.jabatan }}</template>
                        <template v-if="mentor.bidang"> · {{ mentor.bidang }}</template>
                        <template v-if="mentor.kota_domisili"> · {{ mentor.kota_domisili }}</template>
                    </p>

                    <p v-if="mentor.topik_mentor" class="mt-2 text-sm">
                        <span class="font-bold">Topik: </span>{{ mentor.topik_mentor }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                        <a v-if="mentor.telepon" :href="`tel:${mentor.telepon}`" class="brutal-sm bg-paper-alt px-3 py-1.5">{{ mentor.telepon }}</a>
                        <a v-if="mentor.email" :href="`mailto:${mentor.email}`" class="brutal-sm bg-paper-alt px-3 py-1.5">{{ mentor.email }}</a>
                        <span v-if="mentor.nomor_anggota" class="brutal-sm bg-paper-alt px-3 py-1.5">{{ mentor.nomor_anggota }}</span>
                        <span v-if="mentor.unit" class="brutal-sm bg-paper-alt px-3 py-1.5">{{ mentor.unit }}</span>
                    </div>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
