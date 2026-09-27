<script setup lang="ts">
/*
 * Antrean verifikasi prestasi.
 *
 * YANG MENUNGGU DIPERIKSA DILETAKKAN PALING ATAS — hampir semua pekerjaan di
 * halaman ini adalah memutuskan klaim yang baru masuk.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Prestasi {
    id: number;
    anggota: string;
    nomor_anggota: string | null;
    unit: string | null;
    judul: string;
    kategori: string | null;
    penyelenggara: string | null;
    tingkat: string;
    label_tingkat: string;
    peringkat: string;
    label_peringkat: string;
    tanggal: string | null;
    status: string;
    label_status: string;
    poin: number;
    poin_diberikan: number;
    unggulan: boolean;
    tampil_publik: boolean;
    sertifikat_url: string | null;
    tautan_bukti: string | null;
    catatan_verifikasi: string | null;
    pemeriksa: string | null;
    diverifikasi_pada: string | null;
}

interface Kategori {
    id: number;
    kode: string;
    nama: string;
    aktif: boolean;
    jumlah_prestasi: number;
    jumlah_terverifikasi: number;
}

const props = defineProps<{
    daftar: Prestasi[];
    kategori: Kategori[];
    pilihanTingkat: Record<string, string>;
    pilihanPeringkat: Record<string, string>;
    pilihanStatus: Record<string, string>;
    ringkasan: { menunggu: number; terverifikasi: number; ditolak: number; unggulan: number };
    saringan: { status: string; tingkat: string; kategori: number | null };
    catatan: string;
}>();

const statusSaring = ref(props.saringan.status);
const tingkatSaring = ref(props.saringan.tingkat);
const tolakUntuk = ref<number | null>(null);
const bukaKategori = ref(false);

const formTolak = useForm({ alasan: '' });
const formKategori = useForm({ nama: '', keterangan: '', urutan: 0 });

function saring(): void {
    router.get('/panel/prestasi', { status: statusSaring.value, tingkat: tingkatSaring.value }, { preserveScroll: true });
}

function verifikasi(p: Prestasi): void {
    router.post(`/panel/prestasi/${p.id}/verifikasi`, {}, { preserveScroll: true });
}

function unggulan(p: Prestasi, nilai: boolean): void {
    router.post(`/panel/prestasi/${p.id}/unggulan`, { unggulan: nilai }, { preserveScroll: true });
}

function hapus(p: Prestasi): void {
    if (!confirm(`Hapus pengajuan "${p.judul}"? Hanya pengajuan yang belum diperiksa yang dapat dihapus.`)) {
        return;
    }

    router.delete(`/panel/prestasi/${p.id}`, { preserveScroll: true });
}

function kirimTolak(p: Prestasi): void {
    formTolak.post(`/panel/prestasi/${p.id}/tolak`, {
        preserveScroll: true,
        onSuccess: () => {
            tolakUntuk.value = null;
            formTolak.reset();
        },
    });
}

function simpanKategori(): void {
    formKategori.post('/panel/prestasi/kategori', {
        preserveScroll: true,
        onSuccess: () => {
            formKategori.reset();
            bukaKategori.value = false;
        },
    });
}
</script>

<template>
    <Head title="Prestasi Kader" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Prestasi Kader</h1>
                    <p class="text-sm text-muted">Klaim prestasi yang diajukan kader, menunggu diperiksa.</p>
                </div>

                <a
                    :href="`/panel/prestasi/ekspor${statusSaring ? `?status=${statusSaring}` : ''}`"
                    class="brutal-sm brutal-hover bg-paper-alt px-4 py-2 text-sm font-bold"
                >Ekspor CSV</a>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

            <!-- ===== Ringkasan ===== -->
            <section class="grid gap-3 sm:grid-cols-4">
                <div class="brutal bg-accent-100 p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Menunggu</p>
                    <p class="font-display text-2xl">{{ props.ringkasan.menunggu }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Terverifikasi</p>
                    <p class="font-display text-2xl">{{ props.ringkasan.terverifikasi }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Ditolak</p>
                    <p class="font-display text-2xl">{{ props.ringkasan.ditolak }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Unggulan</p>
                    <p class="font-display text-2xl">{{ props.ringkasan.unggulan }}</p>
                </div>
            </section>

            <!-- ===== Kategori ===== -->
            <section class="brutal bg-paper p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-lg">Kategori</h2>
                    <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="bukaKategori = !bukaKategori">
                        {{ bukaKategori ? 'Tutup' : 'Tambah Kategori' }}
                    </button>
                </div>

                <form v-if="bukaKategori" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="simpanKategori">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                            <input v-model="formKategori.nama" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formKategori.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ formKategori.errors.nama }}</span>
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                            <input v-model="formKategori.keterangan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                            <input v-model.number="formKategori.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>
                    </div>

                    <button type="submit" :disabled="formKategori.processing" class="brutal-sm brutal-hover mt-3 bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800">
                        Simpan Kategori
                    </button>
                </form>

                <p v-if="!props.kategori.length" class="mt-2 text-sm text-muted">Belum ada kategori.</p>

                <ul v-else class="mt-3 flex flex-wrap gap-2">
                    <li v-for="k in props.kategori" :key="k.id" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs">
                        <span class="font-bold">{{ k.nama }}</span>
                        <span class="ml-2 text-muted">{{ k.jumlah_terverifikasi }}/{{ k.jumlah_prestasi }} terverifikasi</span>
                        <span v-if="!k.aktif" class="ml-2 font-bold text-accent-600">Nonaktif</span>
                    </li>
                </ul>
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
                    <span class="text-xs font-bold uppercase text-muted">Tingkat</span>
                    <select v-model="tingkatSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua tingkat</option>
                        <option v-for="(label, kunci) in props.pilihanTingkat" :key="kunci" :value="kunci">{{ label }}</option>
                    </select>
                </label>
            </div>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-5 text-sm text-muted">
                Tidak ada prestasi yang cocok dengan saringan ini.
            </p>

            <!-- ===== Daftar prestasi ===== -->
            <ul v-else class="space-y-4">
                <li
                    v-for="p in props.daftar"
                    :key="p.id"
                    class="brutal bg-paper p-5"
                    :class="p.status === 'diajukan' ? 'border-l-8 border-l-accent-400' : ''"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase text-muted">{{ p.label_tingkat }} · {{ p.label_peringkat }}</p>
                            <h3 class="font-display text-lg leading-tight">{{ p.judul }}</h3>
                            <p class="mt-1 text-sm">
                                <span class="font-bold">{{ p.anggota }}</span>
                                <span v-if="p.nomor_anggota" class="ml-1 font-mono text-[10px] text-muted">{{ p.nomor_anggota }}</span>
                                <span v-if="p.unit" class="ml-2 text-muted">{{ p.unit }}</span>
                            </p>
                            <p class="mt-1 text-xs text-muted">
                                {{ p.penyelenggara ?? 'Penyelenggara tidak disebutkan' }} · {{ p.tanggal }}
                                <span v-if="p.kategori"> · {{ p.kategori }}</span>
                            </p>

                            <p class="mt-2 flex flex-wrap gap-2 text-[10px] font-bold uppercase">
                                <span class="border-2 border-ink px-2 py-0.5" :class="p.status === 'terverifikasi' ? 'bg-accent-100' : 'bg-paper-alt'">
                                    {{ p.label_status }}
                                </span>
                                <span v-if="p.poin > 0" class="border-2 border-ink bg-paper-alt px-2 py-0.5">{{ p.poin }} poin</span>
                                <span v-if="p.unggulan" class="border-2 border-ink bg-accent-400 px-2 py-0.5">Unggulan</span>
                                <span v-if="!p.tampil_publik" class="border-2 border-ink bg-paper-alt px-2 py-0.5">Disembunyikan kader</span>
                            </p>

                            <p v-if="p.catatan_verifikasi" class="mt-2 text-xs">
                                <strong>Catatan:</strong> {{ p.catatan_verifikasi }}
                                <span v-if="p.pemeriksa" class="text-muted"> ({{ p.pemeriksa }}<span v-if="p.diverifikasi_pada">, {{ p.diverifikasi_pada }}</span>)</span>
                            </p>
                        </div>

                        <div class="text-right text-xs">
                            <a v-if="p.sertifikat_url" :href="p.sertifikat_url" target="_blank" rel="noopener" class="brutal-sm bg-paper-alt px-3 py-1.5 font-bold">
                                Buka Sertifikat
                            </a>
                            <a v-else-if="p.tautan_bukti" :href="p.tautan_bukti" target="_blank" rel="noopener" class="brutal-sm bg-paper-alt px-3 py-1.5 font-bold">
                                Buka Tautan Bukti
                            </a>
                            <p v-else class="brutal-sm bg-accent-100 px-3 py-1.5 font-bold">Tanpa Bukti</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="p.status !== 'terverifikasi'"
                            type="button"
                            class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                            @click="verifikasi(p)"
                        >Verifikasi</button>

                        <button
                            v-if="p.status === 'terverifikasi'"
                            type="button"
                            class="brutal-sm brutal-hover px-3 py-1.5 text-xs font-bold"
                            :class="p.unggulan ? 'bg-paper-alt' : 'bg-accent-400 text-primary-800'"
                            @click="unggulan(p, !p.unggulan)"
                        >{{ p.unggulan ? 'Batalkan Unggulan' : 'Jadikan Unggulan' }}</button>

                        <button
                            v-if="p.status !== 'ditolak'"
                            type="button"
                            class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                            @click="tolakUntuk = tolakUntuk === p.id ? null : p.id"
                        >{{ tolakUntuk === p.id ? 'Tutup' : 'Tolak' }}</button>

                        <button
                            v-if="p.status === 'diajukan'"
                            type="button"
                            class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold"
                            @click="hapus(p)"
                        >Hapus Pengajuan</button>
                    </div>

                    <form v-if="tolakUntuk === p.id" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimTolak(p)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Alasan penolakan *</span>
                            <textarea v-model="formTolak.alasan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            <span class="text-[11px] text-muted">
                                Kader melihat alasan ini. Bila prestasi ini sebelumnya sempat terverifikasi, poinnya ikut dicabut.
                            </span>
                            <span v-if="formTolak.errors.alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ formTolak.errors.alasan }}</span>
                        </label>

                        <div class="mt-2 flex gap-2">
                            <button type="submit" :disabled="formTolak.processing" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Tolak Prestasi</button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="tolakUntuk = null">Tutup</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
