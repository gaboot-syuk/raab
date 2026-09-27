<script setup lang="ts">
/*
 * Diagnostik — khusus Superadmin.
 *
 * Halaman ini menjawab satu pertanyaan: "apa yang rusak?" Tanpa SSH, tanpa
 * terminal, tanpa menebak. Karena itu yang ditampilkan hanyalah NAMA pengandar
 * dan status terkonfigurasi — bukan kata sandi SMTP, bukan kunci aplikasi.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Aplikasi {
    lingkungan: string;
    debug: boolean;
    url: string;
    locale: string;
    php: string;
    laravel: string;
    zona_waktu: string;
}

interface BasisData {
    pengandar: string;
    tersambung: boolean;
    tabel: number;
    galat: string | null;
}

interface Antrean {
    pengandar: string;
    menunggu: number;
    gagal: number;
    catatan: string;
}

interface Email {
    pengirim_surat: string;
    dari: string;
    nama_dari: string;
    terkonfigurasi: boolean;
}

interface Penyimpanan {
    disk_media: string;
    disk_bawaan: string;
    privat_bisa_ditulis: boolean;
    publik_bisa_ditulis: boolean;
    cadangan_jumlah: number;
    cadangan_total: string;
    cadangan_terakhir: string | null;
}

interface Penjadwal {
    nama: string;
    perintah: string;
    terdaftar: string;
}

interface Log {
    ada: boolean;
    galat_hari_ini: number;
    cuplikan: string[];
}

interface Pemeriksaan {
    keadaan: string;
    apa: string;
    saran: string;
}

const props = defineProps<{
    aplikasi: Aplikasi;
    basisData: BasisData;
    antrean: Antrean;
    email: Email;
    penyimpanan: Penyimpanan;
    penjadwal: Penjadwal[];
    log: Log;
    pemeriksaan: Pemeriksaan[];
}>();

function warnaKeadaan(keadaan: string): string {
    if (keadaan === 'aman') return 'bg-accent-400 text-primary-800';
    if (keadaan === 'perlu tindakan') return 'bg-red-300 text-red-900';

    return 'bg-paper-alt text-ink';
}

function ya(t: boolean): string {
    return t ? 'Ya' : 'Tidak';
}
</script>

<template>
    <Head title="Diagnostik" />

    <PanelLayout>
        <div class="space-y-6">
            <div>
                <h1 class="font-display text-2xl">Diagnostik</h1>
                <p class="text-sm text-muted">
                    Keadaan teknis situs saat ini. Hanya Superadmin yang bisa membukanya, dan tidak ada
                    kata sandi atau kunci rahasia yang ditampilkan di sini.
                </p>
            </div>

            <!-- ===== Pemeriksaan yang butuh perhatian ===== -->
            <section class="space-y-3">
                <h2 class="font-display text-lg">Perlu Diperhatikan</h2>

                <ul class="space-y-3">
                    <li v-for="(p, i) in props.pemeriksaan" :key="i" class="brutal bg-paper p-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="brutal-sm px-2 py-0.5 text-xs font-bold" :class="warnaKeadaan(p.keadaan)">
                                {{ p.keadaan }}
                            </span>
                            <span class="font-bold">{{ p.apa }}</span>
                        </div>
                        <p class="mt-2 text-sm text-muted">{{ p.saran }}</p>
                    </li>
                </ul>
            </section>

            <div class="grid gap-4 md:grid-cols-2">
                <!-- ===== Aplikasi ===== -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Aplikasi</h2>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Lingkungan</dt>
                            <dd class="font-mono">{{ props.aplikasi.lingkungan }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Mode debug</dt>
                            <dd class="font-mono" :class="props.aplikasi.debug ? 'text-red-700 font-bold' : ''">
                                {{ ya(props.aplikasi.debug) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">PHP</dt>
                            <dd class="font-mono">{{ props.aplikasi.php }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Laravel</dt>
                            <dd class="font-mono">{{ props.aplikasi.laravel }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Zona waktu</dt>
                            <dd class="font-mono">{{ props.aplikasi.zona_waktu }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Alamat situs</dt>
                            <dd class="font-mono break-all">{{ props.aplikasi.url }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- ===== Basis data ===== -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Basis Data</h2>
                    <p class="mt-1 text-xs text-muted">Tanpa basis data, situs tidak bisa menampilkan apa pun.</p>

                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Pengandar</dt>
                            <dd class="font-mono">{{ props.basisData.pengandar }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Tersambung</dt>
                            <dd class="font-mono" :class="props.basisData.tersambung ? '' : 'text-red-700 font-bold'">
                                {{ ya(props.basisData.tersambung) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Jumlah tabel</dt>
                            <dd class="font-mono">{{ props.basisData.tabel }}</dd>
                        </div>
                    </dl>

                    <p v-if="props.basisData.galat" class="brutal-sm mt-3 bg-red-200 p-3 text-xs break-all">
                        {{ props.basisData.galat }}
                    </p>
                </section>

                <!-- ===== Antrean & email ===== -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Antrean &amp; Email</h2>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Pengandar antrean</dt>
                            <dd class="font-mono">{{ props.antrean.pengandar }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Menunggu diproses</dt>
                            <dd class="font-mono">{{ props.antrean.menunggu }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Gagal</dt>
                            <dd class="font-mono" :class="props.antrean.gagal > 0 ? 'text-red-700 font-bold' : ''">
                                {{ props.antrean.gagal }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Pengirim surat</dt>
                            <dd class="font-mono">{{ props.email.pengirim_surat }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Alamat pengirim</dt>
                            <dd class="font-mono break-all">{{ props.email.dari || '—' }}</dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-xs text-muted">{{ props.antrean.catatan }}</p>

                    <p v-if="!props.email.terkonfigurasi" class="brutal-sm mt-3 bg-accent-400 p-3 text-xs font-bold">
                        Alamat pengirim belum diisi. Pengingat dan notifikasi lewat email tidak akan terkirim
                        sampai diisi di berkas <code>.env</code> server.
                    </p>
                </section>

                <!-- ===== Penyimpanan ===== -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Penyimpanan</h2>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Disk media unggahan</dt>
                            <dd class="font-mono">{{ props.penyimpanan.disk_media }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Disk bawaan</dt>
                            <dd class="font-mono">{{ props.penyimpanan.disk_bawaan }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Folder privat bisa ditulis</dt>
                            <dd class="font-mono">{{ ya(props.penyimpanan.privat_bisa_ditulis) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Folder publik bisa ditulis</dt>
                            <dd class="font-mono">{{ ya(props.penyimpanan.publik_bisa_ditulis) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Cadangan tersimpan</dt>
                            <dd class="font-mono">{{ props.penyimpanan.cadangan_jumlah }} ({{ props.penyimpanan.cadangan_total }})</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Cadangan terakhir</dt>
                            <dd class="font-mono">{{ props.penyimpanan.cadangan_terakhir || '—' }}</dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-xs text-muted">
                        Cadangan yang hanya tersimpan di server yang sama belum disebut aman — unduh berkasnya
                        dan simpan di tempat lain.
                    </p>
                </section>
            </div>

            <!-- ===== Penjadwal ===== -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Penjadwal</h2>
                <p class="mt-1 text-xs text-muted">
                    Daftar ini menunjukkan perintah yang SUDAH terdaftar di penjadwal. Terdaftar belum berarti
                    berjalan — penjadwal tetap butuh cron, atau pemanggilan berkala ke penjadwal.
                </p>

                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="p in props.penjadwal" :key="p.perintah" class="flex flex-wrap items-center gap-3">
                        <span
                            class="brutal-sm px-2 py-0.5 text-xs font-bold"
                            :class="p.terdaftar === 'Ya' ? 'bg-accent-400 text-primary-800' : 'bg-red-300 text-red-900'"
                        >{{ p.terdaftar }}</span>
                        <span>{{ p.nama }}</span>
                        <code class="border-2 border-ink bg-paper-alt px-1 text-xs">{{ p.perintah }}</code>
                    </li>
                </ul>
            </section>

            <!-- ===== Log ===== -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Galat Hari Ini</h2>

                <p class="mt-1 text-sm">
                    <span class="font-mono font-bold">{{ props.log.galat_hari_ini }}</span> baris galat tercatat
                    sejak tengah malam.
                </p>

                <p v-if="!props.log.ada" class="mt-2 text-xs text-muted">Berkas log belum terbentuk.</p>

                <ul v-else-if="props.log.cuplikan.length" class="mt-3 space-y-2">
                    <li v-for="(c, i) in props.log.cuplikan" :key="i" class="brutal-sm bg-paper-alt p-3 font-mono text-xs break-all">
                        {{ c }}
                    </li>
                </ul>

                <p v-else class="mt-2 text-xs text-muted">
                    Tidak ada galat. Bagus — halaman ini ada bukan untuk dipandangi, tapi untuk dibuka ketika
                    ada yang tidak berjalan semestinya.
                </p>
            </section>
        </div>
    </PanelLayout>
</template>
