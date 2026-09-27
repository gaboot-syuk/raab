<script setup lang="ts">
/*
 * Buku kas.
 *
 * TIDAK ADA TOMBOL HAPUS di halaman ini, dan itu disengaja: satu-satunya cara
 * membatalkan transaksi adalah void beralasan. Kesalahan input pun harus
 * meninggalkan jejak — justru itu yang dicari auditor.
 *
 * Alasan void ditanyakan lewat panel isian di bawah barisnya, bukan
 * window.prompt(): prompt diblokir di konteks ber-sandbox sehingga tombolnya
 * tampak tidak berfungsi.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Transaksi {
    id: number;
    nomor_voucher: string;
    tanggal: string;
    jenis: string;
    label_jenis: string;
    jumlah: number;
    keterangan: string;
    sumber: string;
    akun: string | null;
    kategori: string | null;
    status: string;
    label_status: string;
    saldo_sebelum: number | null;
    saldo_sesudah: number | null;
    void_alasan: string | null;
    punya_bukti: boolean;
    wajib_bukti: boolean;
}

const props = defineProps<{
    daftar: {
        data: Transaksi[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    saring: { akun: string; jenis: string; status: string; cari: string; bulan: string };
    pilihanAkun: { id: number; nama: string; saldo: number }[];
    pilihanKategoriMasuk: { id: number; nama: string }[];
    pilihanKategoriKeluar: { id: number; nama: string }[];
    pilihanSumber: Record<string, string>;
    pilihanJenis: Record<string, string>;
    pilihanStatus: Record<string, string>;
    pilihanKegiatan: { id: number; nama: string }[];
    pilihanAnggaran: { id: number; nama: string }[];
    pilihanBukti: { id: number; nama: string }[];
    batasWajibBukti: number;
    catatan: string;
}>();

const saring = ref({ ...props.saring });
const bukaForm = ref(false);
const voidUntuk = ref<number | null>(null);

const form = useForm({
    account_id: props.pilihanAkun[0]?.id ?? null,
    category_id: null as number | null,
    tanggal: new Date().toISOString().slice(0, 10),
    jenis: 'keluar',
    jumlah: 0,
    keterangan: '',
    sumber: 'lain',
    event_id: null as number | null,
    budget_id: null as number | null,
    bukti_media_id: null as number | null,
});

const formVoid = useForm({ void_alasan: '' });

function rupiah(nilai: number | null): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function saringkan(): void {
    router.get('/panel/keuangan/transaksi', { ...saring.value }, { preserveState: true, preserveScroll: true });
}

function terapkanSaring(): void {
    saringkan();
}

function simpan(): void {
    form.post('/panel/keuangan/transaksi', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('keterangan', 'jumlah', 'bukti_media_id', 'category_id');
            bukaForm.value = false;
        },
    });
}

function konfirmasi(t: Transaksi): void {
    if (!confirm(`Konfirmasi ${t.nomor_voucher}? Saldo akun akan diperbarui sebesar ${rupiah(t.jumlah)}.`)) {
        return;
    }

    router.post(`/panel/keuangan/transaksi/${t.id}/konfirmasi`, {}, { preserveScroll: true });
}

function bukaVoid(t: Transaksi): void {
    voidUntuk.value = voidUntuk.value === t.id ? null : t.id;
    formVoid.reset();
    formVoid.clearErrors();
}

function kirimVoid(t: Transaksi): void {
    formVoid.post(`/panel/keuangan/transaksi/${t.id}/void`, {
        preserveScroll: true,
        onSuccess: () => {
            voidUntuk.value = null;
            formVoid.reset();
        },
    });
}

const kategoriAktif = () => (form.jenis === 'masuk' ? props.pilihanKategoriMasuk : props.pilihanKategoriKeluar);
</script>

<template>
    <PanelLayout>
        <Head title="Buku Kas" />

        <div class="mx-auto max-w-6xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Buku Kas</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800" @click="bukaForm = !bukaForm">
                        {{ bukaForm ? 'Tutup' : 'Catat Transaksi' }}
                    </button>
                    <a href="/panel/keuangan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Ikhtisar</a>
                </div>
            </div>

            <!-- ===== Form catat ===== -->
            <form v-if="bukaForm" class="brutal grid gap-4 bg-paper p-5 sm:grid-cols-3" @submit.prevent="simpan">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Akun kas *</span>
                    <select v-model="form.account_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option v-for="a in props.pilihanAkun" :key="a.id" :value="a.id">{{ a.nama }} — {{ rupiah(a.saldo) }}</option>
                    </select>
                    <span v-if="form.errors.account_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.account_id }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                    <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Sumber *</span>
                    <select v-model="form.sumber" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option v-for="(label, nilai) in props.pilihanSumber" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Tanggal *</span>
                    <input v-model="form.tanggal" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.tanggal" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.tanggal }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                    <select v-model="form.category_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— tanpa kategori —</option>
                        <option v-for="k in kategoriAktif()" :key="k.id" :value="k.id">{{ k.nama }}</option>
                    </select>
                    <span v-if="form.errors.category_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.category_id }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jumlah (Rp) *</span>
                    <input v-model.number="form.jumlah" type="number" min="1" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.jumlah" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.jumlah }}</span>
                </label>

                <label class="block sm:col-span-3">
                    <span class="text-xs font-bold uppercase text-muted">Keterangan *</span>
                    <input v-model="form.keterangan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.keterangan" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.keterangan }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Tautkan ke kegiatan</span>
                    <select v-model="form.event_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— tidak ada —</option>
                        <option v-for="e in props.pilihanKegiatan" :key="e.id" :value="e.id">{{ e.nama }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Tautkan ke anggaran</span>
                    <select v-model="form.budget_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— tidak ada —</option>
                        <option v-for="b in props.pilihanAnggaran" :key="b.id" :value="b.id">{{ b.nama }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Bukti / nota</span>
                    <select v-model="form.bukti_media_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option :value="null">— tanpa bukti —</option>
                        <option v-for="m in props.pilihanBukti" :key="m.id" :value="m.id">{{ m.nama }}</option>
                    </select>
                    <span class="text-[11px] text-muted">
                        Wajib bila di atas {{ rupiah(props.batasWajibBukti) }}. Berkas diunggah di Pustaka Media.
                    </span>
                    <span v-if="form.errors.bukti_media_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.bukti_media_id }}</span>
                </label>

                <div class="flex items-end sm:col-span-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Catat sebagai Draft
                    </button>
                </div>
            </form>

            <!-- ===== Saringan ===== -->
            <form class="brutal grid gap-3 bg-paper p-5 sm:grid-cols-5" @submit.prevent="terapkanSaring">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Cari</span>
                    <input v-model="saring.cari" type="search" placeholder="voucher atau keterangan" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Akun</span>
                    <select v-model="saring.akun" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua akun</option>
                        <option v-for="a in props.pilihanAkun" :key="a.id" :value="String(a.id)">{{ a.nama }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis</span>
                    <select v-model="saring.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua jenis</option>
                        <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Status</span>
                    <select v-model="saring.status" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua status</option>
                        <option v-for="(label, nilai) in props.pilihanStatus" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Bulan</span>
                    <input v-model="saring.bulan" type="month" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <div class="flex items-end gap-2 sm:col-span-5">
                    <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Terapkan</button>
                    <a href="/panel/keuangan/transaksi" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold">Reset</a>
                    <span class="ml-auto self-center text-xs text-muted">{{ props.daftar.total }} transaksi</span>
                </div>
            </form>

            <!-- ===== Daftar ===== -->
            <p v-if="!props.daftar.data.length" class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                Tidak ada transaksi yang cocok dengan saringan ini.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="t in props.daftar.data" :key="t.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                <span :class="t.jenis === 'masuk' ? 'text-primary-800' : 'text-accent-600'">
                                    {{ t.jenis === 'masuk' ? '+' : '−' }}{{ rupiah(t.jumlah) }}
                                </span>
                                <span :class="['ml-2 border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', t.status === 'terkonfirmasi' ? 'bg-paper-alt' : (t.status === 'void' ? 'bg-danger text-paper' : 'bg-accent-100')]">
                                    {{ t.label_status }}
                                </span>
                                <span v-if="t.wajib_bukti && !t.punya_bukti" class="ml-1 border-2 border-ink bg-accent-100 px-2 py-0.5 text-[10px] font-bold uppercase">
                                    Tanpa bukti
                                </span>
                            </p>
                            <p class="mt-1">{{ t.keterangan }}</p>
                            <p class="mt-1 text-xs text-muted">
                                <span class="font-mono">{{ t.nomor_voucher }}</span> · {{ t.tanggal }} · {{ t.akun }} · {{ t.sumber }}
                                <template v-if="t.kategori"> · {{ t.kategori }}</template>
                            </p>
                            <p v-if="t.saldo_sesudah !== null" class="text-xs text-muted">
                                Saldo: {{ rupiah(t.saldo_sebelum) }} → {{ rupiah(t.saldo_sesudah) }}
                            </p>
                            <p v-if="t.void_alasan" class="mt-1 text-xs font-bold text-accent-600">
                                Alasan void: {{ t.void_alasan }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button v-if="t.status === 'draft'" type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="konfirmasi(t)">
                                Konfirmasi
                            </button>
                            <button v-if="t.status !== 'void'" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="bukaVoid(t)">
                                {{ voidUntuk === t.id ? 'Tutup' : 'Void' }}
                            </button>
                        </div>
                    </div>

                    <form v-if="voidUntuk === t.id" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimVoid(t)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Alasan void *</span>
                            <textarea v-model="formVoid.void_alasan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            <span class="text-[11px] text-muted">Saldo akan dikoreksi kembali dan transaksinya tetap tersimpan.</span>
                            <span v-if="formVoid.errors.void_alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ formVoid.errors.void_alasan }}</span>
                        </label>

                        <div class="mt-2 flex gap-2">
                            <button type="submit" :disabled="formVoid.processing" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800">
                                Void &amp; Koreksi Saldo
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="voidUntuk = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>

            <!-- Paginasi -->
            <nav v-if="props.daftar.links.length > 3" class="flex flex-wrap justify-center gap-1" aria-label="Navigasi halaman">
                <template v-for="(l, i) in props.daftar.links" :key="i">
                    <a
                        v-if="l.url"
                        :href="l.url"
                        :class="['brutal-sm px-3 py-1.5 text-xs font-bold', l.active ? 'bg-accent-400 text-primary-800' : 'bg-paper']"
                        v-html="l.label"
                    ></a>
                    <span v-else class="px-3 py-1.5 text-xs text-muted" v-html="l.label"></span>
                </template>
            </nav>
        </div>
    </PanelLayout>
</template>
