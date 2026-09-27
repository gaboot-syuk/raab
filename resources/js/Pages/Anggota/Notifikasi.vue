<script setup lang="ts">
/*
 * Pusat notifikasi — kerangka area anggota.
 *
 * Isinya SAMA PERSIS dengan versi panel: keduanya memakai `NotifikasiIsi`.
 * Menyalin isinya ke dua berkas hanya akan membuat keduanya berbeda cepat atau
 * lambat — dan yang berbeda biasanya versi yang jarang dibuka.
 */
import NotifikasiIsi from '@/Components/NotifikasiIsi.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    daftar: {
        id: string;
        kategori: string;
        label_kategori: string;
        ikon: string;
        judul: string;
        pesan: string;
        tautan: string | null;
        dibaca: boolean;
        waktu: string | null;
    }[];
    kategori: string;
    pilihanKategori: Record<string, string>;
    jumlahPerKategori: Record<string, number>;
    belumDibaca: number;
    preferensi: {
        kunci: string;
        label: string;
        keterangan: string;
        ikon: string;
        wajib: boolean;
        email: boolean;
    }[];
    catatan: string;
}>();
</script>

<template>
    <Head title="Notifikasi" />

    <AnggotaLayout>
        <NotifikasiIsi
            :daftar="daftar"
            :kategori="kategori"
            :pilihan-kategori="pilihanKategori"
            :jumlah-per-kategori="jumlahPerKategori"
            :belum-dibaca="belumDibaca"
            :preferensi="preferensi"
            :catatan="catatan"
        />
    </AnggotaLayout>
</template>
