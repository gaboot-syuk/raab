import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

/*
|--------------------------------------------------------------------------
| Halaman publik memakai Alpine.js (bukan Vue) agar tetap ringan.
| Semua konten dirender server-side oleh Blade — JavaScript hanya
| memperhalus interaksi, bukan untuk menampilkan data.
|--------------------------------------------------------------------------
*/

window.Alpine = Alpine;
Alpine.plugin(focus);

/**
 * Pengalih tema: terang / gelap / ikut sistem.
 * Preferensi disimpan di localStorage, kelas .dark dipasang pada <html>.
 */
Alpine.data('pengalihTema', () => ({
    tema: localStorage.getItem('tema') || 'sistem',

    terapkan() {
        const gelap =
            this.tema === 'gelap' ||
            (this.tema === 'sistem' &&
                window.matchMedia('(prefers-color-scheme: dark)').matches);

        document.documentElement.classList.toggle('dark', gelap);
        localStorage.setItem('tema', this.tema);
    },

    pilih(tema) {
        this.tema = tema;
        this.terapkan();
    },

    inisialisasi() {
        this.terapkan();

        window
            .matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', () => {
                if (this.tema === 'sistem') {
                    this.terapkan();
                }
            });
    },
}));

Alpine.start();

