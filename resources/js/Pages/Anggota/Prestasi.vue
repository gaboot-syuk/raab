<script setup lang="ts">
/*
 * Prestasi saya.
 *
 * Kader melihat STATUS pengajuannya, bukan hanya daftar. Klaim yang ditolak
 * ditampilkan beserta alasan pengurusnya — kalau alasannya disembunyikan, kader
 * akan mengajukan hal yang sama lagi.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Prestasi {
    id: number;
    judul: string;
    deskripsi: string | null;
    kategori_id: number | null;
    kategori: string | null;
    penyelenggara: string | null;
    tingkat: string;
    label_tingkat: string;
    peringkat: string;
    label_peringkat: string;
    tanggal: string;
    tanggal_teks: string | null;
    status: string;
    label_status: string;
    catatan_verifikasi: string | null;
    poin_diharapkan: number;
    poin_diberikan: number;
    unggulan: boolean;
    tampil_publik: boolean;
    boleh_disunting: boolean;
    sertifikat_url: string | null;
    tautan_bukti: string | null;
}

const props = defineProps<{
    anggota: boolean;
    daftar: Prestasi[];
    kategori: { id: number; nama: string }[];
    pilihanTingkat: Record<string, string>;
    pilihanPeringkat: Record<string, string>;
    rekap: { total: number; per_tingkat: Record<string, number>; unggulan: number } | null;
    catatan?: string;
}>();

const bukaForm = ref(false);
const sunting = ref<number | null>(null);

const form = useForm({
    judul: '',
    deskripsi: '',
    achievement_category_id: null as number | null,
    penyelenggara: '',
    tingkat: 'kampus',
    peringkat: 'juara_1',
    tanggal: '',
    tautan_bukti: '',
    tampil_publik: true,
    sertifikat: null as File | null,
});

function isiForm(p: Prestasi): void {
    sunting.value = p.id;
    bukaForm.value = true;
    form.judul = p.judul;
    form.deskripsi = p.deskripsi ?? '';
    form.achievement_category_id = p.kategori_id;
    form.penyelenggara = p.penyelenggara ?? '';
    form.tingkat = p.tingkat;
    form.peringkat = p.peringkat;
    form.tanggal = p.tanggal;
    form.tautan_bukti = p.tautan_bukti ?? '';
    form.tampil_publik = p.tampil_publik;
    form.sertifikat = null;
}

function tutupForm(): void {
    bukaForm.value = false;
    sunting.value = null;
    form.reset();
}

function pilihBerkas(ev: Event): void {
    const input = ev.target as HTMLInputElement;
    form.sertifikat = input.files?.[0] ?? null;
}

function kirim(): void {
    const opsi = {
        preserveScroll: true,
        // Inertia butuh forceFormData agar berkas sertifikat ikut terkirim.
        forceFormData: true,
        onSuccess: () => tutupForm(),
    };

    // Route perbaikan memakai PUT; Inertia mengirimnya sebagai POST dengan
    // _method=PUT, jadi jangan dipaksa menjadi post().
    if (sunting.value) {
        form.put(`/prestasi-saya/${sunting.value}`, opsi);
        return;
    }

    form.post('/prestasi-saya', opsi);
}

function ubahTampil(p: Prestasi, nilai: boolean): void {
    router.post(`/prestasi-saya/${p.id}/tampil`, { tampil_publik: nilai }, { preserveScroll: true });
}

function tarik(p: Prestasi): void {
    if (!confirm(`Tarik pengajuan "${p.judul}"? Pengajuan yang belum diperiksa dapat ditarik.`)) {
        return;
    }

    router.delete(`/prestasi-saya/${p.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Prestasi Saya" />

    <AnggotaLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Prestasi Saya</h1>
                    <p class="text-sm text-muted">Ajukan prestasimu, lalu pantau keputusannya di sini.</p>
                </div>

                <button
                    v-if="props.anggota"
                    type="button"
                    class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                    @click="bukaForm ? tutupForm() : (bukaForm = true)"
                >{{ bukaForm ? 'Tutup Formulir' : 'Ajukan Prestasi' }}</button>
            </div>

            <p v-if="!props.anggota" class="brutal bg-paper p-5 text-sm text-muted">
                Akunmu belum terhubung ke data anggota, jadi prestasi belum bisa diajukan.
            </p>

            <template v-else>
                <p v-if="props.catatan" class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

                <!-- ===== Rekap terverifikasi ===== -->
                <section v-if="props.rekap" class="brutal bg-brand-dark p-5 text-on-brand">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-brand/70">Prestasi Terverifikasi</p>
                    <p class="mt-2 font-display text-4xl">{{ props.rekap.total }}</p>
                    <p v-if="props.rekap.unggulan > 0" class="mt-1 text-sm text-on-brand/85">
                        {{ props.rekap.unggulan }} di antaranya unggulan rayon.
                    </p>

                    <ul class="mt-3 flex flex-wrap gap-3 text-xs text-on-brand/85">
                        <li v-for="(jumlah, label) in props.rekap.per_tingkat" :key="label">{{ label }}: {{ jumlah }}</li>
                    </ul>
                </section>

                <!-- ===== Formulir pengajuan ===== -->
                <form v-if="bukaForm" class="brutal bg-paper p-5" @submit.prevent="kirim">
                    <h2 class="font-display text-lg">{{ sunting ? 'Perbaiki Pengajuan' : 'Ajukan Prestasi' }}</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Judul prestasi *</span>
                            <input v-model="form.judul" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.judul" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.judul }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                            <select v-model="form.achievement_category_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— Tanpa kategori —</option>
                                <option v-for="k in props.kategori" :key="k.id" :value="k.id">{{ k.nama }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Penyelenggara</span>
                            <input v-model="form.penyelenggara" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Tingkat *</span>
                            <select v-model="form.tingkat" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, kunci) in props.pilihanTingkat" :key="kunci" :value="kunci">{{ label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Peringkat *</span>
                            <select v-model="form.peringkat" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, kunci) in props.pilihanPeringkat" :key="kunci" :value="kunci">{{ label }}</option>
                            </select>
                            <span class="text-[11px] text-muted">Peringkat "Peserta" tidak menghasilkan poin.</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Tanggal *</span>
                            <input v-model="form.tanggal" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.tanggal" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.tanggal }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Sertifikat (jpg/png/webp/pdf, maks 5 MB)</span>
                            <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="pilihBerkas">
                            <span class="text-[11px] text-muted">Inilah yang diperiksa pengurus sebelum prestasimu diakui.</span>
                            <span v-if="form.errors.sertifikat" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.sertifikat }}</span>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Tautan bukti (opsional)</span>
                            <input v-model="form.tautan_bukti" type="url" placeholder="https://" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.tautan_bukti" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.tautan_bukti }}</span>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Deskripsi</span>
                            <textarea v-model="form.deskripsi" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                        </label>

                        <label class="flex items-center gap-2 sm:col-span-2">
                            <input v-model="form.tampil_publik" type="checkbox" class="h-4 w-4 border-2 border-ink">
                            <span class="text-sm">Tampilkan di halaman prestasi publik setelah terverifikasi</span>
                        </label>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800 disabled:opacity-40">
                            {{ sunting ? 'Simpan Perbaikan' : 'Ajukan' }}
                        </button>
                        <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="tutupForm">Batal</button>
                    </div>
                </form>

                <!-- ===== Daftar prestasi saya ===== -->
                <section>
                    <h2 class="font-display text-lg">Prestasi Saya</h2>

                    <p v-if="!props.daftar.length" class="mt-2 text-sm text-muted">
                        Belum ada prestasi yang diajukan.
                    </p>

                    <ul v-else class="mt-3 space-y-3">
                        <li v-for="p in props.daftar" :key="p.id" class="brutal bg-paper p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase text-muted">{{ p.label_tingkat }} · {{ p.label_peringkat }}</p>
                                    <p class="font-display text-lg leading-tight">{{ p.judul }}</p>
                                    <p class="mt-1 text-xs text-muted">
                                        {{ p.penyelenggara ?? 'Penyelenggara tidak disebutkan' }} · {{ p.tanggal_teks }}
                                    </p>

                                    <p class="mt-2 flex flex-wrap gap-2 text-[10px] font-bold uppercase">
                                        <span class="border-2 border-ink px-2 py-0.5" :class="p.status === 'terverifikasi' ? 'bg-accent-100' : 'bg-paper-alt'">
                                            {{ p.label_status }}
                                        </span>
                                        <span v-if="p.unggulan" class="border-2 border-ink bg-accent-400 px-2 py-0.5">Unggulan</span>
                                        <span v-if="!p.tampil_publik" class="border-2 border-ink bg-paper-alt px-2 py-0.5">Disembunyikan</span>
                                        <span v-if="p.poin_diberikan > 0" class="border-2 border-ink bg-paper-alt px-2 py-0.5">+{{ p.poin_diberikan }} poin</span>
                                        <span v-else-if="p.status === 'diajukan' && p.poin_diharapkan > 0" class="border-2 border-ink bg-paper-alt px-2 py-0.5">
                                            menunggu {{ p.poin_diharapkan }} poin
                                        </span>
                                    </p>

                                    <p v-if="p.catatan_verifikasi" class="mt-2 text-xs">
                                        <strong>Catatan pengurus:</strong> {{ p.catatan_verifikasi }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <a v-if="p.sertifikat_url" :href="p.sertifikat_url" target="_blank" rel="noopener" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">
                                    Lihat Sertifikat
                                </a>
                                <a v-else-if="p.tautan_bukti" :href="p.tautan_bukti" target="_blank" rel="noopener" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">
                                    Lihat Bukti
                                </a>

                                <button
                                    v-if="p.boleh_disunting"
                                    type="button"
                                    class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                    @click="isiForm(p)"
                                >Perbaiki</button>

                                <button
                                    v-if="p.status === 'terverifikasi'"
                                    type="button"
                                    class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                    @click="ubahTampil(p, !p.tampil_publik)"
                                >{{ p.tampil_publik ? 'Sembunyikan dari publik' : 'Tampilkan di publik' }}</button>

                                <button
                                    v-if="p.boleh_disunting"
                                    type="button"
                                    class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold"
                                    @click="tarik(p)"
                                >Tarik Pengajuan</button>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </AnggotaLayout>
</template>
