<script setup lang="ts">
/*
 * Aspirasi dari sisi pengurus.
 *
 * INI SATU-SATUNYA TEMPAT IDENTITAS PENGIRIM MUNCUL, dan itupun hanya bila
 * pengguna berhak (`aspirations.view-identity`). Ketika tidak berhak, kolom
 * identitas memang TIDAK DIKIRIM server — bukan dikirim lalu disembunyikan,
 * karena data yang sampai ke peramban bisa dibaca siapa pun.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Aspirasi {
    id: number;
    nomor_tiket: string;
    judul: string;
    isi: string;
    kategori: string;
    label_kategori: string;
    status: string;
    label_status: string;
    tampil_publik: boolean;
    tanggapan: string | null;
    penanggap: string | null;
    ditanggapi_pada: string | null;
    dibuat: string | null;
    nama_pengirim: string | null;
    email_pengirim: string | null;
    telepon_pengirim: string | null;
    punya_akun: boolean;
    perlu_diperiksa: boolean;
}

const props = defineProps<{
    daftar: Aspirasi[];
    rekap: { baru: number; dibaca: number; diproses: number; selesai: number; ditolak: number; total: number; belum_ditanggapi: number };
    pilihanStatus: Record<string, string>;
    pilihanKategori: Record<string, string>;
    saringan: { status: string; kategori: string };
    bolehLihatIdentitas: boolean;
    catatan: string;
}>();

const statusSaring = ref(props.saringan.status);
const kategoriSaring = ref(props.saringan.kategori);
const balasUntuk = ref<number | null>(null);
const tutupUntuk = ref<number | null>(null);

const formBalas = useForm({ tanggapan: '', status: 'diproses' });
const formTutup = useForm({ tanggapan: '' });

function saring(): void {
    router.get('/panel/aspirasi', { status: statusSaring.value, kategori: kategoriSaring.value }, { preserveScroll: true });
}

function baca(a: Aspirasi): void {
    router.post(`/panel/aspirasi/${a.id}/baca`, {}, { preserveScroll: true });
}

function ubahTampil(a: Aspirasi, nilai: boolean): void {
    router.post(`/panel/aspirasi/${a.id}/tampil`, { tampil_publik: nilai }, { preserveScroll: true });
}

function bukaBalas(a: Aspirasi): void {
    balasUntuk.value = balasUntuk.value === a.id ? null : a.id;
    tutupUntuk.value = null;
    formBalas.tanggapan = a.tanggapan ?? '';
    formBalas.status = a.status === 'selesai' ? 'selesai' : 'diproses';
}

function bukaTutup(a: Aspirasi): void {
    tutupUntuk.value = tutupUntuk.value === a.id ? null : a.id;
    balasUntuk.value = null;
    formTutup.tanggapan = a.tanggapan ?? '';
}

function kirimBalas(a: Aspirasi): void {
    formBalas.post(`/panel/aspirasi/${a.id}/tanggapi`, {
        preserveScroll: true,
        onSuccess: () => (balasUntuk.value = null),
    });
}

function kirimTutup(a: Aspirasi): void {
    formTutup.post(`/panel/aspirasi/${a.id}/tutup`, {
        preserveScroll: true,
        onSuccess: () => (tutupUntuk.value = null),
    });
}
</script>

<template>
    <Head title="Aspirasi" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Aspirasi</h1>
                    <p class="text-sm text-muted">Aspirasi yang masuk dari kader dan pengunjung situs.</p>
                </div>

                <a href="/panel/aspirasi/ekspor" class="brutal-sm brutal-hover bg-paper-alt px-4 py-2 text-sm font-bold">Ekspor CSV</a>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

            <p v-if="!props.bolehLihatIdentitas" class="brutal bg-accent-400 p-4 text-xs font-bold">
                Kamu tidak memegang izin melihat identitas pengirim. Kolom nama, email, dan telepon tidak ditampilkan.
            </p>

            <!-- ===== Rekap ===== -->
            <section class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="brutal bg-accent-100 p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Belum Ditanggapi</p>
                    <p class="font-display text-2xl">{{ props.rekap.belum_ditanggapi }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Baru</p>
                    <p class="font-display text-2xl">{{ props.rekap.baru }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Dibaca</p>
                    <p class="font-display text-2xl">{{ props.rekap.dibaca }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Diproses</p>
                    <p class="font-display text-2xl">{{ props.rekap.diproses }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Selesai</p>
                    <p class="font-display text-2xl">{{ props.rekap.selesai }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Total</p>
                    <p class="font-display text-2xl">{{ props.rekap.total }}</p>
                </div>
            </section>

            <!-- ===== Saringan ===== -->
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Status</span>
                    <select v-model="statusSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua status</option>
                        <option v-for="(label, kunci) in props.pilihanStatus" :key="kunci" :value="kunci">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                    <select v-model="kategoriSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua kategori</option>
                        <option v-for="(label, kunci) in props.pilihanKategori" :key="kunci" :value="kunci">{{ label }}</option>
                    </select>
                </label>
            </div>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-5 text-sm text-muted">
                Tidak ada aspirasi yang cocok dengan saringan ini.
            </p>

            <!-- ===== Daftar aspirasi ===== -->
            <ul v-else class="space-y-4">
                <li
                    v-for="a in props.daftar"
                    :key="a.id"
                    class="brutal bg-paper p-5"
                    :class="!a.tanggapan ? 'border-l-8 border-l-accent-400' : ''"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs text-muted">{{ a.nomor_tiket }} · {{ a.label_kategori }} · {{ a.dibuat }}</p>
                            <h2 class="font-display text-lg leading-tight">{{ a.judul }}</h2>

                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ a.isi }}</p>

                            <div v-if="props.bolehLihatIdentitas" class="mt-3 border-2 border-ink bg-paper-alt p-3 text-xs">
                                <p class="text-[10px] font-bold uppercase text-muted">Identitas pengirim</p>
                                <p class="mt-1 font-bold">{{ a.nama_pengirim }}</p>
                                <p>{{ a.email_pengirim }}</p>
                                <p v-if="a.telepon_pengirim">{{ a.telepon_pengirim }}</p>
                                <p class="mt-1 text-[10px] text-muted">
                                    Tidak pernah tayang di papan publik.
                                    <span v-if="a.punya_akun"> · Punya akun anggota.</span>
                                </p>
                            </div>
                            <p v-else class="mt-3 text-[11px] text-muted">Identitas pengirim tidak ditampilkan untukmu.</p>

                            <p v-if="a.perlu_diperiksa" class="mt-2 border-2 border-ink bg-accent-400 px-2 py-1 text-[10px] font-bold uppercase">
                                Isinya memuat data pribadi — otomatis dibersihkan saat tayang
                            </p>
                        </div>

                        <div class="text-right text-xs">
                            <span class="border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase" :class="a.tanggapan ? 'bg-accent-100' : 'bg-paper-alt'">
                                {{ a.label_status }}
                            </span>
                            <p v-if="!a.tampil_publik" class="mt-2 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">
                                Tidak ditayangkan
                            </p>
                        </div>
                    </div>

                    <p v-if="a.tanggapan" class="mt-3 border-t-2 border-ink/10 pt-3 text-sm">
                        <span class="text-[10px] font-bold uppercase text-muted">Tanggapan</span><br>
                        <span class="whitespace-pre-line">{{ a.tanggapan }}</span>
                        <span class="mt-1 block text-[10px] text-muted">
                            {{ a.penanggap }} · {{ a.ditanggapi_pada }}
                        </span>
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button v-if="a.status === 'baru'" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="baca(a)">
                            Tandai Dibaca
                        </button>

                        <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="bukaBalas(a)">
                            {{ balasUntuk === a.id ? 'Tutup' : (a.tanggapan ? 'Perbarui Tanggapan' : 'Tanggapi') }}
                        </button>

                        <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="bukaTutup(a)">
                            {{ tutupUntuk === a.id ? 'Tutup' : 'Tutup Tanpa Tindak Lanjut' }}
                        </button>

                        <button type="button" class="brutal-sm brutal-hover bg-paper px-3 py-1.5 text-xs font-bold" @click="ubahTampil(a, !a.tampil_publik)">
                            {{ a.tampil_publik ? 'Sembunyikan dari Papan' : 'Tayangkan di Papan' }}
                        </button>
                    </div>

                    <form v-if="balasUntuk === a.id" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimBalas(a)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Tanggapan *</span>
                            <textarea v-model="formBalas.tanggapan" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            <span class="text-[11px] text-muted">Tanggapan ini tayang di papan publik, jadi tulis seolah dibaca kader lain.</span>
                            <span v-if="formBalas.errors.tanggapan" class="mt-1 block text-xs font-bold text-accent-600">{{ formBalas.errors.tanggapan }}</span>
                        </label>

                        <label class="mt-3 block max-w-xs">
                            <span class="text-xs font-bold uppercase text-muted">Status *</span>
                            <select v-model="formBalas.status" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option value="diproses">Sedang Diproses</option>
                                <option value="selesai">Selesai</option>
                            </select>
                            <span v-if="formBalas.errors.status" class="mt-1 block text-xs font-bold text-accent-600">{{ formBalas.errors.status }}</span>
                        </label>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" :disabled="formBalas.processing" class="brutal-sm bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800">
                                Simpan Tanggapan
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="balasUntuk = null">Batal</button>
                        </div>
                    </form>

                    <form v-if="tutupUntuk === a.id" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimTutup(a)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Alasan tidak ditindaklanjuti *</span>
                            <textarea v-model="formTutup.tanggapan" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            <span class="text-[11px] text-muted">
                                Alasan ini DIBACA pengirimnya. Aspirasi yang ditutup tanpa keterangan tidak bisa dipertanggungjawabkan.
                            </span>
                            <span v-if="formTutup.errors.tanggapan" class="mt-1 block text-xs font-bold text-accent-600">{{ formTutup.errors.tanggapan }}</span>
                        </label>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" :disabled="formTutup.processing" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Tutup Aspirasi</button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="tutupUntuk = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
