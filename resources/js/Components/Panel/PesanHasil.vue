<script setup lang="ts">
/*
 * Notifikasi hasil aksi (flash message) dan daftar galat validasi.
 * Dipakai di seluruh halaman panel supaya umpan balik seragam.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const halaman = usePage<{
    flash: { sukses?: string; galat?: string };
    errors: Record<string, string>;
}>();

const sukses = computed(() => halaman.props.flash?.sukses);
const galat = computed(() => halaman.props.flash?.galat);
const daftarGalat = computed(() => Object.values(halaman.props.errors ?? {}));
</script>

<template>
    <p
        v-if="sukses"
        role="status"
        class="brutal-sm border-ink bg-success/15 px-4 py-3 text-sm font-semibold"
    >
        {{ sukses }}
    </p>

    <div
        v-if="galat"
        role="alert"
        class="brutal-sm border-ink bg-accent-100 px-4 py-3 text-sm font-semibold"
    >
        {{ galat }}
    </div>

    <div
        v-if="daftarGalat.length"
        role="alert"
        class="brutal-sm border-ink bg-accent-100 px-4 py-3 text-sm"
    >
        <p class="font-bold">Mohon periksa kembali isian berikut:</p>
        <ul class="mt-1 list-inside list-disc">
            <li v-for="pesan in daftarGalat" :key="pesan">{{ pesan }}</li>
        </ul>
    </div>
</template>
