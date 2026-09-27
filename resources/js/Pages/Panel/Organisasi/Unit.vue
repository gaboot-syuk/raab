<script setup lang="ts">
/*
 * Biro (8) & Lembaga Semi Otonom (5).
 *
 * Unit yang masih dipakai tidak dapat dihapus — server menolak dengan alasan
 * yang jelas. Nonaktifkan saja agar riwayat anggota tetap utuh.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Unit {
    id: number;
    jenis: string;
    label_jenis: string;
    nama: string;
    slug: string | null;
    singkatan: string | null;
    deskripsi: Record<string, string> | null;
    warna: string | null;
    urutan: number;
    aktif: boolean;
    jumlah_jabatan: number;
    jumlah_anggota: number;
    jumlah_galeri: number;
    tautan_publik: string | null;
}

const props = defineProps<{ daftar: Unit[]; jenis: Record<string, string> }>();

const sunting = ref<number | null>(null);
const saringJenis = ref('');

const form = useForm({
    jenis: 'lso',
    nama: '',
    singkatan: '',
    deskripsi: { id: '', en: '' } as Record<string, string>,
    warna: '',
    urutan: 0,
    aktif: true,
});

function mulaiTambah(): void {
    sunting.value = null;
    form.reset();
    form.clearErrors();
}

function mulaiSunting(unit: Unit): void {
    sunting.value = unit.id;
    form.clearErrors();
    form.jenis = unit.jenis;
    form.nama = unit.nama;
    form.singkatan = unit.singkatan ?? '';
    form.deskripsi = { id: unit.deskripsi?.id ?? '', en: unit.deskripsi?.en ?? '' };
    form.warna = unit.warna ?? '';
    form.urutan = unit.urutan;
    form.aktif = unit.aktif;
}

function simpan(): void {
    const opsi = { preserveScroll: true, onSuccess: () => mulaiTambah() };

    if (sunting.value) {
        form.put(`/panel/organisasi/unit/${sunting.value}`, opsi);
    } else {
        form.post('/panel/organisasi/unit', opsi);
    }
}

function hapus(unit: Unit): void {
    if (!confirm(`Hapus unit ${unit.nama}?`)) {
        return;
    }

    router.delete(`/panel/organisasi/unit/${unit.id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Biro & LSO" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Biro & Lembaga Semi Otonom</h1>
                <p class="mt-1 text-sm text-muted">
                    Biro adalah bagian struktural rayon; LSO adalah lembaga yang menampung minat dan bakat kader.
                    Halaman publik LSO dibuka lewat tautan di daftar.
                </p>
            </div>

            <form class="brutal bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">{{ sunting ? 'Sunting Unit' : 'Tambah Unit' }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                        <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in props.jenis" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Nama unit *</span>
                        <input v-model="form.nama" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Singkatan</span>
                        <input v-model="form.singkatan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Warna (token)</span>
                        <input v-model="form.warna" type="text" placeholder="mis. accent-400" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                        <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block sm:col-span-2 lg:col-span-1">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi (Indonesia)</span>
                        <textarea v-model="form.deskripsi.id" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi (Inggris)</span>
                        <textarea v-model="form.deskripsi.en" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                    <input v-model="form.aktif" type="checkbox" class="h-4 w-4"> Aktif (tampil di situs)
                </label>

                <div class="mt-4 flex gap-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        {{ sunting ? 'Simpan Perubahan' : 'Tambah Unit' }}
                    </button>
                    <button v-if="sunting" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="mulaiTambah">Batal</button>
                </div>
            </form>

            <section class="brutal bg-paper p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display text-lg">Daftar Unit</h2>

                    <select v-model="saringJenis" class="brutal-sm bg-paper-alt px-3 py-1.5 text-sm font-bold">
                        <option value="">Semua jenis</option>
                        <option v-for="(label, nilai) in props.jenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </div>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada unit.</p>

                <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                    <li
                        v-for="unit in props.daftar.filter((u) => saringJenis === '' || u.jenis === saringJenis)"
                        :key="unit.id"
                        class="flex flex-wrap items-center gap-3 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                {{ unit.nama }}
                                <span class="brutal-sm ml-2 bg-paper-alt px-2 py-0.5 text-[10px] uppercase">{{ unit.label_jenis }}</span>
                                <span v-if="!unit.aktif" class="ml-2 text-xs font-normal text-muted">(nonaktif)</span>
                            </p>
                            <p class="text-xs text-muted">
                                {{ unit.jumlah_jabatan }} jabatan · {{ unit.jumlah_anggota }} anggota · {{ unit.jumlah_galeri }} album
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a v-if="unit.tautan_publik" :href="unit.tautan_publik" target="_blank" rel="noopener noreferrer" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">
                                Halaman Publik
                            </a>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(unit)">Sunting</button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(unit)">Hapus</button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
