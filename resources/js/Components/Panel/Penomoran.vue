<script setup lang="ts">
/*
 * Penomoran halaman (pagination) bergaya neo-brutalism.
 * Menerima array `links` bawaan Laravel paginator.
 */
import { Link } from '@inertiajs/vue3';

defineProps<{
    tautan: { url: string | null; label: string; aktif: boolean }[];
}>();
</script>

<template>
    <nav v-if="tautan.length > 3" class="flex flex-wrap items-center gap-1" aria-label="Penomoran halaman">
        <template v-for="(tautanHalaman, indeks) in tautan" :key="indeks">
            <span
                v-if="!tautanHalaman.url"
                class="border-2 border-ink/25 px-3 py-1.5 text-sm text-muted"
                v-html="tautanHalaman.label"
            />
            <Link
                v-else
                :href="tautanHalaman.url"
                preserve-scroll
                class="border-2 border-ink px-3 py-1.5 text-sm font-bold"
                :class="tautanHalaman.aktif ? 'bg-accent-400 text-primary-800' : 'bg-paper hover:bg-accent-100'"
                v-html="tautanHalaman.label"
            />
        </template>
    </nav>
</template>
