<script setup lang="ts">
/*
 * Halaman laporan lintas modul.
 *
 * Laporan yang TIDAK muncul di sini bukan disembunyikan — datanya memang tidak
 * boleh dibaca pengguna ini. Daftar "tertutup" ditampilkan apa adanya supaya
 * jelas mengapa sebuah laporan tidak ada, dan supaya pengurus tahu izin mana
 * yang perlu diminta, bukan menebak-nebak.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Jenis {
    kunci: string;
    label: string;
    keterangan: string;
    izin: string;
}

const props = defineProps<{
    tersedia: Jenis[];
    tertutup: Jenis[];
    ringkasan: { label: string; nilai: number }[];
    bolehEkspor: boolean;
    catatan: string;
}>();

const dari = ref('');
const sampai = ref('');

function ekspor(jenis: string): void {
    const params = new URLSearchParams();

    if (dari.value) params.set('dari', dari.value);
    if (sampai.value) params.set('sampai', sampai.value);

    const kueri = params.toString();

    window.location.href = `/panel/laporan/${jenis}/ekspor${kueri ? `?${kueri}` : ''}`;
}
</script>

<template>
    <Head title="Laporan" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div>
                <h1 class="font-display text-2xl">Laporan</h1>
                <p class="text-sm text-muted">Unduhan lintas modul untuk rapat dan pelaporan ke komisariat.</p>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

            <!-- ===== Rentang tanggal ===== -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Rentang Tanggal</h2>
                <p class="mt-1 text-xs text-muted">
                    Kosongkan keduanya untuk mengunduh seluruh riwayat. Rentang yang kosong berarti
                    "semua", bukan "hari ini".
                </p>

                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Dari</span>
                        <input v-model="dari" type="date" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Sampai</span>
                        <input v-model="sampai" type="date" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <button
                        type="button"
                        class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold"
                        @click="dari = ''; sampai = ''"
                    >Kosongkan</button>
                </div>
            </section>

            <!-- ===== Ringkasan ===== -->
            <section v-if="props.ringkasan.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="r in props.ringkasan" :key="r.label" class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">{{ r.label }}</p>
                    <p class="font-display text-2xl">{{ r.nilai }}</p>
                </div>
            </section>

            <!-- ===== Laporan yang tersedia ===== -->
            <section>
                <h2 class="font-display text-lg">Laporan yang Bisa Kamu Unduh</h2>

                <p v-if="!props.tersedia.length" class="brutal mt-3 bg-paper p-5 text-sm text-muted">
                    Tidak ada laporan yang bisa kamu unduh. Kamu memegang izin membuka halaman ini, tetapi belum
                    memegang izin data mentah dari modul mana pun — dan laporan tidak boleh menjadi jalan pintas
                    menuju data yang di tempat lain dibatasi.
                </p>

                <ul v-else class="mt-3 space-y-3">
                    <li v-for="j in props.tersedia" :key="j.kunci" class="brutal bg-paper p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-display text-base">{{ j.label }}</h3>
                                <p class="mt-1 text-xs text-muted">{{ j.keterangan }}</p>
                                <p class="mt-1 text-[11px] text-muted">Izin: <span class="font-mono">{{ j.izin }}</span></p>
                            </div>

                            <button
                                type="button"
                                class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                                @click="ekspor(j.kunci)"
                            >Unduh CSV</button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- ===== Yang tertutup ===== -->
            <section v-if="props.tertutup.length">
                <h2 class="font-display text-lg">Laporan yang Tidak Terbuka untukmu</h2>

                <ul class="mt-3 space-y-2">
                    <li v-for="j in props.tertutup" :key="j.kunci" class="brutal border-2 border-dashed border-ink/30 bg-paper-alt p-4">
                        <p class="font-bold">{{ j.label }}</p>
                        <p class="mt-1 text-xs text-muted">
                            Perlu izin <span class="font-mono">{{ j.izin }}</span> — mintalah ke Superadmin kalau kamu memang membutuhkannya.
                        </p>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
