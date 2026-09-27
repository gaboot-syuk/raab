<script setup lang="ts">
/*
 * Pusat notifikasi — kerangka panel.
 *
 * Isi halaman ini sama dengan versi area anggota; yang berbeda hanya
 * kerangkanya. Notifikasi selalu aktif dan TIDAK bisa dimatikan — yang bisa
 * diatur pengguna hanya pengiriman lewat email.
 */
import NotifikasiIsi from '@/Components/NotifikasiIsi.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
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

    <PanelLayout>
        <NotifikasiIsi
            :daftar="daftar"
            :kategori="kategori"
            :pilihan-kategori="pilihanKategori"
            :jumlah-per-kategori="jumlahPerKategori"
            :belum-dibaca="belumDibaca"
            :preferensi="preferensi"
            :catatan="catatan"
        />
    </PanelLayout>
</template>
