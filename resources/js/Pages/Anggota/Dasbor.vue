<script setup lang="ts">
/*
 * Dasbor anggota. Isinya menyesuaikan keadaan:
 *  - pengajuan masih menunggu → tampilkan status pengajuan
 *  - kader aktif → tampilkan nomor anggota & kartu
 *  - alumni → tampilkan ringkasan profil alumni
 */
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    anggota: {
        id: number;
        nomor_anggota: string | null;
        nama: string;
        status: string;
        label_status: string;
        jalur: string;
        label_jalur: string;
        angkatan: number | null;
        fakultas: string | null;
        program_studi: string | null;
        unit: string | null;
        kelengkapan: number;
        kartu: { nomor_kartu: string; berlaku_sampai: string | null } | null;
        alumni: {
            tahun_lulus: number | null;
            instansi: string | null;
            jabatan: string | null;
            kota_domisili: string | null;
            bersedia_mentor: boolean;
        } | null;
    } | null;
    pengajuan: {
        status: string;
        label_status: string;
        jalur: string;
        catatan_pengurus: string | null;
        dikirim_pada: string;
        diproses_pada: string | null;
    } | null;
    pintasan: { label: string; tautan: string }[];
}>();

const belumDiverifikasi = computed(
    () => props.pengajuan !== null && props.pengajuan.status !== 'disetujui',
);
</script>

<template>
    <AnggotaLayout>
        <Head title="Dasbor Anggota" />

        <div class="space-y-6">
            <PesanHasil />

            <!-- Belum diverifikasi: tampilkan status pengajuan -->
            <section v-if="belumDiverifikasi && pengajuan" class="brutal bg-paper p-5">
                <h1 class="font-display text-2xl">Pengajuanmu sedang diproses</h1>
                <p class="mt-2 text-sm">
                    Dikirim {{ pengajuan.dikirim_pada }} ·
                    Jalur <strong>{{ pengajuan.jalur === 'kader' ? 'Kader Aktif' : 'Alumni' }}</strong>
                </p>

                <p class="mt-4 inline-block border-2 border-ink px-3 py-1 text-sm font-bold uppercase"
                   :class="pengajuan.status === 'perbaikan' ? 'bg-accent-400 text-primary-800' : pengajuan.status === 'ditolak' ? 'bg-accent-100' : 'bg-paper-alt'">
                    {{ pengajuan.label_status }}
                </p>

                <div v-if="pengajuan.catatan_pengurus" class="brutal-sm mt-4 border-ink bg-accent-100 p-4 text-sm">
                    <p class="font-bold">Catatan dari pengurus</p>
                    <p class="mt-1">{{ pengajuan.catatan_pengurus }}</p>
                </div>

                <p class="mt-4 text-sm text-muted">
                    Nomor anggota dan kartu kader diterbitkan setelah Sekretaris menyetujui pengajuan.
                    Pastikan emailmu sudah diverifikasi agar pemberitahuan sampai.
                </p>

                <Link href="/profil" class="brutal-sm brutal-hover mt-4 inline-block bg-primary-600 px-4 py-2 text-sm font-bold text-paper">
                    Periksa Data Saya
                </Link>
            </section>

            <!-- Sudah diverifikasi -->
            <template v-else-if="anggota">
                <!--
                    Blok kartu hanya untuk KADER. Alumni tidak memakai kartu —
                    kartunya dicabut saat statusnya berubah. Menampilkan blok ini
                    kepada alumni berarti menawarkan sesuatu yang tidak akan
                    pernah ada, lengkap dengan tombol "Lihat & cetak kartu" yang
                    membawa ke halaman kosong.
                -->
                <section v-if="anggota.jalur === 'kader'" class="brutal bg-brand-dark p-6 text-on-brand">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-brand/70">Kartu Anggota</p>
                    <p class="mt-2 font-mono text-2xl">{{ anggota.nomor_anggota || 'Nomor belum diterbitkan' }}</p>
                    <p class="mt-1 font-display text-lg">{{ anggota.nama }}</p>
                    <p class="mt-3 text-sm text-on-brand/85">
                        {{ anggota.label_jalur }} ·
                        {{ anggota.unit || 'Belum memilih biro/LSO' }}
                    </p>
                    <p v-if="anggota.kartu" class="mt-2 text-xs text-on-brand/70">
                        Kartu {{ anggota.kartu.nomor_kartu }} · berlaku sampai {{ anggota.kartu.berlaku_sampai }}
                    </p>
                    <a
                        href="/kartu-kader"
                        class="brutal-sm brutal-hover mt-4 inline-block bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                    >Lihat &amp; cetak kartu</a>
                </section>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="brutal bg-paper p-5">
                        <p class="text-xs font-bold uppercase text-muted">Status Keanggotaan</p>
                        <p class="mt-1 font-display text-lg">{{ anggota.label_status }}</p>
                    </div>
                    <div class="brutal bg-paper p-5">
                        <p class="text-xs font-bold uppercase text-muted">Angkatan</p>
                        <p class="mt-1 font-display text-lg">{{ anggota.angkatan || '—' }}</p>
                    </div>
                    <div class="brutal bg-paper p-5">
                        <p class="text-xs font-bold uppercase text-muted">Kelengkapan Profil</p>
                        <p class="mt-1 font-display text-lg">{{ anggota.kelengkapan }}%</p>
                        <div class="mt-2 h-3 border-2 border-ink bg-paper-alt">
                            <div class="h-full bg-accent-400" :style="{ width: anggota.kelengkapan + '%' }"></div>
                        </div>
                    </div>
                </div>

                <section v-if="anggota.jalur === 'alumni'" class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Profil Alumni</h2>
                    <p v-if="anggota.alumni" class="mt-2 text-sm">
                        {{ [anggota.alumni.jabatan, anggota.alumni.instansi].filter(Boolean).join(' · ') || 'Instansi belum diisi' }}
                    </p>
                    <p v-if="anggota.alumni?.kota_domisili" class="mt-1 text-sm text-muted">
                        Domisili: {{ anggota.alumni.kota_domisili }}
                    </p>
                    <p v-if="anggota.alumni?.bersedia_mentor" class="mt-2 inline-block border-2 border-ink bg-success/25 px-2 py-0.5 text-xs font-bold uppercase">
                        Siap menjadi mentor
                    </p>
                    <p class="mt-3 text-xs text-muted">
                        Kamu dapat mengatur bagian mana dari profilmu yang boleh tampil di direktori publik.
                    </p>
                </section>

                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Langkah Berikutnya</h2>
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        <li v-for="p in pintasan" :key="p.tautan">
                            <Link :href="p.tautan" class="brutal-sm brutal-hover block bg-accent-100 px-3 py-2 text-sm font-bold">
                                {{ p.label }}
                            </Link>
                        </li>
                    </ul>
                    <p class="mt-4 text-xs text-muted">
                        Semua fitur anggota sudah terbuka — presensi, poin kontribusi, prestasi,
                        pengumuman, arsip, aspirasi, dan hibah alumni bisa dibuka dari menu di atas.
                    </p>
                </section>
            </template>

            <!-- Kasus tak terduga: akun ada tetapi belum ada pengajuan -->
            <section v-else class="brutal bg-paper p-5">
                <h1 class="font-display text-2xl">Belum ada pengajuan keanggotaan</h1>
                <p class="mt-2 text-sm text-muted">
                    Akunmu sudah aktif, tetapi belum ada pengajuan keanggotaan yang tercatat.
                    Hubungi Sekretaris bila ini keliru.
                </p>
                <Link href="/kontak" class="brutal-sm brutal-hover mt-4 inline-block bg-primary-600 px-4 py-2 text-sm font-bold text-paper">
                    Hubungi Sekretariat
                </Link>
            </section>
        </div>
    </AnggotaLayout>
</template>
