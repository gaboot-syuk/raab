<script setup lang="ts">
/*
 * Jabatan kepengurusan.
 *
 * `level` mengatur kedalaman bagan struktur dan `urutan` mengatur urutan dalam
 * satu tingkat — keduanya langsung memengaruhi halaman /struktur.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { useGulirKeForm } from '@/composables/useGulirKeForm';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Jabatan {
    id: number;
    nama: string;
    level: number;
    urutan: number;
    unit_id: number | null;
    unit: string | null;
    rangkap_diizinkan: boolean;
    aktif: boolean;
    jumlah_penugasan: number;
}

const props = defineProps<{
    daftar: Jabatan[];
    level: Record<string, string>;
    unit: { id: number; nama: string; label: string }[];
}>();

const sunting = ref<number | null>(null);
const { wadah, gulirKeForm } = useGulirKeForm();

const form = useForm({
    nama: '',
    level: 2,
    urutan: 0,
    unit_id: null as number | null,
    rangkap_diizinkan: false,
    aktif: true,
});

function mulaiTambah(): void {
    sunting.value = null;
    form.reset();
    form.clearErrors();
}

function mulaiSunting(jabatan: Jabatan): void {
    sunting.value = jabatan.id;
    form.clearErrors();
    form.nama = jabatan.nama;
    form.level = jabatan.level;
    form.urutan = jabatan.urutan;
    form.unit_id = jabatan.unit_id;
    form.rangkap_diizinkan = jabatan.rangkap_diizinkan;
    form.aktif = jabatan.aktif;

    gulirKeForm();
}

function simpan(): void {
    const opsi = { preserveScroll: true, onSuccess: () => mulaiTambah() };

    if (sunting.value) {
        form.put(`/panel/organisasi/jabatan/${sunting.value}`, opsi);
    } else {
        form.post('/panel/organisasi/jabatan', opsi);
    }
}

function hapus(jabatan: Jabatan): void {
    if (!confirm(`Hapus jabatan "${jabatan.nama}"?`)) {
        return;
    }

    router.delete(`/panel/organisasi/jabatan/${jabatan.id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Jabatan" />

        <PesanHasil />

        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Jabatan</h1>
                <p class="mt-1 text-sm text-muted">
                    Tingkat menentukan posisi pada bagan struktur, urutan menentukan urutan tampil dalam satu tingkat.
                </p>
            </div>

            <form ref="wadah" class="brutal bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">{{ sunting ? 'Sunting Jabatan' : 'Tambah Jabatan' }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block lg:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Nama jabatan *</span>
                        <input v-model="form.nama" type="text" placeholder="Ketua Rayon" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Tingkat *</span>
                        <select v-model.number="form.level" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in props.level" :key="nilai" :value="Number(nilai)">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Unit (opsional)</span>
                        <select v-model="form.unit_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— tanpa unit (jabatan rayon) —</option>
                            <option v-for="satuan in props.unit" :key="satuan.id" :value="satuan.id">{{ satuan.label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                        <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <div class="flex flex-col justify-end gap-2">
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input v-model="form.aktif" type="checkbox" class="h-4 w-4"> Aktif
                        </label>
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input v-model="form.rangkap_diizinkan" type="checkbox" class="h-4 w-4"> Boleh dirangkap
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex gap-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        {{ sunting ? 'Simpan Perubahan' : 'Tambah Jabatan' }}
                    </button>
                    <button v-if="sunting" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="mulaiTambah">Batal</button>
                </div>
            </form>

            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Daftar Jabatan</h2>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada jabatan.</p>

                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Jabatan</th>
                            <th class="py-2">Tingkat</th>
                            <th class="py-2">Unit</th>
                            <th class="py-2 text-right">Dipakai</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="jabatan in props.daftar" :key="jabatan.id" class="border-b-2 border-ink/10">
                            <td class="py-2 font-bold">
                                {{ jabatan.nama }}
                                <span v-if="!jabatan.aktif" class="ml-2 text-xs font-normal text-muted">(nonaktif)</span>
                            </td>
                            <td class="py-2">{{ jabatan.level }}</td>
                            <td class="py-2">{{ jabatan.unit ?? '—' }}</td>
                            <td class="py-2 text-right">{{ jabatan.jumlah_penugasan }}</td>
                            <td class="py-2">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold" @click="mulaiSunting(jabatan)">Sunting</button>
                                    <button type="button" class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold" @click="hapus(jabatan)">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </PanelLayout>
</template>
