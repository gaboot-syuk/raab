<script setup lang="ts">
/*
 * Periode kepengurusan — HANYA Superadmin.
 *
 * Menghapus periode tidak pernah menyentuh periode lain; yang dijaga di sini
 * adalah agar periode yang masih punya penugasan tidak bisa dihapus, sehingga
 * riwayat kepengurusan tidak hilang tanpa sengaja.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { useGulirKeForm } from '@/composables/useGulirKeForm';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Periode {
    id: number;
    nama: string;
    tahun_mulai: number;
    tahun_selesai: number;
    mulai: string | null;
    selesai: string | null;
    aktif: boolean;
    urutan: number;
    jumlah_penugasan: number;
}

const props = defineProps<{ daftar: Periode[] }>();

const sunting = ref<number | null>(null);
const { wadah, gulirKeForm } = useGulirKeForm();

const form = useForm({
    nama: '',
    tahun_mulai: new Date().getFullYear(),
    tahun_selesai: new Date().getFullYear() + 1,
    mulai: '',
    selesai: '',
    urutan: 0,
    aktif: false,
});

function mulaiTambah(): void {
    sunting.value = null;
    form.reset();
    form.clearErrors();
    form.tahun_mulai = new Date().getFullYear();
    form.tahun_selesai = new Date().getFullYear() + 1;
}

function mulaiSunting(periode: Periode): void {
    sunting.value = periode.id;
    form.clearErrors();
    form.nama = periode.nama;
    form.tahun_mulai = periode.tahun_mulai;
    form.tahun_selesai = periode.tahun_selesai;
    form.mulai = periode.mulai ?? '';
    form.selesai = periode.selesai ?? '';
    form.urutan = periode.urutan;
    form.aktif = periode.aktif;

    gulirKeForm();
}

function simpan(): void {
    const opsi = { preserveScroll: true, onSuccess: () => mulaiTambah() };

    if (sunting.value) {
        form.put(`/panel/organisasi/periode/${sunting.value}`, opsi);
    } else {
        form.post('/panel/organisasi/periode', opsi);
    }
}

function aktifkan(periode: Periode): void {
    if (!confirm(`Tandai ${periode.nama} sebagai periode berjalan? Periode lama tidak dihapus.`)) {
        return;
    }

    router.post(`/panel/organisasi/periode/${periode.id}/aktifkan`, {}, { preserveScroll: true });
}

function hapus(periode: Periode): void {
    if (!confirm(`Hapus periode ${periode.nama}?`)) {
        return;
    }

    router.delete(`/panel/organisasi/periode/${periode.id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Periode Kepengurusan" />

        <PesanHasil />

        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Periode Kepengurusan</h1>
                <p class="mt-1 text-sm text-muted">
                    Kerangka seluruh data kepengurusan. Mengganti periode berjalan tidak menghapus data periode lama.
                    Hanya Superadmin yang dapat mengubah daftar ini.
                </p>
            </div>

            <!-- Formulir -->
            <form ref="wadah" class="brutal bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">
                    {{ sunting ? 'Sunting Periode' : 'Tambah Periode' }}
                </h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Nama (opsional)</span>
                        <input v-model="form.nama" type="text" placeholder="2026/2027" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Tahun mulai *</span>
                        <input v-model.number="form.tahun_mulai" type="number" min="2000" max="2100" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.tahun_mulai" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.tahun_mulai }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Tahun selesai *</span>
                        <input v-model.number="form.tahun_selesai" type="number" min="2000" max="2100" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.tahun_selesai" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.tahun_selesai }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Tanggal mulai</span>
                        <input v-model="form.mulai" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Tanggal selesai</span>
                        <input v-model="form.selesai" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.selesai" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.selesai }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                        <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                    <input v-model="form.aktif" type="checkbox" class="h-4 w-4">
                    Jadikan periode berjalan
                </label>

                <div class="mt-4 flex gap-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        {{ sunting ? 'Simpan Perubahan' : 'Tambah Periode' }}
                    </button>
                    <button v-if="sunting" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="mulaiTambah">
                        Batal
                    </button>
                </div>
            </form>

            <!-- Daftar -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Daftar Periode</h2>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada periode.</p>

                <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="periode in props.daftar" :key="periode.id" class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                {{ periode.nama }}
                                <span v-if="periode.aktif" class="brutal-sm ml-2 bg-accent-400 px-2 py-0.5 text-[10px] uppercase text-primary-800">
                                    Berjalan
                                </span>
                            </p>
                            <p class="text-xs text-muted">
                                {{ periode.tahun_mulai }}–{{ periode.tahun_selesai }} ·
                                {{ periode.jumlah_penugasan }} penugasan
                                <template v-if="periode.mulai"> · {{ periode.mulai }} s.d. {{ periode.selesai ?? '…' }}</template>
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <button
                                v-if="!periode.aktif"
                                type="button"
                                class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                @click="aktifkan(periode)"
                            >Jadikan Berjalan</button>

                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(periode)">
                                Sunting
                            </button>

                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(periode)">
                                Hapus
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
