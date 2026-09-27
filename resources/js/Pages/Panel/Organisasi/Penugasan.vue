<script setup lang="ts">
/*
 * Penyusunan pengurus per periode.
 *
 * Pengurus boleh BUKAN anggota terdaftar: cukup isi nama manual (dosen
 * pembina, tokoh, kader yang datanya belum masuk). Salah satu dari keduanya
 * wajib terisi — server menegakkannya juga, bukan hanya di layar ini.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Penugasan {
    id: number;
    position_id: number;
    jabatan: string | null;
    level: number | null;
    unit: string | null;
    member_id: number | null;
    nama_manual: string | null;
    nama: string;
    nomor_anggota: string | null;
    keterangan: string | null;
    urutan: number;
    aktif: boolean;
}

const props = defineProps<{
    periode: { id: number; nama: string; aktif: boolean } | null;
    daftarPeriode: { id: number; nama: string; aktif: boolean }[];
    penugasan: Penugasan[];
    daftarJabatan: { id: number; nama: string; label: string }[];
    pilihanAnggota: { id: number; nama: string; label: string }[];
}>();

const sunting = ref<number | null>(null);

const form = useForm({
    period_id: props.periode?.id ?? null,
    position_id: null as number | null,
    member_id: null as number | null,
    nama_manual: '',
    keterangan: '',
    urutan: 0,
    aktif: true,
});

function mulaiTambah(): void {
    sunting.value = null;
    form.reset();
    form.clearErrors();
    form.period_id = props.periode?.id ?? null;
}

function mulaiSunting(baris: Penugasan): void {
    sunting.value = baris.id;
    form.clearErrors();
    form.period_id = props.periode?.id ?? null;
    form.position_id = baris.position_id;
    form.member_id = baris.member_id;
    form.nama_manual = baris.nama_manual ?? '';
    form.keterangan = baris.keterangan ?? '';
    form.urutan = baris.urutan;
    form.aktif = baris.aktif;
}

function simpan(): void {
    if (!form.period_id) {
        return;
    }

    const opsi = { preserveScroll: true, onSuccess: () => mulaiTambah() };

    if (sunting.value) {
        form.put(`/panel/organisasi/penugasan/${sunting.value}`, opsi);
    } else {
        form.post('/panel/organisasi/penugasan', opsi);
    }
}

function hapus(baris: Penugasan): void {
    if (!confirm(`Hapus penugasan ${baris.nama} (${baris.jabatan})?`)) {
        return;
    }

    router.delete(`/panel/organisasi/penugasan/${baris.id}`, { preserveScroll: true });
}

function gantiPeriode(id: number): void {
    router.get('/panel/organisasi/penugasan', { periode: id }, { preserveScroll: false });
}
</script>

<template>
    <PanelLayout>
        <Head title="Penugasan Pengurus" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Penugasan Pengurus</h1>
                    <p class="mt-1 text-sm text-muted">
                        Susun pengurus untuk satu periode. Periode lain tidak tersentuh.
                    </p>
                </div>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Periode</span>
                    <select
                        :value="props.periode?.id"
                        class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm font-bold"
                        @change="gantiPeriode(Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option v-for="p in props.daftarPeriode" :key="p.id" :value="p.id">
                            {{ p.nama }}{{ p.aktif ? ' (berjalan)' : '' }}
                        </option>
                    </select>
                </label>
            </div>

            <p v-if="!props.periode" class="brutal bg-paper-alt p-6 text-sm text-muted">
                Belum ada periode kepengurusan. Buat periodenya lebih dulu di menu
                <Link href="/panel/organisasi/periode" class="font-bold underline">Periode</Link>.
            </p>

            <template v-else>
                <form class="brutal bg-paper p-5" @submit.prevent="simpan">
                    <h2 class="font-display text-lg">
                        {{ sunting ? 'Sunting Penugasan' : 'Tambah Pengurus ke ' + props.periode.nama }}
                    </h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block lg:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Jabatan *</span>
                            <select v-model="form.position_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— pilih jabatan —</option>
                                <option v-for="jabatan in props.daftarJabatan" :key="jabatan.id" :value="jabatan.id">{{ jabatan.label }}</option>
                            </select>
                            <span v-if="form.errors.position_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.position_id }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                            <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block lg:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Anggota terdaftar</span>
                            <select v-model="form.member_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— bukan anggota terdaftar —</option>
                                <option v-for="anggota in props.pilihanAnggota" :key="anggota.id" :value="anggota.id">{{ anggota.label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Nama manual</span>
                            <input v-model="form.nama_manual" type="text" placeholder="mis. Dr. Ahmad, M.Pd." class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.nama_manual" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama_manual }}</span>
                        </label>

                        <label class="block lg:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                            <input v-model="form.keterangan" type="text" placeholder="mis. Plt. hingga Munas" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm font-bold">
                                <input v-model="form.aktif" type="checkbox" class="h-4 w-4"> Tampilkan di bagan
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-3">
                        <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                            {{ sunting ? 'Simpan Perubahan' : 'Tambah Pengurus' }}
                        </button>
                        <button v-if="sunting" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="mulaiTambah">Batal</button>
                    </div>
                </form>

                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">
                        Susunan {{ props.periode.nama }}
                        <span class="ml-2 text-sm font-normal text-muted">{{ props.penugasan.length }} orang</span>
                    </h2>

                    <p v-if="!props.penugasan.length" class="mt-3 text-sm text-muted">
                        Belum ada pengurus pada periode ini.
                    </p>

                    <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="baris in props.penugasan" :key="baris.id" class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ baris.nama }}
                                    <span v-if="!baris.aktif" class="ml-2 text-xs font-normal text-muted">(tidak tampil)</span>
                                </p>
                                <p class="text-xs text-muted">
                                    {{ baris.jabatan }}<template v-if="baris.unit"> · {{ baris.unit }}</template>
                                    <template v-if="baris.nomor_anggota"> · {{ baris.nomor_anggota }}</template>
                                    <template v-if="baris.keterangan"> · {{ baris.keterangan }}</template>
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(baris)">Sunting</button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(baris)">Hapus</button>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </PanelLayout>
</template>
