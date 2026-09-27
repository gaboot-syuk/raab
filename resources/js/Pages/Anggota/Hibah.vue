<script setup lang="ts">
/*
 * Hibah & dukungan — sisi alumni/kader.
 *
 * Halaman ini HANYA membuat pengajuan. Ia tidak menyentuh kas maupun stok:
 * penerimaan dan pencatatannya tetap di tangan Bendahara. Dengan begitu tidak
 * ada uang yang bisa "masuk sendiri" tanpa persetujuan.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Hibah {
    id: number;
    nomor_hibah: string;
    judul: string;
    jenis: string;
    label_jenis: string;
    nilai: number;
    status: string;
    label_status: string;
    alasan_tolak: string | null;
    catatan_bendahara: string | null;
    tanggal_rencana: string | null;
    diterima_pada: string | null;
}

const props = defineProps<{
    daftar: Hibah[];
    pilihanJenis: Record<string, string>;
    totalSaya: number;
    catatan: string;
}>();

const form = useForm({
    jenis: 'dana',
    judul: '',
    deskripsi: '',
    estimasi_nilai: 0,
    tanggal_rencana: '',
    kontak: '',
    anonim: false,
});

function rupiah(nilai: number | null): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function ajukan(): void {
    form.post('/hibah', {
        preserveScroll: true,
        onSuccess: () => form.reset('judul', 'deskripsi', 'estimasi_nilai', 'tanggal_rencana'),
    });
}

function batalkan(h: Hibah): void {
    if (!confirm(`Batalkan pengajuan ${h.nomor_hibah}?`)) {
        return;
    }

    router.post(`/hibah/${h.id}/batalkan`, {}, { preserveScroll: true });
}
</script>

<template>
    <AnggotaLayout>
        <Head title="Hibah & Dukungan" />

        <div class="space-y-6">
            <PesanHasil />
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Hibah &amp; Dukungan</h1>
                <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
                <p v-if="props.totalSaya > 0" class="mt-2">
                    Total dukunganmu yang sudah diterima:
                    <span class="font-display text-lg">{{ rupiah(props.totalSaya) }}</span>
                </p>
            </div>

            <!-- Ajukan -->
            <form class="brutal bg-paper p-5" @submit.prevent="ajukan">
                <h2 class="font-display text-lg">Ajukan Dukungan</h2>

                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Jenis dukungan *</span>
                        <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Perkiraan nilai (Rp)</span>
                        <input v-model.number="form.estimasi_nilai" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.estimasi_nilai" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.estimasi_nilai }}</span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Judul *</span>
                        <input v-model="form.judul" type="text" placeholder="Dukungan konsumsi Mapaba" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.judul" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.judul }}</span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi</span>
                        <textarea v-model="form.deskripsi" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Rencana diserahkan</span>
                        <input v-model="form.tanggal_rencana" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.tanggal_rencana" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.tanggal_rencana }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Kontak yang bisa dihubungi</span>
                        <input v-model="form.kontak" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                    <input v-model="form.anonim" type="checkbox" class="h-4 w-4">
                    Sembunyikan nama saya (hanya Superadmin yang bisa melihat)
                </label>

                <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    Kirim Pengajuan
                </button>
            </form>

            <!-- Riwayat -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Pengajuan Saya</h2>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada pengajuan.</p>

                <ul v-else class="mt-3 space-y-3">
                    <li v-for="h in props.daftar" :key="h.id" class="border-2 border-ink p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ h.judul }}
                                    <span class="ml-2 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">{{ h.label_jenis }}</span>
                                    <span :class="['ml-1 border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', ['diterima', 'diverifikasi'].includes(h.status) ? 'bg-accent-100' : 'bg-paper-alt']">
                                        {{ h.label_status }}
                                    </span>
                                </p>
                                <p class="mt-1 text-sm">
                                    <span class="font-mono text-xs text-muted">{{ h.nomor_hibah }}</span>
                                    · {{ rupiah(h.nilai) }}
                                    <template v-if="h.tanggal_rencana"> · rencana {{ h.tanggal_rencana }}</template>
                                    <template v-if="h.diterima_pada"> · diterima {{ h.diterima_pada }}</template>
                                </p>
                                <p v-if="h.alasan_tolak" class="mt-1 text-xs font-bold text-accent-600">
                                    Belum dapat diterima: {{ h.alasan_tolak }}
                                </p>
                                <p v-if="h.catatan_bendahara" class="text-xs text-muted">Catatan Bendahara: {{ h.catatan_bendahara }}</p>
                            </div>

                            <button
                                v-if="!['diterima', 'diverifikasi', 'ditolak', 'dibatalkan'].includes(h.status)"
                                type="button"
                                class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                @click="batalkan(h)"
                            >Batalkan</button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AnggotaLayout>
</template>
