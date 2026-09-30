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
 * Pengalih tema: terang / gelap.
 *
 * Pilihan ketiga, "ikut sistem", sudah TIDAK ADA tombolnya — tempatnya di
 * header dipakai tombol pasang aplikasi. Yang perlu diketahui:
 *
 *  - Pengunjung yang belum pernah memilih tetap mengikuti pengaturan
 *    perangkat (`tema` bernilai 'sistem'). Perilaku lamanya utuh.
 *  - `gelap` menyimpan tema yang SEDANG BERLAKU, dan itulah yang menandai
 *    tombol. Kalau tombolnya ditandai dari `tema`, pengunjung yang masih
 *    mengikuti sistem tidak melihat satu tombol pun menyala.
 *  - Setelah memilih, tidak ada jalan kembali ke "ikut sistem". Itu harga
 *    dari satu tempat di header; disengaja, bukan kelalaian.
 */
Alpine.data('pengalihTema', () => ({
    tema: localStorage.getItem('tema') || 'sistem',
    gelap: false,

    terapkan() {
        this.gelap =
            this.tema === 'gelap' ||
            (this.tema === 'sistem' &&
                window.matchMedia('(prefers-color-scheme: dark)').matches);

        document.documentElement.classList.toggle('dark', this.gelap);
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

/**
 * Tombol "Pasang aplikasi" — pintasan ke layar utama.
 *
 * TIDAK ADA service worker di baliknya, dan itu memang tidak diperlukan:
 * syarat pemasangan Chrome sekarang hanya HTTPS, manifest berisi
 * name/short_name, ikon 192 px dan 512 px, start_url, serta display.
 * Karena itu tidak ada apa pun yang disimpan di perangkat pengunjung.
 *
 * Dua jalan, karena peramban tidak seragam:
 *  - Android/Chrome/Edge memicu `beforeinstallprompt`; peristiwanya ditahan
 *    dulu supaya bisa dipanggil dari tombol sendiri.
 *  - iOS tidak punya API pemasangan sama sekali; yang bisa dilakukan hanya
 *    menunjukkan panduan langkah demi langkah.
 */
Alpine.data('pasangAplikasi', () => ({
    /** Peristiwa yang ditahan peramban sampai tombolnya ditekan. */
    tawaran: null,

    /** Apakah tombolnya perlu ditampilkan. */
    tampil: false,

    /** Apakah panduan iOS perlu dibuka. */
    panduan: false,

    inisialisasi() {
        // Sudah dibuka dari pintasannya? Tidak ada yang perlu ditawarkan lagi.
        const sudahTerpasang =
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;

        if (sudahTerpasang) {
            return;
        }

        window.addEventListener('beforeinstallprompt', (peristiwa) => {
            peristiwa.preventDefault();
            this.tawaran = peristiwa;
            this.tampil = true;
        });

        /*
         * iOS: SEMUA peramban di iPhone memakai mesin WebKit, dan satu-satunya
         * yang menyediakan "Tambahkan ke Layar Utama" adalah Safari. Karena
         * tidak ada peristiwa yang bisa ditunggu, keberadaannya ditebak dari
         * penanda perangkat — dan itu memang cara yang lazim dipakai.
         */
        if (/iPad|iPhone|iPod/.test(window.navigator.userAgent)) {
            this.tampil = true;
        }

        window.addEventListener('appinstalled', () => {
            this.tampil = false;
            this.tawaran = null;
            this.panduan = false;
        });
    },

    pasang() {
        if (this.tawaran) {
            this.tawaran.prompt();
            this.tawaran = null;
            this.tampil = false;

            return;
        }

        // Tanpa tawaran dari peramban — praktisnya iOS — yang tersedia hanya panduan.
        this.panduan = true;
    },
}));

Alpine.start();

