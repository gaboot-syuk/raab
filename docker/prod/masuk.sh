#!/bin/sh
# ============================================================
# Titik masuk wadah produksi.
#
# Dijalankan setiap kali wadah dinyalakan, dan urutannya disengaja:
#
#   1. Pastikan port sudah diketahui, lalu tulis konfigurasi nginx.
#   2. Jalankan migrasi basis data.
#   3. Isi data awal — HANYA bila diminta lewat APP_JALANKAN_SEED.
#   4. Siapkan cache konfigurasi, rute, dan tampilan.
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
    php /var/www/html/artisan migrate --force || true
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
    php /var/www/html/artisan db:seed --force || true
fi

echo "[masuk] Menyiapkan cache…"
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true

echo "[masuk] Menyiapkan penyimpanan publik…"
php /var/www/html/artisan storage:link || true

echo "[masuk] Menjalankan nginx, php-fpm, dan antrean…"
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
