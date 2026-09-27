<script setup lang="ts">
/*
 * Pengelolaan peminjaman.
 *
 * Urutan tombol mengikuti alur nyata di loket: setujui → serahkan → terima
 * kembali. Setiap tombol yang mengubah status meminta konfirmasi karena
 * kesalahannya berujung pada email yang salah ke anggota.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Pinjaman {
    id: number;
    kode_pinjam: string;
    barang: string;
    jenis: string;
    label_jenis: string;
    peminjam: string;
    peminjam_kontak: string | null;
    peminjam_instansi: string | null;
    penanggung_jawab: string | null;
    jumlah: number;
    status: string;
    label_status: string;
    jatuh_tempo: string | null;
    sedang_dipinjam: boolean;
    terlambat: boolean;
    hari_terlambat: number;
    sisa_hari: number | null;
    perpanjangan_ke: number;
    perpanjangan_diajukan: number | null;
    perpanjangan_alasan: string | null;
    kondisi_keluar: string;
    kondisi_masuk: string | null;
    catatan_peminjam: string | null;
    catatan_petugas: string | null;
    diajukan_pada: string | null;
}

const props = defineProps<{
    daftar: Pinjaman[];
    saring: { status: string; jenis: string; cari: string };
    status: Record<string, string>;
    kondisiKeluar: Record<string, string>;
    kondisiMasuk: Record<string, string>;
    jenis: Record<string, string>;
    ringkasan: Record<string, number>;
    antrian: { id: number; judul: string | null; anggota: string | null; status: string; label_status: string; posisi: number; kedaluwarsa_pada: string | null }[];
    pilihanBuku: { id: number; label: string }[];
    pilihanAset: { id: number; label: string }[];
    pilihanAnggota: { id: number; label: string }[];
}>();

const cari = ref(props.saring.cari);
const status = ref(props.saring.status);
const jenis = ref(props.saring.jenis);
const formEksternal = ref(false);

/*
 * Tindakan yang butuh isian petugas (tolak, serah terima, pengembalian,
 * tolak perpanjangan) dijalankan lewat panel kecil di baris pinjaman.
 *
 * SEBELUMNYA memakai window.prompt(). Itu keliru karena: prompt diblokir di
 * konteks ber-sandbox (halaman jadi diam saja tanpa penjelasan), teksnya bebas
 * sehingga "Baik" atau "rusak ringan" ditolak validasi, dan nilainya tidak
 * pernah terlihat lagi setelah dikirim. Pilihan kondisi kini dropdown.
 */
type JenisAksi = 'tolak' | 'serahkan' | 'kembalikan' | 'tolakPerpanjangan';

interface Aksi {
    jenis: JenisAksi;
    item: Pinjaman;
}

const aksi = ref<Aksi | null>(null);

const formAksi = useForm({
    catatan_petugas: '',
    kondisi_keluar: 'baik',
    kondisi_masuk: 'baik',
});

const JUDUL_AKSI: Record<JenisAksi, string> = {
    tolak: 'Alasan menolak pengajuan',
    serahkan: 'Kondisi barang saat diserahkan',
    kembalikan: 'Kondisi barang saat dikembalikan',
    tolakPerpanjangan: 'Alasan menolak perpanjangan',
};

function mulaiAksi(jenis: JenisAksi, item: Pinjaman): void {
    if (aksi.value?.jenis === jenis && aksi.value.item.id === item.id) {
        aksi.value = null;

        return;
    }

    aksi.value = { jenis, item };
    formAksi.reset();
    formAksi.clearErrors();
}

function kirimAksi(): void {
    if (!aksi.value) {
        return;
    }

    const { jenis: macam, item } = aksi.value;

    const tujuan: Record<JenisAksi, string> = {
        tolak: `/panel/peminjaman/${item.id}/tolak`,
        serahkan: `/panel/peminjaman/${item.id}/serahkan`,
        kembalikan: `/panel/peminjaman/${item.id}/kembalikan`,
        tolakPerpanjangan: `/panel/peminjaman/perpanjangan/${item.perpanjangan_diajukan}/tolak`,
    };

    const muatan: Record<string, unknown> = {};

    if (macam === 'serahkan') {
        muatan.kondisi_keluar = formAksi.kondisi_keluar;
    }

    if (macam === 'kembalikan') {
        muatan.kondisi_masuk = formAksi.kondisi_masuk;
    }

    if (macam === 'tolak' || macam === 'tolakPerpanjangan') {
        muatan.catatan_petugas = formAksi.catatan_petugas;
    }

    formAksi.transform(() => muatan).post(tujuan[macam], {
        preserveScroll: true,
        onSuccess: () => {
            aksi.value = null;
            formAksi.reset();
        },
    });
}

const form = useForm({
    book_id: null as number | null,
    inventory_item_id: null as number | null,
    jumlah: 1,
    peminjam_nama: '',
    peminjam_kontak: '',
    peminjam_instansi: '',
    penanggung_jawab_id: null as number | null,
    catatan_peminjam: '',
});

function saringkan(): void {
    router.get('/panel/peminjaman', { cari: cari.value, status: status.value, jenis: jenis.value }, { preserveState: true, preserveScroll: true });
}

function reset(): void {
    cari.value = '';
    status.value = '';
    jenis.value = '';
    saringkan();
}

function kirim(tautan: string, muatan: Record<string, unknown> = {}): void {
    router.post(tautan, muatan, { preserveScroll: true });
}

function setujui(item: Pinjaman): void {
    if (!confirm(`Setujui peminjaman ${item.kode_pinjam} oleh ${item.peminjam}? Jatuh tempo akan ditetapkan otomatis.`)) {
        return;
    }

    kirim(`/panel/peminjaman/${item.id}/setujui`);
}

function tolak(item: Pinjaman): void {
    mulaiAksi('tolak', item);
}

function serahkan(item: Pinjaman): void {
    mulaiAksi('serahkan', item);
}

function kembalikan(item: Pinjaman): void {
    mulaiAksi('kembalikan', item);
}

function batalkan(item: Pinjaman): void {
    if (!confirm(`Batalkan peminjaman ${item.kode_pinjam}? Eksemplar akan dilepas kembali.`)) {
        return;
    }

    kirim(`/panel/peminjaman/${item.id}/batalkan`);
}

function setujuiPerpanjangan(item: Pinjaman): void {
    if (!item.perpanjangan_diajukan) {
        return;
    }

    kirim(`/panel/peminjaman/perpanjangan/${item.perpanjangan_diajukan}/setujui`);
}

function tolakPerpanjangan(item: Pinjaman): void {
    if (!item.perpanjangan_diajukan) {
        return;
    }

    mulaiAksi('tolakPerpanjangan', item);
}

function simpanEksternal(): void {
    form.post('/panel/peminjaman/eksternal', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            formEksternal.value = false;
        },
    });
}
</script>

<template>
    <PanelLayout>
        <Head title="Peminjaman" />

        <div class="mx-auto max-w-6xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Peminjaman</h1>
                    <p class="mt-1 text-sm text-muted">
                        Buku dan aset. Eksemplar dikunci sejak disetujui, dan antrian naik otomatis saat dikembalikan.
                        <span class="font-bold">Tanpa denda.</span>
                    </p>
                </div>

                <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800" @click="formEksternal = !formEksternal">
                    {{ formEksternal ? 'Tutup' : 'Catat Peminjaman Eksternal' }}
                </button>
            </div>

            <!-- Ringkasan -->
            <ul class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <li class="brutal bg-paper p-4">
                    <p class="font-display text-2xl">{{ props.ringkasan.diajukan }}</p>
                    <p class="text-xs font-bold uppercase text-muted">Menunggu Persetujuan</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="font-display text-2xl">{{ props.ringkasan.dipinjam }}</p>
                    <p class="text-xs font-bold uppercase text-muted">Sedang Dipinjam</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="font-display text-2xl text-accent-600">{{ props.ringkasan.terlambat }}</p>
                    <p class="text-xs font-bold uppercase text-muted">Terlambat</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="font-display text-2xl">{{ props.ringkasan.perpanjangan }}</p>
                    <p class="text-xs font-bold uppercase text-muted">Perpanjangan</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="font-display text-2xl">{{ props.ringkasan.antrian_siap }}</p>
                    <p class="text-xs font-bold uppercase text-muted">Siap Diambil</p>
                </li>
            </ul>

            <!-- Peminjaman eksternal -->
            <form v-if="formEksternal" class="brutal bg-paper p-5" @submit.prevent="simpanEksternal">
                <h2 class="font-display text-lg">Catat Peminjaman Eksternal</h2>
                <p class="mt-1 text-sm text-muted">
                    Pihak luar boleh meminjam, tetapi wajib ada anggota rayon yang bertanggung jawab.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block lg:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Nama peminjam *</span>
                        <input v-model="form.peminjam_nama" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.peminjam_nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.peminjam_nama }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Kontak</span>
                        <input v-model="form.peminjam_kontak" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Instansi</span>
                        <input v-model="form.peminjam_instansi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block lg:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Penanggung jawab internal *</span>
                        <select v-model="form.penanggung_jawab_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— pilih anggota —</option>
                            <option v-for="orang in props.pilihanAnggota" :key="orang.id" :value="orang.id">{{ orang.label }}</option>
                        </select>
                        <span v-if="form.errors.penanggung_jawab_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.penanggung_jawab_id }}</span>
                    </label>

                    <label class="block lg:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Buku</span>
                        <select v-model="form.book_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— tidak meminjam buku —</option>
                            <option v-for="buku in props.pilihanBuku" :key="buku.id" :value="buku.id">{{ buku.label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Aset</span>
                        <select v-model="form.inventory_item_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— tidak meminjam aset —</option>
                            <option v-for="aset in props.pilihanAset" :key="aset.id" :value="aset.id">{{ aset.label }}</option>
                        </select>
                    </label>

                    <label class="block lg:col-span-3">
                        <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                        <input v-model="form.catatan_peminjam" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>
                </div>

                <div class="mt-4 flex gap-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Catat Peminjaman
                    </button>
                    <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="formEksternal = false">Batal</button>
                </div>
            </form>

            <!-- Saringan -->
            <form class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-4" @submit.prevent="saringkan">
                <label class="block sm:col-span-2">
                    <span class="text-xs font-bold uppercase text-muted">Cari</span>
                    <input v-model="cari" type="search" placeholder="kode pinjam atau nama peminjam" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Status</span>
                    <select v-model="status" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua status</option>
                        <option v-for="(label, nilai) in props.status" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis</span>
                    <select v-model="jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua jenis</option>
                        <option v-for="(label, nilai) in props.jenis" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <div class="flex items-end gap-2 sm:col-span-4">
                    <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Terapkan</button>
                    <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="reset">Reset</button>
                </div>
            </form>

            <!-- Antrian -->
            <section v-if="props.antrian.length" class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Daftar Tunggu ({{ props.antrian.length }})</h2>
                <ul class="mt-3 divide-y-2 divide-ink/10 text-sm">
                    <li v-for="a in props.antrian" :key="a.id" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <div>
                            <p class="font-bold">{{ a.judul }} — {{ a.anggota }}</p>
                            <p class="text-xs text-muted">
                                Antrian #{{ a.posisi }} · {{ a.label_status }}
                                <template v-if="a.kedaluwarsa_pada"> · berlaku sampai {{ a.kedaluwarsa_pada }}</template>
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Daftar peminjaman -->
            <p v-if="!props.daftar.length" class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                Belum ada peminjaman yang cocok dengan saringan ini.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="item in props.daftar" :key="item.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                {{ item.peminjam }}
                                <span :class="['border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', item.sedang_dipinjam ? 'bg-accent-100' : 'bg-paper-alt']">
                                    {{ item.label_status }}
                                </span>
                                <span v-if="item.jenis === 'eksternal'" class="ml-1 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">
                                    {{ item.label_jenis }}
                                </span>
                                <span v-if="item.terlambat" class="ml-1 border-2 border-ink bg-accent-100 px-2 py-0.5 text-[10px] font-bold uppercase">
                                    Terlambat {{ item.hari_terlambat }} hari
                                </span>
                            </p>
                            <p class="mt-1 text-xs text-muted">
                                <span class="font-mono">{{ item.kode_pinjam }}</span> · {{ item.barang }}
                            </p>
                            <p class="text-xs text-muted">
                                Diajukan {{ item.diajukan_pada }}
                                <template v-if="item.jatuh_tempo"> · jatuh tempo {{ item.jatuh_tempo }}</template>
                                <template v-if="item.penanggung_jawab"> · penanggung jawab {{ item.penanggung_jawab }}</template>
                                <template v-if="item.perpanjangan_ke > 0"> · perpanjangan ke-{{ item.perpanjangan_ke }}</template>
                            </p>
                            <p v-if="item.catatan_peminjam" class="mt-1 text-xs">
                                <span class="font-bold uppercase">Catatan peminjam:</span> {{ item.catatan_peminjam }}
                            </p>
                            <p v-if="item.catatan_petugas" class="text-xs">
                                <span class="font-bold uppercase">Catatan petugas:</span> {{ item.catatan_petugas }}
                            </p>
                            <p v-if="item.perpanjangan_diajukan" class="mt-1 text-xs font-bold text-accent-600">
                                Mengajukan perpanjangan: {{ item.perpanjangan_alasan || '(tanpa alasan)' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button v-if="item.status === 'diajukan'" type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="setujui(item)">Setujui</button>
                            <button v-if="item.status === 'diajukan'" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="tolak(item)">Tolak</button>

                            <button v-if="item.status === 'disetujui'" type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="serahkan(item)">Serah Terima</button>

                            <button v-if="item.status === 'dipinjam'" type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="kembalikan(item)">Terima Kembali</button>

                            <button v-if="item.perpanjangan_diajukan" type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="setujuiPerpanjangan(item)">Setujui Perpanjangan</button>
                            <button v-if="item.perpanjangan_diajukan" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="tolakPerpanjangan(item)">Tolak Perpanjangan</button>

                            <button v-if="item.status === 'diajukan' || item.status === 'disetujui'" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="batalkan(item)">Batalkan</button>
                        </div>
                    </div>

                    <!-- Panel isian petugas: menggantikan window.prompt() -->
                    <form
                        v-if="aksi && aksi.item.id === item.id"
                        class="mt-4 grid gap-3 border-t-2 border-ink/10 pt-4 sm:grid-cols-3"
                        @submit.prevent="kirimAksi"
                    >
                        <p class="text-sm font-bold sm:col-span-3">
                            {{ JUDUL_AKSI[aksi.jenis] }}
                            <span class="font-normal text-muted">— {{ item.kode_pinjam }}</span>
                        </p>

                        <label v-if="aksi.jenis === 'serahkan'" class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kondisi saat diserahkan *</span>
                            <select v-model="formAksi.kondisi_keluar" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, nilai) in props.kondisiKeluar" :key="nilai" :value="nilai">{{ label }}</option>
                            </select>
                            <span v-if="formAksi.errors.kondisi_keluar" class="mt-1 block text-xs font-bold text-accent-600">{{ formAksi.errors.kondisi_keluar }}</span>
                        </label>

                        <label v-if="aksi.jenis === 'kembalikan'" class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kondisi saat dikembalikan *</span>
                            <select v-model="formAksi.kondisi_masuk" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, nilai) in props.kondisiMasuk" :key="nilai" :value="nilai">{{ label }}</option>
                            </select>
                            <span v-if="formAksi.errors.kondisi_masuk" class="mt-1 block text-xs font-bold text-accent-600">{{ formAksi.errors.kondisi_masuk }}</span>
                        </label>

                        <label v-if="aksi.jenis === 'tolak' || aksi.jenis === 'tolakPerpanjangan'" class="block sm:col-span-3">
                            <span class="text-xs font-bold uppercase text-muted">Catatan untuk peminjam *</span>
                            <textarea v-model="formAksi.catatan_petugas" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            <span v-if="formAksi.errors.catatan_petugas" class="mt-1 block text-xs font-bold text-accent-600">{{ formAksi.errors.catatan_petugas }}</span>
                        </label>

                        <div class="flex flex-wrap gap-2 sm:col-span-3">
                            <button type="submit" :disabled="formAksi.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                {{ aksi.jenis === 'tolak' || aksi.jenis === 'tolakPerpanjangan' ? 'Kirim Penolakan' : 'Catat' }}
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="aksi = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
