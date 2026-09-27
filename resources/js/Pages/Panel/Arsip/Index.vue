<script setup lang="ts">
/*
 * Panel arsip dokumen.
 *
 * Yang membedakan modul ini dari pustaka media: AUDIENS. Berkasnya disimpan di
 * penyimpanan privat, dan satu-satunya jalan keluarnya adalah jalur unduhan
 * yang memeriksa audiens. Karena itu daftar di halaman ini menampilkan dengan
 * jelas siapa saja yang boleh mengunduh tiap dokumen — tanpa itu, pengurus
 * tidak punya cara memeriksa apakah hak aksesnya sudah benar.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Dokumen {
    id: number;
    slug: string;
    judul: string;
    keterangan: string | null;
    kategori: string;
    label_kategori: string;
    nomor: string | null;
    tanggal: string | null;
    tanggal_teks: string | null;
    akses: string[];
    audiens_teks: string[];
    periode: string | null;
    nama_berkas: string | null;
    ukuran: string | null;
    punya_berkas: boolean;
    tautan_luar: string | null;
    diunggah: string | null;
    pengunggah: string | null;
}

const props = defineProps<{
    daftar: Dokumen[];
    rekap: { total: number; publik: number; internal: number; tanpa_berkas: number; kategori: Record<string, number> };
    kategori: string;
    pilihanKategori: Record<string, string>;
    pilihanAudiens: Record<string, string>;
    periode: { id: number; nama: string }[];
    maksKb: number;
    catatan: string;
}>();

const kategoriSaring = ref(props.kategori);
const sedangSunting = ref<number | null>(null);

function formKosong() {
    return {
        judul: '',
        keterangan: '',
        kategori: 'lainnya',
        nomor: '',
        tanggal_dokumen: '',
        akses: [] as string[],
        tautan_luar: '',
        period_id: '',
        berkas: null as File | null,
    };
}

const form = useForm(formKosong());
const formSunting = useForm(formKosong());

function saring(): void {
    router.get('/panel/arsip', { kategori: kategoriSaring.value }, { preserveScroll: true });
}

function pilihBerkas(e: Event, untuk: 'baru' | 'sunting'): void {
    const berkas = (e.target as HTMLInputElement).files?.[0] ?? null;

    if (untuk === 'baru') {
        form.berkas = berkas;
    } else {
        formSunting.berkas = berkas;
    }
}

function simpan(): void {
    form.post('/panel/arsip', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

function bukaSunting(d: Dokumen): void {
    if (sedangSunting.value === d.id) {
        sedangSunting.value = null;
        return;
    }

    sedangSunting.value = d.id;
    formSunting.judul = d.judul;
    formSunting.keterangan = d.keterangan ?? '';
    formSunting.kategori = d.kategori;
    formSunting.nomor = d.nomor ?? '';
    formSunting.tanggal_dokumen = d.tanggal ?? '';
    formSunting.akses = [...d.akses];
    formSunting.tautan_luar = d.tautan_luar ?? '';
    formSunting.period_id = '';
    formSunting.berkas = null;
}

function perbarui(d: Dokumen): void {
    formSunting.post(`/panel/arsip/${d.id}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => (sedangSunting.value = null),
    });
}

function hapus(d: Dokumen): void {
    if (!confirm(`Hapus dokumen "${d.judul}"?\n\nBerkasnya TIDAK ikut dihapus dari pustaka media, jadi salah hapus masih bisa dipulihkan.`)) {
        return;
    }

    router.delete(`/panel/arsip/${d.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Arsip Dokumen" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Arsip Dokumen</h1>
                    <p class="text-sm text-muted">AD/ART, template surat, dan hasil rapat — dengan hak akses per audiens.</p>
                </div>

                <a href="/panel/arsip/ekspor" class="brutal-sm brutal-hover bg-paper-alt px-4 py-2 text-sm font-bold">Ekspor CSV</a>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Total Dokumen</p>
                    <p class="font-display text-2xl">{{ props.rekap.total }}</p>
                </div>
                <div class="brutal bg-accent-100 p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Terbuka untuk Umum</p>
                    <p class="font-display text-2xl">{{ props.rekap.publik }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Terbatas</p>
                    <p class="font-display text-2xl">{{ props.rekap.internal }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Tanpa Berkas</p>
                    <p class="font-display text-2xl">{{ props.rekap.tanpa_berkas }}</p>
                </div>
            </section>

            <!-- ===== Formulir baru ===== -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Tambah Dokumen</h2>

                <form class="mt-3 space-y-4" @submit.prevent="simpan">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Judul *</span>
                            <input v-model="form.judul" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.judul" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.judul }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kategori *</span>
                            <select v-model="form.kategori" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, kunci) in props.pilihanKategori" :key="kunci" :value="kunci">{{ label }}</option>
                            </select>
                            <span class="text-[11px] text-muted">Kategori hanya keterangan. Yang menentukan siapa boleh mengunduh adalah audiens.</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Nomor Dokumen</span>
                            <input v-model="form.nomor" type="text" placeholder="012/AD/RAAB/IX/2026" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Tanggal Dokumen</span>
                            <input v-model="form.tanggal_dokumen" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Periode Kepengurusan</span>
                            <select v-model="form.period_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option value="">— tidak ditautkan —</option>
                                <option v-for="p in props.periode" :key="p.id" :value="p.id">{{ p.nama }}</option>
                            </select>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                            <textarea v-model="form.keterangan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Berkas</span>
                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="pilihBerkas($event, 'baru')">
                            <span class="text-[11px] text-muted">PDF, Word, Excel, atau gambar. Paling besar {{ Math.round(props.maksKb / 1024) }} MB. Boleh dikosongkan bila dokumennya sekadar tautan.</span>
                            <span v-if="form.errors.berkas" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.berkas }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Tautan Luar</span>
                            <input v-model="form.tautan_luar" type="url" placeholder="https://" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span class="text-[11px] text-muted">Untuk dokumen yang berkasnya disimpan di tempat lain.</span>
                        </label>
                    </div>

                    <fieldset>
                        <legend class="text-xs font-bold uppercase text-muted">Siapa yang boleh mengunduh *</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label v-for="(label, kunci) in props.pilihanAudiens" :key="kunci" class="flex items-center gap-2">
                                <input v-model="form.akses" type="checkbox" :value="kunci" class="h-4 w-4 border-2 border-ink">
                                <span class="text-sm">{{ label }}</span>
                            </label>
                        </div>
                        <span class="text-[11px] text-muted">Dokumen yang menyertakan "Umum" ikut muncul di halaman publik /arsip.</span>
                        <span v-if="form.errors.akses" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.akses }}</span>
                    </fieldset>

                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Simpan Dokumen
                    </button>
                </form>
            </section>

            <!-- ===== Daftar ===== -->
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                    <select v-model="kategoriSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua kategori</option>
                        <option v-for="(label, kunci) in props.pilihanKategori" :key="kunci" :value="kunci">{{ label }}</option>
                    </select>
                </label>
            </div>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-5 text-sm text-muted">
                Belum ada dokumen pada kategori ini.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="d in props.daftar" :key="d.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                                {{ d.label_kategori }}
                                <template v-if="d.nomor"> · {{ d.nomor }}</template>
                                <template v-if="d.tanggal_teks"> · {{ d.tanggal_teks }}</template>
                                <template v-if="d.periode"> · {{ d.periode }}</template>
                            </p>
                            <h2 class="font-display text-lg leading-tight">{{ d.judul }}</h2>

                            <p v-if="d.keterangan" class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ d.keterangan }}</p>

                            <p class="mt-2 text-xs text-muted">
                                <template v-if="d.punya_berkas">
                                    {{ d.nama_berkas || d.tautan_luar }}
                                    <template v-if="d.ukuran"> ({{ d.ukuran }})</template>
                                </template>
                                <template v-else>Belum ada berkas.</template>
                                <template v-if="d.pengunggah"> · Diunggah {{ d.pengunggah }}, {{ d.diunggah }}</template>
                            </p>

                            <p class="mt-2 text-xs">
                                <span class="font-bold uppercase text-[10px]">Boleh mengunduh:</span>
                                <span
                                    v-for="a in d.audiens_teks"
                                    :key="a"
                                    class="ml-1 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase"
                                >{{ a }}</span>
                            </p>
                        </div>

                        <a
                            v-if="d.punya_berkas"
                            :href="`/arsip/${d.id}/unduh`"
                            class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                        >Uji Unduh</a>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="bukaSunting(d)">
                            {{ sedangSunting === d.id ? 'Tutup' : 'Sunting' }}
                        </button>
                        <button type="button" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold" @click="hapus(d)">
                            Hapus
                        </button>
                    </div>

                    <form v-if="sedangSunting === d.id" class="mt-3 space-y-3 border-t-2 border-ink/10 pt-3" @submit.prevent="perbarui(d)">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="text-xs font-bold uppercase text-muted">Judul *</span>
                                <input v-model="formSunting.judul" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                                <select v-model="formSunting.kategori" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option v-for="(label, kunci) in props.pilihanKategori" :key="kunci" :value="kunci">{{ label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Nomor</span>
                                <input v-model="formSunting.nomor" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Tanggal</span>
                                <input v-model="formSunting.tanggal_dokumen" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Ganti Berkas</span>
                                <input type="file" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="pilihBerkas($event, 'sunting')">
                                <span class="text-[11px] text-muted">Berkas lama tidak dihapus dari pustaka media.</span>
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                                <textarea v-model="formSunting.keterangan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            </label>
                        </div>

                        <fieldset>
                            <legend class="text-xs font-bold uppercase text-muted">Siapa yang boleh mengunduh</legend>
                            <div class="mt-2 flex flex-wrap gap-4">
                                <label v-for="(label, kunci) in props.pilihanAudiens" :key="kunci" class="flex items-center gap-2">
                                    <input v-model="formSunting.akses" type="checkbox" :value="kunci" class="h-4 w-4 border-2 border-ink">
                                    <span class="text-sm">{{ label }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="flex gap-2">
                            <button type="submit" :disabled="formSunting.processing" class="brutal-sm bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800">
                                Simpan Perubahan
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="sedangSunting = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
