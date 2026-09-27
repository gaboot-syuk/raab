<script setup lang="ts">
/*
 * Iuran anggota.
 *
 * Bukti transfer yang menunggu verifikasi diletakkan paling atas karena itulah
 * pekerjaan yang paling sering dikerjakan Bendahara.
 *
 * Memverifikasi pembayaran sekaligus mencatat kas masuk — satu pembayaran,
 * satu transaksi. Kalau keduanya dibuat terpisah, satu pembayaran akan
 * tercatat dua kali di laporan.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Kategori {
    id: number;
    kode: string;
    nama: string;
    target: string;
    target_audiens: string;
    nominal: number;
    periode: string;
    aktif: boolean;
    jumlah_tagihan: number;
    jumlah_penerima: number;
}

interface Tagihan {
    id: number;
    anggota: string;
    nominal: number;
    status: string;
    label_status: string;
    jatuh_tempo: string | null;
    terlambat: boolean;
    dibebaskan_alasan: string | null;
    pengingat_terakhir_pada: string | null;
    pengingat_terkirim: number;
}

interface Menunggu {
    id: number;
    anggota: string;
    kategori: string | null;
    periode_label: string | null;
    jumlah: number;
    metode: string;
    bukti_media_id: number | null;
    catatan_pembayar: string | null;
    dibuat: string;
}

const props = defineProps<{
    kategori: Kategori[];
    pilihanTarget: Record<string, string>;
    pilihanPeriode: Record<string, string>;
    terpilih: { id: number; nama: string; nominal: number } | null;
    periode: string;
    rekap: {
        lunas: number;
        belum: number;
        menunggu: number;
        nominal_terkumpul: number;
        nominal_target: number;
        bisa_diingatkan: number;
        tagihan: Tagihan[];
    } | null;
    menungguVerifikasi: Menunggu[];
    pilihanAkun: { id: number; nama: string }[];
    pilihanKategoriMasuk: { id: number; nama: string }[];
    catatan: string;
}>();

const periodePilih = ref(props.periode);
const akunId = ref<number | null>(props.pilihanAkun[0]?.id ?? null);
const bukaKategori = ref(false);
const tolakUntuk = ref<number | null>(null);
const bebasUntuk = ref<number | null>(null);

const formKategori = useForm({
    nama: '',
    target_audiens: 'kader_aktif',
    nominal: 0,
    periode: 'bulanan',
    urutan: 0,
});

const formTolak = useForm({ catatan_bendahara: '' });
const formBebas = useForm({ dibebaskan_alasan: '' });

function rupiah(nilai: number | null): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function muat(kategoriId: number): void {
    router.get('/panel/keuangan/iuran', { kategori: kategoriId, periode: periodePilih.value }, { preserveScroll: true });
}

function gantiPeriode(): void {
    if (props.terpilih) {
        muat(props.terpilih.id);
    }
}

function simpanKategori(): void {
    formKategori.post('/panel/keuangan/iuran/kategori', {
        preserveScroll: true,
        onSuccess: () => {
            formKategori.reset();
            bukaKategori.value = false;
        },
    });
}

function terbitkan(): void {
    if (!props.terpilih) {
        return;
    }

    if (!confirm(`Terbitkan tagihan ${props.terpilih.nama} untuk periode ${periodePilih.value}?`)) {
        return;
    }

    router.post(`/panel/keuangan/iuran/kategori/${props.terpilih.id}/terbitkan`, {
        periode_label: periodePilih.value,
    }, { preserveScroll: true });
}

function kirimPengingat(): void {
    if (!props.terpilih) {
        return;
    }

    const jumlah = props.rekap?.bisa_diingatkan ?? 0;

    if (jumlah === 0) {
        return;
    }

    if (!confirm(`Kirim email pengingat ke ${jumlah} anggota yang belum membayar ${props.terpilih.nama} periode ${periodePilih.value}?`)) {
        return;
    }

    router.post(`/panel/keuangan/iuran/kategori/${props.terpilih.id}/ingatkan`, {
        periode_label: periodePilih.value,
    }, { preserveScroll: true });
}

function catatTunai(t: Tagihan): void {
    if (!confirm(`Catat pembayaran TUNAI ${rupiah(t.nominal)} dari ${t.anggota}? Tagihan langsung lunas dan kas masuk terbentuk.`)) {
        return;
    }

    router.post(`/panel/keuangan/iuran/tagihan/${t.id}/tunai`, {
        jumlah: t.nominal,
        account_id: akunId.value,
    }, { preserveScroll: true });
}

function verifikasi(m: Menunggu): void {
    router.post(`/panel/keuangan/iuran/pembayaran/${m.id}/verifikasi`, {
        account_id: akunId.value,
    }, { preserveScroll: true });
}

function bukaTolak(m: Menunggu): void {
    tolakUntuk.value = tolakUntuk.value === m.id ? null : m.id;
    formTolak.reset();
    formTolak.clearErrors();
}

function kirimTolak(m: Menunggu): void {
    formTolak.post(`/panel/keuangan/iuran/pembayaran/${m.id}/tolak`, {
        preserveScroll: true,
        onSuccess: () => {
            tolakUntuk.value = null;
            formTolak.reset();
        },
    });
}

function bukaBebas(t: Tagihan): void {
    bebasUntuk.value = bebasUntuk.value === t.id ? null : t.id;
    formBebas.reset();
    formBebas.clearErrors();
}

function kirimBebas(t: Tagihan): void {
    formBebas.post(`/panel/keuangan/iuran/tagihan/${t.id}/bebaskan`, {
        preserveScroll: true,
        onSuccess: () => {
            bebasUntuk.value = null;
            formBebas.reset();
        },
    });
}
</script>

<template>
    <PanelLayout>
        <Head title="Iuran" />

        <div class="mx-auto max-w-6xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Iuran</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
                </div>
                <a href="/panel/keuangan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Ikhtisar</a>
            </div>

            <!-- ===== Bukti menunggu verifikasi ===== -->
            <section v-if="props.menungguVerifikasi.length" class="brutal bg-accent-100 p-5">
                <h2 class="font-display text-lg">Menunggu Verifikasi ({{ props.menungguVerifikasi.length }})</h2>
                <p class="text-xs text-muted">Memverifikasi sekaligus mencatat kas masuk. Uang tidak pernah dicatat dua kali.</p>

                <ul class="mt-3 space-y-3">
                    <li v-for="m in props.menungguVerifikasi" :key="m.id" class="border-2 border-ink bg-paper p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-bold">{{ m.anggota }} — {{ rupiah(m.jumlah) }}</p>
                                <p class="text-xs text-muted">
                                    {{ m.kategori }} · {{ m.periode_label }} · {{ m.metode }} · diunggah {{ m.dibuat }}
                                </p>
                                <p v-if="m.catatan_pembayar" class="mt-1 text-xs">{{ m.catatan_pembayar }}</p>
                                <p v-if="!m.bukti_media_id" class="mt-1 text-xs font-bold text-accent-600">
                                    TANPA BUKTI — pastikan dulu sebelum memverifikasi.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="verifikasi(m)">
                                    Verifikasi
                                </button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="bukaTolak(m)">
                                    {{ tolakUntuk === m.id ? 'Tutup' : 'Tolak' }}
                                </button>
                            </div>
                        </div>

                        <form v-if="tolakUntuk === m.id" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimTolak(m)">
                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Alasan penolakan *</span>
                                <textarea v-model="formTolak.catatan_bendahara" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                                <span class="text-[11px] text-muted">Anggota akan diminta mengunggah ulang. Bukti lama tetap tersimpan.</span>
                                <span v-if="formTolak.errors.catatan_bendahara" class="mt-1 block text-xs font-bold text-accent-600">{{ formTolak.errors.catatan_bendahara }}</span>
                            </label>

                            <div class="mt-2 flex gap-2">
                                <button type="submit" :disabled="formTolak.processing" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Kirim Penolakan</button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="tolakUntuk = null">Batal</button>
                            </div>
                        </form>
                    </li>
                </ul>
            </section>

            <!-- ===== Kategori iuran ===== -->
            <section class="brutal bg-paper p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display text-lg">Kategori Iuran</h2>
                    <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="bukaKategori = !bukaKategori">
                        {{ bukaKategori ? 'Tutup' : 'Tambah Kategori' }}
                    </button>
                </div>

                <form v-if="bukaKategori" class="mt-3 grid gap-3 border-t-2 border-ink/10 pt-3 sm:grid-cols-4" @submit.prevent="simpanKategori">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                        <input v-model="formKategori.nama" type="text" placeholder="Iuran Anggota Aktif" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="formKategori.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ formKategori.errors.nama }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Penerima *</span>
                        <select v-model="formKategori.target_audiens" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in props.pilihanTarget" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Periode *</span>
                        <select v-model="formKategori.periode" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in props.pilihanPeriode" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Nominal (Rp) *</span>
                        <input v-model.number="formKategori.nominal" type="number" min="1" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="formKategori.errors.nominal" class="mt-1 block text-xs font-bold text-accent-600">{{ formKategori.errors.nominal }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                        <input v-model.number="formKategori.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <div class="flex items-end sm:col-span-4">
                        <button type="submit" :disabled="formKategori.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                            Simpan Kategori
                        </button>
                    </div>
                </form>

                <ul class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="k in props.kategori" :key="k.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-bold">
                                {{ k.nama }}
                                <span v-if="!k.aktif" class="ml-2 border-2 border-ink bg-accent-100 px-2 py-0.5 text-[10px] font-bold uppercase">Nonaktif</span>
                            </p>
                            <p class="text-xs text-muted">
                                {{ k.target }} · {{ rupiah(k.nominal) }} · {{ k.periode }} · {{ k.jumlah_tagihan }} tagihan · {{ k.jumlah_penerima }} calon penerima
                            </p>
                        </div>
                        <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="muat(k.id)">
                            {{ props.terpilih?.id === k.id ? 'Sedang Dibuka' : 'Buka' }}
                        </button>
                    </li>
                </ul>
            </section>

            <!-- ===== Rekap & tagihan ===== -->
            <section v-if="props.rekap && props.terpilih" class="brutal bg-paper p-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg">Rekap {{ props.terpilih.nama }}</h2>
                        <p class="text-sm text-muted">
                            Periode {{ props.periode }} · {{ props.rekap.lunas }} lunas · {{ props.rekap.belum }} belum ·
                            {{ props.rekap.menunggu }} menunggu
                        </p>
                        <p class="mt-1">
                            Terkumpul <span class="font-display text-lg">{{ rupiah(props.rekap.nominal_terkumpul) }}</span>
                            dari target {{ rupiah(props.rekap.nominal_target) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Periode</span>
                            <input v-model="periodePilih" type="month" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="gantiPeriode">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Akun tujuan</span>
                            <select v-model="akunId" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="a in props.pilihanAkun" :key="a.id" :value="a.id">{{ a.nama }}</option>
                            </select>
                        </label>
                        <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800" @click="terbitkan">
                            Terbitkan Tagihan
                        </button>
                        <button
                            type="button"
                            class="brutal-sm brutal-hover px-4 py-2 text-sm font-bold disabled:opacity-40"
                            :class="props.rekap.bisa_diingatkan > 0 ? 'bg-primary-600 text-paper' : 'bg-paper-alt text-ink'"
                            :disabled="props.rekap.bisa_diingatkan === 0"
                            @click="kirimPengingat"
                        >
                            Kirim Pengingat ({{ props.rekap.bisa_diingatkan }})
                        </button>
                    </div>
                </div>

                <p class="mt-2 text-xs text-muted">
                    Menerbitkan dua kali untuk periode yang sama tidak menggandakan tagihan — penerima yang sudah punya tagihan dilewati.
                    Pengingat dikirim manual lewat email, dan yang buktinya sedang diperiksa tidak ikut diingatkan.
                </p>

                <p v-if="!props.rekap.tagihan.length" class="mt-4 text-sm text-muted">
                    Belum ada tagihan untuk periode ini.
                </p>

                <table v-else class="mt-4 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Anggota</th>
                            <th class="py-2 text-right">Nominal</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Jatuh tempo</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in props.rekap.tagihan" :key="t.id" class="border-b-2 border-ink/10">
                            <td class="py-2 font-bold">{{ t.anggota }}</td>
                            <td class="py-2 text-right">{{ rupiah(t.nominal) }}</td>
                            <td class="py-2">
                                <span :class="['border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', ['lunas', 'dibebaskan'].includes(t.status) ? 'bg-paper-alt' : 'bg-accent-100']">
                                    {{ t.label_status }}
                                </span>
                                <span v-if="t.terlambat" class="ml-1 text-[10px] font-bold text-accent-600">Terlambat</span>
                                <span v-if="t.dibebaskan_alasan" class="block text-[10px] text-muted">{{ t.dibebaskan_alasan }}</span>
                            </td>
                            <td class="py-2 text-xs">
                                {{ t.jatuh_tempo ?? '—' }}
                                <span v-if="t.pengingat_terakhir_pada" class="block text-[10px] text-muted">
                                    Diingatkan {{ t.pengingat_terakhir_pada }}<span v-if="t.pengingat_terkirim > 1"> ({{ t.pengingat_terkirim }}×)</span>
                                </span>
                            </td>
                            <td class="py-2">
                                <div v-if="!['lunas', 'dibebaskan'].includes(t.status)" class="flex flex-wrap justify-end gap-1">
                                    <button type="button" class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold" @click="catatTunai(t)">Tunai</button>
                                    <button type="button" class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold" @click="bukaBebas(t)">
                                        {{ bebasUntuk === t.id ? 'Tutup' : 'Bebaskan' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <form v-if="bebasUntuk" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimBebas(props.rekap!.tagihan.find((x) => x.id === bebasUntuk)!)">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Alasan pembebasan *</span>
                        <textarea v-model="formBebas.dibebaskan_alasan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                        <span class="text-[11px] text-muted">Tagihan yang hangus tanpa alasan tidak bisa dipertanggungjawabkan.</span>
                        <span v-if="formBebas.errors.dibebaskan_alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ formBebas.errors.dibebaskan_alasan }}</span>
                    </label>

                    <div class="mt-2 flex gap-2">
                        <button type="submit" :disabled="formBebas.processing" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Tandai Dibebaskan</button>
                        <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="bebasUntuk = null">Batal</button>
                    </div>
                </form>
            </section>
        </div>
    </PanelLayout>
</template>
