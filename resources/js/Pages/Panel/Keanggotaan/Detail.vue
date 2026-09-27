<script setup lang="ts">
/*
 * Detail seorang anggota: identitas, kartu, profil alumni, riwayat status.
 * Data sensitif hanya ada di prop `sensitif` bila pengguna berhak melihatnya.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Riwayat {
    status_lama: string | null;
    status_baru: string;
    alasan: string | null;
    oleh: string;
    waktu: string;
}

const props = defineProps<{
    anggota: {
        id: number;
        nomor_anggota: string | null;
        nama: string;
        nama_panggilan: string | null;
        status: string;
        label_status: string;
        jalur: string;
        label_jalur: string;
        jenis_kelamin: string | null;
        tempat_lahir: string | null;
        tanggal_lahir: string | null;
        angkatan: number | null;
        fakultas: string | null;
        program_studi: string | null;
        unit: string | null;
        keahlian: string[];
        kelengkapan: number;
        akun: { nama: string | null; email: string | null; terverifikasi: boolean };
        sensitif: { nim: string | null; telepon: string | null; email_kontak: string | null; alamat: string | null } | null;
        kartu: {
            nomor_kartu: string;
            status: string;
            berlaku_sampai: string | null;
            diterbitkan_pada: string | null;
            alasan_pencabutan: string | null;
        } | null;
        alumni: {
            tahun_lulus: number | null;
            instansi: string | null;
            jabatan: string | null;
            bidang: string | null;
            kota_domisili: string | null;
            bersedia_mentor: boolean;
        } | null;
        riwayat: Riwayat[];
    };
    pilihanStatus: Record<string, string>;
    bolehSensitif: boolean;
}>();

const formStatus = useForm({ status: props.anggota.status, alasan: '' });
const modalStatus = ref(false);

function simpanStatus() {
    formStatus.patch(`/panel/keanggotaan/${props.anggota.id}/status`, {
        preserveScroll: true,
        onSuccess: () => (modalStatus.value = false),
    });
}

function terbitkanKartu() {
    router.post(`/panel/keanggotaan/${props.anggota.id}/kartu`, {}, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head :title="`Anggota — ${anggota.nama}`" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href="/panel/keanggotaan" class="text-xs font-bold uppercase underline">← Keanggotaan</Link>
                    <h1 class="mt-2 font-display text-2xl sm:text-3xl">{{ anggota.nama }}</h1>
                    <p class="mt-1 font-mono text-sm text-muted">{{ anggota.nomor_anggota || 'Nomor anggota belum diterbitkan' }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="brutal-sm brutal-hover bg-accent-100 px-4 py-2 text-sm font-bold" @click="modalStatus = true">
                        Ubah Status
                    </button>
                    <button
                        v-if="anggota.status === 'aktif'"
                        type="button"
                        class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold"
                        @click="terbitkanKartu"
                    >Terbitkan Ulang Kartu</button>
                </div>
            </div>

            <PesanHasil />

            <div class="flex flex-wrap gap-2">
                <span class="border-2 border-ink bg-accent-400 px-2 py-0.5 text-xs font-bold uppercase text-primary-800">{{ anggota.label_status }}</span>
                <span class="border-2 border-ink bg-paper-alt px-2 py-0.5 text-xs font-bold uppercase">{{ anggota.label_jalur }}</span>
                <span class="border-2 border-ink bg-paper px-2 py-0.5 text-xs font-bold">Kelengkapan profil {{ anggota.kelengkapan }}%</span>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Identitas -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Identitas</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Panggilan</dt><dd>{{ anggota.nama_panggilan || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Jenis kelamin</dt><dd>{{ anggota.jenis_kelamin === 'perempuan' ? 'Perempuan' : anggota.jenis_kelamin === 'laki_laki' ? 'Laki-laki' : '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Tempat / tanggal lahir</dt><dd>{{ anggota.tempat_lahir || '—' }}{{ anggota.tanggal_lahir ? ', ' + anggota.tanggal_lahir : '' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Angkatan</dt><dd>{{ anggota.angkatan || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Fakultas</dt><dd>{{ anggota.fakultas || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Program studi</dt><dd>{{ anggota.program_studi || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Biro / LSO</dt><dd>{{ anggota.unit || '—' }}</dd></div>
                        <div class="flex gap-2">
                            <dt class="w-36 shrink-0 font-bold">Keahlian</dt>
                            <dd>{{ anggota.keahlian.length ? anggota.keahlian.join(', ') : '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Akun & data sensitif -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Akun &amp; Kontak</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Nama akun</dt><dd>{{ anggota.akun.nama || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Email akun</dt><dd>{{ anggota.akun.email || '—' }}</dd></div>
                        <div class="flex gap-2">
                            <dt class="w-36 shrink-0 font-bold">Verifikasi email</dt>
                            <dd>{{ anggota.akun.terverifikasi ? 'Sudah' : 'Belum' }}</dd>
                        </div>
                    </dl>

                    <div v-if="anggota.sensitif" class="mt-4 border-t-2 border-ink pt-3">
                        <p class="text-xs font-bold uppercase text-muted">Data sensitif</p>
                        <dl class="mt-2 space-y-2 text-sm">
                            <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">NIM</dt><dd>{{ anggota.sensitif.nim || '—' }}</dd></div>
                            <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Telepon</dt><dd>{{ anggota.sensitif.telepon || '—' }}</dd></div>
                            <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Email kontak</dt><dd>{{ anggota.sensitif.email_kontak || '—' }}</dd></div>
                            <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Alamat</dt><dd>{{ anggota.sensitif.alamat || '—' }}</dd></div>
                        </dl>
                    </div>
                    <p v-else class="mt-4 border-t-2 border-ink pt-3 text-xs text-muted">
                        Data sensitif disembunyikan — peranmu tidak memegang izin <em>Melihat data sensitif</em>.
                    </p>
                </section>

                <!-- Kartu -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Kartu Kader</h2>
                    <div v-if="anggota.kartu" class="mt-3 space-y-2 text-sm">
                        <p class="font-mono font-bold">{{ anggota.kartu.nomor_kartu }}</p>
                        <p>
                            Status:
                            <span class="font-bold">{{ anggota.kartu.status === 'aktif' ? 'Berlaku' : 'Dicabut' }}</span>
                        </p>
                        <p v-if="anggota.kartu.berlaku_sampai">Berlaku sampai {{ anggota.kartu.berlaku_sampai }}</p>
                        <p v-if="anggota.kartu.diterbitkan_pada" class="text-muted">Diterbitkan {{ anggota.kartu.diterbitkan_pada }}</p>
                        <p v-if="anggota.kartu.alasan_pencabutan" class="text-muted">Alasan pencabutan: {{ anggota.kartu.alasan_pencabutan }}</p>
                    </div>
                    <p v-else class="mt-3 text-sm text-muted">
                        Belum ada kartu. Kartu diterbitkan otomatis saat pengajuan disetujui (khusus jalur kader).
                    </p>
                </section>

                <!-- Profil alumni -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Profil Alumni</h2>
                    <dl v-if="anggota.alumni" class="mt-3 space-y-2 text-sm">
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Tahun lulus</dt><dd>{{ anggota.alumni.tahun_lulus || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Instansi</dt><dd>{{ anggota.alumni.instansi || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Jabatan</dt><dd>{{ anggota.alumni.jabatan || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Bidang</dt><dd>{{ anggota.alumni.bidang || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Domisili</dt><dd>{{ anggota.alumni.kota_domisili || '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-36 shrink-0 font-bold">Siap jadi mentor</dt><dd>{{ anggota.alumni.bersedia_mentor ? 'Ya' : 'Belum' }}</dd></div>
                    </dl>
                    <p v-else class="mt-3 text-sm text-muted">Belum ada data alumni.</p>
                </section>
            </div>

            <!-- Riwayat status -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Riwayat Status</h2>
                <ul v-if="anggota.riwayat.length" class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="(baris, i) in anggota.riwayat" :key="i" class="py-3 text-sm">
                        <p>
                            <span class="font-bold">{{ baris.status_lama || '—' }}</span>
                            <span aria-hidden="true"> → </span>
                            <span class="font-bold">{{ baris.status_baru }}</span>
                        </p>
                        <p v-if="baris.alasan" class="mt-1 text-muted">{{ baris.alasan }}</p>
                        <p class="mt-1 text-xs text-muted">oleh {{ baris.oleh }} · {{ baris.waktu }}</p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-muted">Belum ada perubahan status tercatat.</p>
            </section>
        </div>

        <!-- Modal ubah status -->
        <div v-if="modalStatus" class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4" @click.self="modalStatus = false">
            <form class="brutal w-full max-w-md bg-paper p-5" @submit.prevent="simpanStatus">
                <h2 class="font-display text-lg">Ubah Status Keanggotaan</h2>
                <p class="mt-1 text-sm text-muted">Saat ini {{ anggota.label_status }}.</p>

                <label for="status-detail" class="mt-4 block text-sm font-bold">Status baru</label>
                <select id="status-detail" v-model="formStatus.status" required class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option v-for="(label, nilai) in pilihanStatus" :key="nilai" :value="nilai">{{ label }}</option>
                </select>

                <label for="alasan-detail" class="mt-3 block text-sm font-bold">Alasan / keterangan</label>
                <textarea id="alasan-detail" v-model="formStatus.alasan" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modalStatus = false">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formStatus.processing">Simpan</button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
