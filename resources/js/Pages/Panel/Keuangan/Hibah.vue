<script setup lang="ts">
/*
 * Hibah & dukungan alumni — sisi Bendahara.
 *
 * Menerima hibah bukan sekadar mengubah status: jenisnya menentukan modul mana
 * yang tersentuh. Karena itu formulir penerimaan berubah mengikuti jenisnya —
 * dana menanyakan akun kas, barang menanyakan aset inventaris, jasa menanyakan
 * kegiatan.
 *
 * Alasan penolakan diisi lewat panel, bukan window.prompt() yang diblokir di
 * konteks ber-sandbox.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Hibah {
    id: number;
    nomor_hibah: string;
    judul: string;
    jenis: string;
    label_jenis: string;
    pemberi: string;
    anonim: boolean;
    kontak: string | null;
    nilai: number;
    nilai_diterima: number | null;
    status: string;
    label_status: string;
    tanggal_rencana: string | null;
    diterima_pada: string | null;
    alasan_tolak: string | null;
    catatan_bendahara: string | null;
    ke_kas: string | null;
    ke_inventaris: string | null;
    ke_kegiatan: string | null;
}

const props = defineProps<{
    daftar: Hibah[];
    saring: { jenis: string; status: string };
    rekap: {
        total: number;
        jumlah: number;
        per_jenis: Record<string, number>;
        per_alumni: { nama: string; total: number; jumlah: number }[];
    };
    pilihanJenis: Record<string, string>;
    pilihanStatus: Record<string, string>;
    pilihanAkun: { id: number; nama: string }[];
    pilihanKategoriMasuk: { id: number; nama: string }[];
    pilihanAset: { id: number; nama: string }[];
    pilihanKegiatan: { id: number; nama: string }[];
    pilihanBukti: { id: number; nama: string }[];
    batasWajibBukti: number;
    catatan: string;
}>();

const saring = ref({ ...props.saring });
const bukaCatat = ref(false);
const terimaUntuk = ref<number | null>(null);
const tolakUntuk = ref<number | null>(null);

const form = useForm({
    jenis: 'dana',
    judul: '',
    deskripsi: '',
    nama_pemberi: '',
    kontak: '',
    estimasi_nilai: 0,
    tanggal_rencana: '',
    anonim: false,
});

const formTerima = useForm({
    nilai_diterima: 0,
    account_id: props.pilihanAkun[0]?.id ?? null,
    category_id: null as number | null,
    inventory_item_id: props.pilihanAset[0]?.id ?? null,
    jumlah: 1,
    kondisi_barang: 'baik',
    event_id: null as number | null,
    bukti_media_id: null as number | null,
    catatan_bendahara: '',
});

const formTolak = useForm({ alasan_tolak: '' });

function rupiah(nilai: number | null): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function saringkan(): void {
    router.get('/panel/keuangan/hibah', { ...saring.value }, { preserveState: true, preserveScroll: true });
}

function simpan(): void {
    form.post('/panel/keuangan/hibah', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            bukaCatat.value = false;
        },
    });
}

function setujui(h: Hibah): void {
    router.post(`/panel/keuangan/hibah/${h.id}/setujui`, {}, { preserveScroll: true });
}

function bukaTerima(h: Hibah): void {
    terimaUntuk.value = terimaUntuk.value === h.id ? null : h.id;
    formTerima.reset();
    formTerima.clearErrors();
    formTerima.nilai_diterima = h.nilai;
}

function kirimTerima(h: Hibah): void {
    formTerima.post(`/panel/keuangan/hibah/${h.id}/terima`, {
        preserveScroll: true,
        onSuccess: () => {
            terimaUntuk.value = null;
            formTerima.reset();
        },
    });
}

function bukaTolak(h: Hibah): void {
    tolakUntuk.value = tolakUntuk.value === h.id ? null : h.id;
    formTolak.reset();
    formTolak.clearErrors();
}

function kirimTolak(h: Hibah): void {
    formTolak.post(`/panel/keuangan/hibah/${h.id}/tolak`, {
        preserveScroll: true,
        onSuccess: () => {
            tolakUntuk.value = null;
            formTolak.reset();
        },
    });
}
</script>

<template>
    <PanelLayout>
        <Head title="Hibah & Dukungan" />

        <div class="mx-auto max-w-6xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Hibah &amp; Dukungan Alumni</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800" @click="bukaCatat = !bukaCatat">
                        {{ bukaCatat ? 'Tutup' : 'Catat Hibah Langsung' }}
                    </button>
                    <a href="/panel/keuangan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Ikhtisar</a>
                </div>
            </div>

            <!-- Rekap -->
            <ul class="grid gap-3 sm:grid-cols-4">
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Total Diterima</p>
                    <p class="font-display text-xl">{{ rupiah(props.rekap.total) }}</p>
                    <p class="text-xs text-muted">{{ props.rekap.jumlah }} hibah</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Dana</p>
                    <p class="font-display text-xl">{{ rupiah(props.rekap.per_jenis.dana ?? 0) }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Barang</p>
                    <p class="font-display text-xl">{{ rupiah(props.rekap.per_jenis.barang ?? 0) }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Jasa</p>
                    <p class="font-display text-xl">{{ rupiah(props.rekap.per_jenis.jasa ?? 0) }}</p>
                </li>
            </ul>

            <!-- Catat langsung -->
            <form v-if="bukaCatat" class="brutal grid gap-4 bg-paper p-5 sm:grid-cols-3" @submit.prevent="simpan">
                <p class="text-sm sm:col-span-3">
                    Untuk hibah yang diserahkan langsung di sekretariat. Hibah dari dashboard alumni akan muncul sendiri di daftar bawah.
                </p>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                    <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-xs font-bold uppercase text-muted">Judul *</span>
                    <input v-model="form.judul" type="text" placeholder="Dukungan konsumsi Mapaba" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.judul" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.judul }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Nama pemberi *</span>
                    <input v-model="form.nama_pemberi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.nama_pemberi" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama_pemberi }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Kontak</span>
                    <input v-model="form.kontak" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Estimasi nilai (Rp) *</span>
                    <input v-model.number="form.estimasi_nilai" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Tanggal rencana</span>
                    <input v-model="form.tanggal_rencana" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-xs font-bold uppercase text-muted">Deskripsi</span>
                    <input v-model="form.deskripsi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="flex items-center gap-2 self-end text-sm font-bold">
                    <input v-model="form.anonim" type="checkbox" class="h-4 w-4"> Sembunyikan nama (anonim)
                </label>

                <div class="flex items-end sm:col-span-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Simpan Hibah
                    </button>
                </div>
            </form>

            <!-- Saringan -->
            <form class="brutal flex flex-wrap items-end gap-3 bg-paper p-5" @submit.prevent="saringkan">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis</span>
                    <select v-model="saring.jenis" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua jenis</option>
                        <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Status</span>
                    <select v-model="saring.status" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua status</option>
                        <option v-for="(label, nilai) in props.pilihanStatus" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Terapkan</button>
                <a href="/panel/keuangan/hibah/ekspor" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold">Ekspor CSV</a>
            </form>

            <!-- Daftar -->
            <p v-if="!props.daftar.length" class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                Belum ada hibah.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="h in props.daftar" :key="h.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                {{ h.judul }}
                                <span class="ml-2 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">{{ h.label_jenis }}</span>
                                <span :class="['ml-1 border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', ['diterima', 'diverifikasi'].includes(h.status) ? 'bg-accent-100' : 'bg-paper-alt']">
                                    {{ h.label_status }}
                                </span>
                                <span v-if="h.anonim" class="ml-1 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">Anonim</span>
                            </p>
                            <p class="mt-1 text-sm">
                                {{ h.pemberi }} · <span class="font-display">{{ rupiah(h.nilai) }}</span>
                            </p>
                            <p class="text-xs text-muted">
                                <span class="font-mono">{{ h.nomor_hibah }}</span>
                                <template v-if="h.kontak"> · {{ h.kontak }}</template>
                                <template v-if="h.tanggal_rencana"> · rencana {{ h.tanggal_rencana }}</template>
                                <template v-if="h.diterima_pada"> · diterima {{ h.diterima_pada }}</template>
                            </p>

                            <p v-if="h.ke_kas || h.ke_inventaris || h.ke_kegiatan" class="mt-1 text-xs font-bold">
                                Bermuara ke:
                                <template v-if="h.ke_kas"> kas ({{ h.ke_kas }})</template>
                                <template v-if="h.ke_inventaris"> inventaris ({{ h.ke_inventaris }})</template>
                                <template v-if="h.ke_kegiatan"> kegiatan ({{ h.ke_kegiatan }})</template>
                            </p>

                            <p v-if="h.alasan_tolak" class="mt-1 text-xs font-bold text-accent-600">Alasan ditolak: {{ h.alasan_tolak }}</p>
                            <p v-if="h.catatan_bendahara" class="text-xs text-muted">Catatan: {{ h.catatan_bendahara }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="['diajukan', 'dijanjikan'].includes(h.status)"
                                type="button"
                                class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                @click="setujui(h)"
                            >Setujui</button>

                            <button
                                v-if="!['diterima', 'diverifikasi', 'ditolak', 'dibatalkan'].includes(h.status)"
                                type="button"
                                class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                                @click="bukaTerima(h)"
                            >{{ terimaUntuk === h.id ? 'Tutup' : 'Terima' }}</button>

                            <button
                                v-if="!['diterima', 'diverifikasi', 'ditolak', 'dibatalkan'].includes(h.status)"
                                type="button"
                                class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                @click="bukaTolak(h)"
                            >{{ tolakUntuk === h.id ? 'Tutup' : 'Tolak' }}</button>
                        </div>
                    </div>

                    <!-- Panel terima: berubah menurut jenis -->
                    <form v-if="terimaUntuk === h.id" class="mt-4 grid gap-3 border-t-2 border-ink/10 pt-4 sm:grid-cols-3" @submit.prevent="kirimTerima(h)">
                        <p class="text-sm sm:col-span-3">
                            <template v-if="h.jenis === 'dana'">
                                Hibah dana akan dicatat sebagai <span class="font-bold">kas masuk bersumber "hibah"</span>.
                            </template>
                            <template v-else-if="h.jenis === 'barang'">
                                Hibah barang akan dicatat sebagai <span class="font-bold">mutasi inventaris "masuk"</span>, menambah stok aset yang dipilih.
                            </template>
                            <template v-else>
                                Hibah jasa dicatat sebagai <span class="font-bold">kesediaan pada kegiatan</span> — tidak menyentuh kas maupun stok.
                            </template>
                        </p>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Nilai diterima (Rp) *</span>
                            <input v-model.number="formTerima.nilai_diterima" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formTerima.errors.nilai_diterima" class="mt-1 block text-xs font-bold text-accent-600">{{ formTerima.errors.nilai_diterima }}</span>
                        </label>

                        <template v-if="h.jenis === 'dana'">
                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Akun kas tujuan *</span>
                                <select v-model="formTerima.account_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option v-for="a in props.pilihanAkun" :key="a.id" :value="a.id">{{ a.nama }}</option>
                                </select>
                                <span v-if="formTerima.errors.account_id" class="mt-1 block text-xs font-bold text-accent-600">{{ formTerima.errors.account_id }}</span>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Kategori penerimaan</span>
                                <select v-model="formTerima.category_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option :value="null">— tanpa kategori —</option>
                                    <option v-for="k in props.pilihanKategoriMasuk" :key="k.id" :value="k.id">{{ k.nama }}</option>
                                </select>
                            </label>

                            <label class="block sm:col-span-3">
                                <span class="text-xs font-bold uppercase text-muted">Bukti / nota transfer</span>
                                <select v-model="formTerima.bukti_media_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option :value="null">— tanpa bukti —</option>
                                    <option v-for="m in props.pilihanBukti" :key="m.id" :value="m.id">{{ m.nama }}</option>
                                </select>
                                <span class="text-[11px] text-muted">
                                    Wajib bila nilai hibah di atas Rp{{ props.batasWajibBukti.toLocaleString('id-ID') }}. Berkas diunggah di Pustaka Media.
                                </span>
                                <span v-if="formTerima.errors.bukti_media_id" class="mt-1 block text-xs font-bold text-accent-600">{{ formTerima.errors.bukti_media_id }}</span>
                            </label>
                        </template>

                        <template v-if="h.jenis === 'barang'">
                            <label class="block sm:col-span-2">
                                <span class="text-xs font-bold uppercase text-muted">Aset inventaris *</span>
                                <select v-model="formTerima.inventory_item_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option v-for="i in props.pilihanAset" :key="i.id" :value="i.id">{{ i.nama }}</option>
                                </select>
                                <span class="text-[11px] text-muted">Barang jenis baru? Daftarkan asetnya dulu di panel Inventaris.</span>
                                <span v-if="formTerima.errors.inventory_item_id" class="mt-1 block text-xs font-bold text-accent-600">{{ formTerima.errors.inventory_item_id }}</span>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Jumlah *</span>
                                <input v-model.number="formTerima.jumlah" type="number" min="1" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>
                        </template>

                        <template v-if="h.jenis === 'jasa'">
                            <label class="block sm:col-span-2">
                                <span class="text-xs font-bold uppercase text-muted">Tautkan ke kegiatan</span>
                                <select v-model="formTerima.event_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option :value="null">— belum ditautkan —</option>
                                    <option v-for="e in props.pilihanKegiatan" :key="e.id" :value="e.id">{{ e.nama }}</option>
                                </select>
                            </label>
                        </template>

                        <label class="block sm:col-span-3">
                            <span class="text-xs font-bold uppercase text-muted">Catatan Bendahara</span>
                            <input v-model="formTerima.catatan_bendahara" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <div class="flex items-end gap-2 sm:col-span-3">
                            <button type="submit" :disabled="formTerima.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                Terima Hibah
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="terimaUntuk = null">Batal</button>
                        </div>
                    </form>

                    <!-- Panel tolak -->
                    <form v-if="tolakUntuk === h.id" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimTolak(h)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Alasan penolakan *</span>
                            <textarea v-model="formTolak.alasan_tolak" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            <span v-if="formTolak.errors.alasan_tolak" class="mt-1 block text-xs font-bold text-accent-600">{{ formTolak.errors.alasan_tolak }}</span>
                        </label>

                        <div class="mt-2 flex gap-2">
                            <button type="submit" :disabled="formTolak.processing" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Kirim Penolakan</button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="tolakUntuk = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>

            <!-- Peringkat pemberi -->
            <section v-if="props.rekap.per_alumni.length" class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Pemberi Terbesar</h2>
                <p class="text-xs text-muted">Untuk apresiasi. Pemberi anonim tetap ditampilkan tanpa identitas.</p>
                <ul class="mt-3 divide-y-2 divide-ink/10 text-sm">
                    <li v-for="p in props.rekap.per_alumni" :key="p.nama" class="flex items-center justify-between py-2">
                        <span class="font-bold">{{ p.nama }}</span>
                        <span>{{ rupiah(p.total) }} <span class="text-xs text-muted">({{ p.jumlah }} hibah)</span></span>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
