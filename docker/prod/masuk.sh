#!/bin/sh
# ============================================================
# Titik masuk wadah produksi.
#
# Dijalankan setiap kali wadah dinyalakan, dan urutannya disengaja:
#
#   1. Pastikan port sudah diketahui, lalu tulis konfigurasi nginx.
#   2. Jalankan migrasi basis data.
#   3. Isi data awal — HANYA bila diminta lewat APP_JALANKAN_SEED. Akun demo
#      per peran menyusul, dengan sakelar TERPISAH (APP_JALANKAN_SEED_DEMO).
#   4. Siapkan cache konfigurasi dan tampilan — TIDAK rute. Lihat alasannya
#      pada bagian cache di bawah.
#   5. Sediakan tautan penyimpanan publik.
#   6. Baru jalankan nginx, php-fpm, dan antrean.
#
# Poin 2–4 dijalankan DI SINI, bukan saat membangun citra, karena basis data
# dan variabel lingkungan baru tersedia saat wadahnya dinyalakan. Migrasi yang
# dijalankan saat pembangunan citra akan gagal: alamat basis datanya belum ada.
# ============================================================

set -e

PORT="${PORT:-8080}"
export PORT

echo "[masuk] Menyiapkan nginx pada port ${PORT}…"
sed "s/\${PORT}/${PORT}/g" /var/www/html/docker/prod/nginx.conf.template > /etc/nginx/nginx.conf

echo "[masuk] Menunggu basis data siap…"
# Menunggu, bukan menyerah. Wadah aplikasi dan basis data biasanya menyala
# bersamaan, dan basis datanya selalu siap belakangan.
selesai=0
for i in $(seq 1 30); do
    if php /var/www/html/artisan migrate:status >/dev/null 2>&1; then
        selesai=1
        break
    fi
    echo "[masuk]   …belum siap (percobaan ${i}/30)"
    sleep 2
done

if [ "$selesai" -ne 1 ]; then
    echo "[masuk] Basis data tidak dapat dihubungi. Wadah tetap dijalankan supaya"
    echo "[masuk] halaman galat dan /up bisa menjawab, dan masalahnya terlihat."
fi

if [ "$APP_JALANKAN_MIGRASI" != "false" ]; then
    echo "[masuk] Menjalankan migrasi…"

    # Kegagalan TIDAK menghentikan wadah — halaman galat dan /up harus tetap
    # bisa menjawab supaya masalahnya terlihat. Tetapi kegagalan itu juga tidak
    # boleh LEWAT BEGITU SAJA: tanpa peringatan di bawah, yang tampak di log
    # hanyalah baris "Menjalankan migrasi…" yang seolah berhasil.
    if ! php /var/www/html/artisan migrate --force; then
        echo "[masuk] ============================================================"
        echo "[masuk] PERINGATAN: MIGRASI GAGAL."
        echo "[masuk] Tabelnya belum terbentuk. Situs akan menyala tetapi hampir"
        echo "[masuk] setiap halaman akan gagal. Periksa pesan galat di atas."
        echo "[masuk] ============================================================"
    fi
fi

# Data awal: peran & izin, akun superadmin, pengaturan situs, halaman statis.
#
# HANYA berjalan bila diminta lewat APP_JALANKAN_SEED=true, dan itu hanya perlu
# pada penyebaran PERTAMA ke basis data yang masih kosong. Tanpa langkah ini,
# tabelnya terbentuk tetapi kosong: tidak ada peran, tidak ada akun untuk masuk,
# dan halaman seperti /sejarah menjawab 404.
#
# Sengaja tidak dijalankan otomatis setiap kali wadah menyala: seeder ini
# menulis ulang pengaturan dan halaman, dan menjalankannya terus-menerus
# membuang waktu penyalaan pada setiap bangun dari tidur.
if [ "$APP_JALANKAN_SEED" = "true" ]; then
    echo "[masuk] Mengisi data awal…"

    # Sama seperti migrasi: gagal pun wadah tetap jalan, tetapi TIDAK boleh
    # diam. Sebelum ini kegagalannya tertelan `|| true`, dan akibatnya baru
    # terlihat belakangan sebagai halaman 404 dan ketidakmampuan masuk panel —
    # tanpa satu pun petunjuk di log bahwa pengisiannya memang gagal.
    if ! php /var/www/html/artisan db:seed --force; then
        echo "[masuk] ============================================================"
        echo "[masuk] PERINGATAN: PENGISIAN DATA AWAL GAGAL."
        echo "[masuk] Tabel ada tetapi KOSONG: tidak ada peran, tidak ada akun"
        echo "[masuk] untuk masuk, dan halaman seperti /sejarah menjawab 404."
        echo "[masuk] Periksa pesan galat di atas, lalu jalankan ulang dengan"
        echo "[masuk] APP_JALANKAN_SEED=true."
        echo "[masuk] ============================================================"
    fi
fi

# Akun demo per peran — SAKELAR TERPISAH, dan pemisahan itu disengaja.
#
# Seeder ini membuat akun sekretaris, bendahara, konten, kader, dan alumni
# dengan SATU kata sandi bersama dari SEED_DEMO_PASSWORD, supaya setiap peran
# dapat dicoba pada masa uji coba MVP. Ia tidak ikut ke dalam `db:seed` biasa
# karena data demo bukan bagian dari kerangka situs: membuangnya kelak tidak
# boleh berarti ikut membuang peran, pengaturan, dan halaman.
#
# Seeder itu masih menjaga dirinya sendiri: tanpa APP_JALANKAN_SEED_DEMO=true
# ia menolak berjalan meski dipanggil dari sini.
if [ "$APP_JALANKAN_SEED_DEMO" = "true" ]; then
    echo "[masuk] Mengisi akun demo per peran…"

    if ! php /var/www/html/artisan db:seed --class=DemoPeranSeeder --force; then
        echo "[masuk] ============================================================"
        echo "[masuk] PERINGATAN: PENGISIAN AKUN DEMO GAGAL."
        echo "[masuk] Pastikan SEED_DEMO_PASSWORD sudah diisi — di produksi seeder"
        echo "[masuk] menolak memakai kata sandi bawaan yang tertulis di kode."
        echo "[masuk] ============================================================"
    fi
fi

echo "[masuk] Menyiapkan cache…"
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan view:cache || true

# `route:cache` SENGAJA TIDAK dijalankan di sini.
#
# Paket mcamara/laravel-localization mendaftarkan alamat berprefiks bahasa
# (/en) SAAT PERMINTAAN BERJALAN, bukan saat rute didefinisikan. Pencachean
# rute membekukan koleksi rute sebelum penambahan itu terjadi, sehingga
# SELURUH halaman versi Inggris menjawab 404.
#
# Terbukti dengan mengukurnya langsung: sebelum route:cache "/en" menjawab
# 200, sesudahnya 404 — dan kembali 200 begitu cache-nya dibersihkan.
#
# Kegagalannya senyap dan hanya separuh: halaman Indonesia tetap normal,
# sehingga yang tampak hanyalah "versi Inggris tidak ada". Lingkungan
# pengembangan pun tidak pernah menjalankan route:cache, jadi tidak ada yang
# menangkapnya sampai dipasang di hosting.
#
# Ongkosnya nyata tapi kecil: pendaftaran rute diulang tiap permintaan.
# Itu jauh lebih murah daripada separuh situs yang mati.

echo "[masuk] Menyiapkan penyimpanan publik…"
php /var/www/html/artisan storage:link || true

echo "[masuk] Menjalankan nginx, php-fpm, dan antrean…"
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
