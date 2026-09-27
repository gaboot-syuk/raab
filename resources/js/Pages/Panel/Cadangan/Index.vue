<script setup lang="ts">
/*
 * Panel cadangan basis data — khusus Superadmin.
 *
 * Halaman ini hanya MEMBUAT dan MENGUNDUH. Tidak ada tombol "pulihkan", dan
 * itu keputusan yang disengaja: memulihkan berarti menghapus seluruh data yang
 * ada sekarang. Tindakan sebesar itu tidak boleh bisa terjadi karena salah
 * klik; ia dilakukan lewat perintah artisan yang menuntut nama berkas diketik
 * ulang.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

interface Berkas {
    nama: string;
    ukuran: number;
    ukuran_teks: string;
    dibuat: string;
}

const props = defineProps<{
    daftar: Berkas[];
    pengandar: string;
    simpanBerkas: number;
    catatan: string;
}>();

const form = useForm({});

function buat(): void {
    form.post('/panel/cadangan', { preserveScroll: true });
}

function hapus(nama: string): void {
    if (!confirm(`Hapus cadangan ${nama}? Berkas ini tidak bisa dikembalikan.`)) {
        return;
    }

    router.delete(`/panel/cadangan/${encodeURIComponent(nama)}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Cadangan Basis Data" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Cadangan Basis Data</h1>
                    <p class="text-sm text-muted">
                        Basis data: <span class="font-mono">{{ props.pengandar }}</span>
                        · menyimpan {{ props.simpanBerkas }} berkas terbaru
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="form.processing"
                    class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                    @click="buat"
                >Buat Cadangan Sekarang</button>
            </div>

            <p class="brutal bg-accent-400 p-4 text-xs leading-relaxed font-bold">{{ props.catatan }}</p>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-6">
                <span class="font-display text-lg">Belum ada cadangan.</span>
                <span class="mt-1 block text-sm text-muted">
                    Cadangan dibuat otomatis setiap dini hari, dan bisa dibuat kapan saja dengan tombol di atas —
                    terutama sebelum impor data atau perubahan besar.
                </span>
            </p>

            <ul v-else class="space-y-3">
                <li v-for="b in props.daftar" :key="b.nama" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-sm break-all">{{ b.nama }}</p>
                            <p class="mt-1 text-xs text-muted">{{ b.dibuat }} · {{ b.ukuran_teks }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                :href="`/panel/cadangan/${encodeURIComponent(b.nama)}/unduh`"
                                class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                            >Unduh</a>

                            <button
                                type="button"
                                class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                @click="hapus(b.nama)"
                            >Hapus</button>
                        </div>
                    </div>
                </li>
            </ul>

            <!-- ===== Cara memulihkan ===== -->
            <section class="brutal bg-paper-alt p-5">
                <h2 class="font-display text-lg">Cara Memulihkan</h2>
                <p class="mt-1 text-xs text-muted">
                    Pemulihan tidak disediakan lewat halaman ini karena ia MENGHAPUS seluruh data yang ada sekarang.
                    Ia dijalankan dari terminal server oleh Superadmin, dan keadaan sekarang dicadangkan lebih dulu
                    secara otomatis sebelum apa pun diubah.
                </p>

                <ol class="mt-3 space-y-2 text-sm">
                    <li><span class="font-bold">1.</span> Unduh berkas cadangan di atas, lalu unggah ke server bila perlu.</li>
                    <li><span class="font-bold">2.</span> Jalankan <code class="border-2 border-ink bg-paper px-1">php artisan cadangan:daftar</code> untuk melihat berkas yang ada di server.</li>
                    <li><span class="font-bold">3.</span> Jalankan <code class="border-2 border-ink bg-paper px-1">php artisan cadangan:pulihkan &lt;nama-berkas&gt;</code> lalu ketik ulang nama berkasnya.</li>
                    <li><span class="font-bold">4.</span> Jalankan <code class="border-2 border-ink bg-paper px-1">php artisan cache:clear</code> — pengaturan situs disimpan di cache.</li>
                </ol>
            </section>
        </div>
    </PanelLayout>
</template>
