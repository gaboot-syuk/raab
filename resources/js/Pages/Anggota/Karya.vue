<script setup lang="ts">
/*
 * Daftar karya milik kader sendiri, beserta status peninjauannya.
 */
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Karya {
    id: number;
    tipe: string;
    label_tipe: string;
    status: string;
    label_status: string;
    judul: string | null;
    kategori: string | null;
    catatan_review: string | null;
    terbit_pada: string | null;
    diperbarui_pada: string | null;
    boleh_kirim: boolean;
    tautan_publik: string | null;
}

defineProps<{ daftar: Karya[] }>();

function kirim(karya: Karya) {
    if (!confirm(`Kirim "${karya.judul}" ke pengelola konten untuk ditinjau?`)) {
        return;
    }

    router.post(`/karya/${karya.id}/kirim`, {}, { preserveScroll: true });
}
</script>

<template>
    <AnggotaLayout>
        <Head title="Karya Saya" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Karya Saya</h1>
                    <p class="mt-1 text-sm text-muted">
                        Kirim tulisanmu — berita, opini, kajian, esai, atau sastra. Pengelola konten akan meninjaunya.
                    </p>
                </div>

                <Link href="/karya/baru" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper">
                    + Tulis Karya
                </Link>
            </div>

            <PesanHasil />

            <p v-if="!daftar.length" class="brutal bg-paper p-8 text-center text-sm text-muted">
                Kamu belum menulis karya apa pun. Mulai dengan menekan tombol <strong>Tulis Karya</strong>.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="k in daftar" :key="k.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="border-2 border-ink bg-accent-100 px-1.5 py-0.5 text-[11px] font-bold uppercase">{{ k.label_tipe }}</span>
                        <span
                            class="border-2 border-ink px-1.5 py-0.5 text-[11px] font-bold uppercase"
                            :class="k.status === 'terbit' ? 'bg-success text-paper' : k.status === 'menunggu_review' ? 'bg-accent-400 text-primary-800' : k.status === 'perlu_revisi' ? 'bg-accent-100' : 'bg-paper-alt text-muted'"
                        >{{ k.label_status }}</span>
                    </div>

                    <h2 class="mt-2 font-display text-lg">{{ k.judul ?? '(tanpa judul)' }}</h2>
                    <p class="mt-1 text-xs text-muted">
                        {{ k.kategori || 'Tanpa kategori' }} · Diperbarui {{ k.diperbarui_pada }}
                        <span v-if="k.terbit_pada"> · Terbit {{ k.terbit_pada }}</span>
                    </p>

                    <p v-if="k.catatan_review" class="brutal-sm mt-3 border-ink bg-accent-100 px-3 py-2 text-sm">
                        <span class="font-bold">Catatan pengelola:</span> {{ k.catatan_review }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-1 border-t-2 border-ink/15 pt-3">
                        <Link :href="`/karya/${k.id}`" class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold">Sunting</Link>

                        <button
                            v-if="k.boleh_kirim"
                            type="button"
                            class="brutal-sm brutal-hover bg-accent-400 px-2 py-1 text-xs font-bold text-primary-800"
                            @click="kirim(k)"
                        >Kirim untuk Review</button>

                        <a
                            v-if="k.tautan_publik"
                            :href="k.tautan_publik"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold"
                        >Lihat di Situs</a>
                    </div>

                    <p v-if="k.status === 'terbit'" class="mt-2 text-xs text-muted">
                        Karya yang sudah terbit tidak dapat disunting sendiri — hubungi pengelola konten bila perlu perbaikan.
                    </p>
                </li>
            </ul>
        </div>
    </AnggotaLayout>
</template>
