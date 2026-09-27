import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

/*
|--------------------------------------------------------------------------
| Entry Inertia — dipakai HANYA oleh panel pengurus & dashboard anggota.
| Halaman publik memakai Blade dan tidak memuat berkas ini.
|--------------------------------------------------------------------------
*/

createInertiaApp({
    title: (title) => (title ? `${title} — PMII RAAB` : 'PMII RAAB'),

    resolve: (name) => {
        /*
         * Pemetaan halaman dibuat MALAS (tanpa `eager: true`) agar Vite memecah
         * setiap halaman menjadi berkas tersendiri. Sebelumnya semua halaman
         * digabung dalam satu berkas, sehingga editor teks kaya (TipTap) ikut
         * terunduh bahkan saat pengurus hanya membuka dasbor.
         */
        const halaman = import.meta.glob<DefineComponent>('./Pages/**/*.vue');

        return halaman[`./Pages/${name}.vue`]();
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },

    progress: {
        color: '#FFD100',
    },
});
