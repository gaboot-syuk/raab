<script setup lang="ts">
/*
 * Pengumuman di area anggota.
 *
 * Daftarnya sudah disaring server menurut hak akses penonton. Halaman ini tidak
 * perlu menyembunyikan apa pun — yang tidak berhak memang tidak pernah sampai
 * ke sini.
 */
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head } from '@inertiajs/vue3';

interface Pengumuman {
    id: number;
    slug: string;
    judul: string;
    isi: string;
    label_tipe: string;
    tipe: string;
    audiens_teks: string[];
    is_pinned: boolean;
    publish_at_teks: string | null;
    expire_at_teks: string | null;
    label_waktu: string;
}

const props = defineProps<{
    daftar: Pengumuman[];
    total: number;
    catatan: string;
}>();
</script>

<template>
    <Head title="Pengumuman" />

    <AnggotaLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div>
                <h1 class="font-display text-2xl">Pengumuman</h1>
                <p class="text-sm text-muted">{{ props.total }} pengumuman yang menjadi hakmu.</p>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-6">
                <span class="font-display text-lg">Belum ada pengumuman untukmu.</span>
                <span class="mt-1 block text-sm text-muted">Pengumuman baru akan muncul di sini begitu pengurus menerbitkannya.</span>
            </p>

            <ul v-else class="space-y-4">
                <li
                    v-for="p in props.daftar"
                    :key="p.id"
                    class="brutal bg-paper p-5"
                    :class="p.is_pinned ? 'border-l-8 border-l-accent-400' : ''"
                >
                    <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                        {{ p.label_tipe }} · {{ p.publish_at_teks }}
                        <span v-if="p.is_pinned" class="ml-1 border-2 border-ink bg-accent-400 px-1.5 py-0.5">Disematkan</span>
                    </p>

                    <h2 class="mt-1 font-display text-xl leading-tight">{{ p.judul }}</h2>

                    <div class="mt-3 space-y-3 text-sm leading-relaxed">
                        <p v-for="(paragraf, i) in p.isi.split(/\n\s*\n/)" :key="i" class="whitespace-pre-line">{{ paragraf }}</p>
                    </div>

                    <p class="mt-3 border-t-2 border-ink/10 pt-2 text-xs text-muted">
                        Untuk: <span class="font-bold">{{ p.audiens_teks.join(', ') }}</span>
                        <template v-if="p.expire_at_teks"> · Berlaku sampai {{ p.expire_at_teks }}</template>
                    </p>
                </li>
            </ul>
        </div>
    </AnggotaLayout>
</template>
