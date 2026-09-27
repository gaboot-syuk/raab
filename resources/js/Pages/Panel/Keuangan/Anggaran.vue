<script setup lang="ts">
/*
 * Anggaran (RKAT) & realisasi.
 *
 * REALISASI TIDAK DIKETIK MANUAL. Ia dijumlahkan dari transaksi kas yang sudah
 * dikonfirmasi dan ditautkan ke anggaran ini. Dua angka yang harus selalu sama
 * tidak perlu disimpan dua kali.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Anggaran {
    id: number;
    nama: string;
    jenis: string;
    label_jenis: string;
    periode: string | null;
    kegiatan: string | null;
    kategori: string | null;
    rencana: number;
    realisasi: number;
    sisa: number;
    persen: number;
    melebihi: boolean;
    aktif: boolean;
    jumlah_transaksi: number;
}

const props = defineProps<{
    daftar: Anggaran[];
    ringkasan: Record<string, number>;
    periodeTerpilih: number | null;
    pilihanPeriode: { id: number; nama: string }[];
    pilihanJenis: Record<string, string>;
    pilihanKegiatan: { id: number; nama: string }[];
    pilihanKategori: { id: number; nama: string; jenis: string }[];
    catatan: string;
}>();

const sunting = ref<number | null>(null);

const form = useForm({
    nama: '',
    jenis: 'keluar',
    jumlah_direncanakan: 0,
    period_id: props.periodeTerpilih,
    event_id: null as number | null,
    category_id: null as number | null,
    keterangan: '',
});

const formUbah = useForm({
    nama: '',
    jenis: 'keluar',
    jumlah_direncanakan: 0,
    period_id: props.periodeTerpilih,
    event_id: null as number | null,
    category_id: null as number | null,
    keterangan: '',
    aktif: true,
});

function rupiah(nilai: number): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function simpan(): void {
    form.post('/panel/keuangan/anggaran', {
        preserveScroll: true,
        onSuccess: () => form.reset('nama', 'jumlah_direncanakan', 'keterangan', 'category_id', 'event_id'),
    });
}

function mulaiSunting(a: Anggaran): void {
    sunting.value = sunting.value === a.id ? null : a.id;
    formUbah.clearErrors();
    formUbah.nama = a.nama;
    formUbah.jenis = a.jenis;
    formUbah.jumlah_direncanakan = a.rencana;
    formUbah.period_id = props.periodeTerpilih;
    formUbah.event_id = null;
    formUbah.category_id = null;
    formUbah.keterangan = '';
    formUbah.aktif = a.aktif;
}

function perbarui(a: Anggaran): void {
    formUbah.put(`/panel/keuangan/anggaran/${a.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            sunting.value = null;
            formUbah.reset();
        },
    });
}

function nonaktifkan(a: Anggaran): void {
    router.put(`/panel/keuangan/anggaran/${a.id}`, {
        nama: a.nama,
        jenis: a.jenis,
        jumlah_direncanakan: a.rencana,
        period_id: props.periodeTerpilih,
        event_id: null,
        category_id: null,
        aktif: !a.aktif,
    }, { preserveScroll: true });
}

function hapus(a: Anggaran): void {
    if (!confirm(`Hapus anggaran ${a.nama}?`)) {
        return;
    }

    router.delete(`/panel/keuangan/anggaran/${a.id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Anggaran" />

        <div class="mx-auto max-w-6xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Anggaran (RKAT)</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
                </div>
                <a href="/panel/keuangan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Ikhtisar</a>
            </div>

            <ul class="grid gap-3 sm:grid-cols-4">
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Rencana Penerimaan</p>
                    <p class="font-display text-xl">{{ rupiah(props.ringkasan.rencana_masuk) }}</p>
                    <p class="text-xs text-muted">realisasi {{ rupiah(props.ringkasan.realisasi_masuk) }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Rencana Pengeluaran</p>
                    <p class="font-display text-xl">{{ rupiah(props.ringkasan.rencana_keluar) }}</p>
                    <p class="text-xs text-muted">realisasi {{ rupiah(props.ringkasan.realisasi_keluar) }}</p>
                </li>
            </ul>

            <form class="brutal grid gap-4 bg-paper p-5 sm:grid-cols-3" @submit.prevent="simpan">
                <h2 class="font-display text-lg sm:col-span-3">Tambah Anggaran</h2>

                <label class="block sm:col-span-2">
                    <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                    <input v-model="form.nama" type="text" placeholder="Konsumsi Mapaba 2026" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                    <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jumlah direncanakan (Rp) *</span>
                    <input v-model.number="form.jumlah_direncanakan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.jumlah_direncanakan" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.jumlah_direncanakan }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Periode kepengurusan</span>
                    <select v-model="form.period_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— tanpa periode —</option>
                        <option v-for="p in props.pilihanPeriode" :key="p.id" :value="p.id">{{ p.nama }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Kegiatan</span>
                    <select v-model="form.event_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— tanpa kegiatan —</option>
                        <option v-for="e in props.pilihanKegiatan" :key="e.id" :value="e.id">{{ e.nama }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                    <select v-model="form.category_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— tanpa kategori —</option>
                        <option v-for="k in props.pilihanKategori" :key="k.id" :value="k.id">{{ k.nama }}</option>
                    </select>
                </label>

                <label class="block sm:col-span-3">
                    <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                    <input v-model="form.keterangan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <div class="flex items-end sm:col-span-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Tambah Anggaran
                    </button>
                </div>
            </form>

            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Anggaran &amp; Realisasi</h2>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada anggaran untuk periode ini.</p>

                <ul v-else class="mt-3 space-y-3">
                    <li v-for="a in props.daftar" :key="a.id" class="border-2 border-ink p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ a.nama }}
                                    <span class="ml-2 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">{{ a.label_jenis }}</span>
                                    <span v-if="a.melebihi" class="ml-1 border-2 border-ink bg-accent-100 px-2 py-0.5 text-[10px] font-bold uppercase">Lewat rencana</span>
                                    <span v-if="!a.aktif" class="ml-1 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">Nonaktif</span>
                                </p>
                                <p class="text-xs text-muted">
                                    <template v-if="a.periode">{{ a.periode }}</template>
                                    <template v-if="a.kegiatan"> · {{ a.kegiatan }}</template>
                                    <template v-if="a.kategori"> · {{ a.kategori }}</template>
                                    · {{ a.jumlah_transaksi }} transaksi tertaut
                                </p>

                                <p class="mt-2">
                                    Rencana <span class="font-display">{{ rupiah(a.rencana) }}</span>
                                    · Realisasi <span class="font-display">{{ rupiah(a.realisasi) }}</span>
                                    · Sisa <span class="font-display" :class="a.sisa < 0 ? 'text-accent-600' : ''">{{ rupiah(a.sisa) }}</span>
                                </p>

                                <div class="mt-1 h-3 w-full border-2 border-ink bg-paper-alt">
                                    <div class="h-full bg-accent-400" :style="{ width: Math.min(100, a.persen) + '%' }"></div>
                                </div>
                                <p class="text-xs text-muted">{{ a.persen }}% terpakai</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="sunting = sunting === a.id ? null : a.id">
                                    {{ sunting === a.id ? 'Tutup' : 'Sunting' }}
                                </button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="nonaktifkan(a)">
                                    {{ a.aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                                <button v-if="!a.jumlah_transaksi" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(a)">
                                    Hapus
                                </button>
                            </div>
                        </div>

                        <form v-if="sunting === a.id" class="mt-3 grid gap-3 border-t-2 border-ink/10 pt-3 sm:grid-cols-3" @submit.prevent="perbarui(a)">
                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                                <input v-model="formUbah.nama" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <span v-if="formUbah.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ formUbah.errors.nama }}</span>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                                <select v-model="formUbah.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Jumlah direncanakan (Rp) *</span>
                                <input v-model.number="formUbah.jumlah_direncanakan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <span v-if="formUbah.errors.jumlah_direncanakan" class="mt-1 block text-xs font-bold text-accent-600">{{ formUbah.errors.jumlah_direncanakan }}</span>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Periode kepengurusan</span>
                                <select v-model="formUbah.period_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option :value="null">— tanpa periode —</option>
                                    <option v-for="p in props.pilihanPeriode" :key="p.id" :value="p.id">{{ p.nama }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Kegiatan</span>
                                <select v-model="formUbah.event_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option :value="null">— tanpa kegiatan —</option>
                                    <option v-for="e in props.pilihanKegiatan" :key="e.id" :value="e.id">{{ e.nama }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                                <select v-model="formUbah.category_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option :value="null">— tanpa kategori —</option>
                                    <option v-for="k in props.pilihanKategori" :key="k.id" :value="k.id">{{ k.nama }}</option>
                                </select>
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                                <input v-model="formUbah.keterangan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>

                            <label class="flex items-center gap-2 self-end text-sm font-bold">
                                <input v-model="formUbah.aktif" type="checkbox" class="h-4 w-4"> Aktif
                            </label>

                            <div class="flex items-end gap-2 sm:col-span-3">
                                <button type="submit" :disabled="formUbah.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                    Simpan Perubahan
                                </button>
                                <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="sunting = null">Batal</button>
                            </div>
                        </form>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
