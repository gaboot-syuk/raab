<script setup lang="ts">
/*
 * Daftar akun kas.
 *
 * `saldo_seharusnya` ditampilkan berdampingan dengan `saldo_berjalan` supaya
 * Bendahara bisa melihat sendiri bila ada yang menulis saldo di luar layanan
 * Kas — persis yang tidak boleh terjadi.
 *
 * SALDO AWAL TIDAK DAPAT DIUBAH. Mengubahnya akan membuat seluruh riwayat
 * transaksi di atasnya tidak bisa direkonsiliasi lagi.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Akun {
    id: number;
    kode: string;
    nama: string;
    jenis: string;
    label_jenis: string;
    saldo_awal: number;
    saldo_berjalan: number;
    saldo_seharusnya: number;
    aktif: boolean;
    jumlah_transaksi: number;
}

const props = defineProps<{
    akun: Akun[];
    pilihanJenis: Record<string, string>;
}>();

const sunting = ref<number | null>(null);

const form = useForm({ kode: '', nama: '', jenis: 'utama', urutan: 0 });

function rupiah(nilai: number): string {
    return 'Rp' + nilai.toLocaleString('id-ID');
}

function mulaiSunting(a: Akun): void {
    sunting.value = a.id;
    form.clearErrors();
    form.kode = a.kode;
    form.nama = a.nama;
    form.jenis = a.jenis;
    form.urutan = 0;
}

function simpan(a: Akun): void {
    form.put(`/panel/keuangan/akun/${a.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            sunting.value = null;
            form.reset();
        },
    });
}

function nonaktifkan(a: Akun): void {
    router.put(`/panel/keuangan/akun/${a.id}`, {
        kode: a.kode,
        nama: a.nama,
        jenis: a.jenis,
        urutan: 0,
        aktif: !a.aktif,
    }, { preserveScroll: true });
}

function hapus(a: Akun): void {
    if (!confirm(`Hapus akun ${a.nama}?`)) {
        return;
    }

    router.delete(`/panel/keuangan/akun/${a.id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Akun Kas" />

        <div class="mx-auto max-w-5xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Akun Kas</h1>
                    <p class="mt-1 text-sm text-muted">
                        Bila "seharusnya" berbeda dari "tersimpan", berarti ada yang menulis saldo di luar buku kas.
                    </p>
                </div>
                <a href="/panel/keuangan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Ikhtisar</a>
            </div>

            <ul class="space-y-3">
                <li v-for="a in props.akun" :key="a.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                {{ a.nama }}
                                <span class="ml-2 font-mono text-xs font-normal text-muted">{{ a.kode }}</span>
                                <span v-if="!a.aktif" class="ml-2 border-2 border-ink bg-paper-alt px-2 py-0.5 text-[10px] font-bold uppercase">Nonaktif</span>
                            </p>
                            <p class="text-xs text-muted">{{ a.label_jenis }} · {{ a.jumlah_transaksi }} transaksi</p>

                            <p class="mt-2">
                                Saldo tersimpan <span class="font-display text-lg">{{ rupiah(a.saldo_berjalan) }}</span>
                                · seharusnya {{ rupiah(a.saldo_seharusnya) }}
                            </p>
                            <p v-if="a.saldo_berjalan !== a.saldo_seharusnya" class="mt-1 border-2 border-ink bg-accent-100 px-2 py-1 text-[11px] font-bold">
                                TIDAK COCOK — ada penulisan saldo di luar buku kas. Laporkan ke Superadmin.
                            </p>
                            <p class="text-xs text-muted">Saldo awal {{ rupiah(a.saldo_awal) }} (tidak dapat diubah)</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(a)">
                                {{ sunting === a.id ? 'Tutup' : 'Sunting' }}
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="nonaktifkan(a)">
                                {{ a.aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                            <button v-if="!a.jumlah_transaksi" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(a)">
                                Hapus
                            </button>
                        </div>
                    </div>

                    <form v-if="sunting === a.id" class="mt-4 grid gap-3 border-t-2 border-ink/10 pt-4 sm:grid-cols-3" @submit.prevent="simpan(a)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Kode *</span>
                            <input v-model="form.kode" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.kode" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.kode }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                            <input v-model="form.nama" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                            <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, nilai) in props.pilihanJenis" :key="nilai" :value="nilai">{{ label }}</option>
                            </select>
                        </label>

                        <div class="flex items-end gap-2 sm:col-span-3">
                            <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                Simpan Perubahan
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="sunting = null">Batal</button>
                        </div>
                    </form>
                </li>

                <li v-if="!props.akun.length" class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                    Belum ada akun kas. Tambahkan dari halaman Ikhtisar.
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
