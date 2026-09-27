# ============================================================
# PMII Rayon Ali Ahmad Baktsir — Citra Produksi
#
# Ciri khas citra ini:
#
#   * Tiga tahap. Aset depan dibangun di tahap Node, PHP beserta pustakanya
#     dipasang di tahap Composer, dan yang benar-benar dikirim ke server
#     hanyalah tahap terakhir. Alat pembangun (node_modules, composer cache,
#     kode sumber paket) tidak ikut terbawa.
#
#   * Ekstensi gambar ikut dipasang. Lingkungan pengembangan tidak
#     memilikinya, sehingga konversi ukuran gambar di sana dilewati. Di
#     produksi, gd dipasang supaya gambar benar-benar diperkecil.
#
#   * Port diambil dari variabel $PORT. Hosting gratis menentukan portnya
#     sendiri lewat variabel lingkungan; alamat yang ditulis tetap akan
#     ditolak.
#
#   * Satu proses menjalankan nginx, php-fpm, dan pekerja antrean sekaligus
#     (lihat docker/prod/supervisord.conf). Hosting gratis umumnya hanya
#     memberi satu proses; memisahkan pekerja antrean ke layanan kedua berarti
#     tidak jalan sama sekali.
# ============================================================

# ------------------------------------------------------------
# Tahap 1 — bangun aset depan
# ------------------------------------------------------------
FROM node:22-alpine AS aset

WORKDIR /app

# Berkas kunci didahulukan supaya lapisan pemasangan paket hanya dibangun
# ulang ketika daftar paket berubah, bukan setiap kali kode berubah.
COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ------------------------------------------------------------
# Tahap 2 — pasang pustaka PHP
# ------------------------------------------------------------
FROM composer:2 AS php

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts karena skrip artisan membutuhkan berkas yang belum ada di tahap
# ini. Skripnya dijalankan di tahap terakhir, saat seluruh berkas sudah ada.
#
# --ignore-platform-req=ext-exif: citra `composer:2` tidak membawa exif,
# sedangkan spatie/laravel-medialibrary menuntutnya sehingga pemeriksaan
# platform menggagalkan build. Pemeriksaan itu berlaku pada citra PEMBANGUN
# ini, bukan pada citra yang dikirim — dan citra akhirnya memang memasang exif
# (lihat daftar docker-php-ext-install di tahap 3). Karena itu yang dikecualikan
# hanya SATU ekstensi ini, bukan seluruh pemeriksaan platform: memakai
# --ignore-platform-reqs akan ikut menyembunyikan ekstensi lain yang benar-benar
# kurang, dan itu justru kesalahan yang sedang kita hindari.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --ignore-platform-req=ext-exif \
        --optimize-autoloader

# ------------------------------------------------------------
# Tahap 3 — citra akhir
# ------------------------------------------------------------
FROM php:8.4-fpm-alpine

# Paket sistem:
#   nginx        — server web
#   supervisor   — menjalankan nginx, php-fpm, dan antrean dalam satu wadah
#   curl         — pemeriksaan kesehatan
#   sqlite       — basis data bawaan, dan dipakai lingkungan pengujian
#   libpng/libjpeg/freetype — pendukung ekstensi gd
#
# Pustaka RUNTIME sengaja dipasang terpisah dari paket -dev.
#
# `apk del libpng-dev` tidak sekadar menghapus berkas header: ia juga membuang
# `libpng` yang tadi ikut terpasang sebagai kebergantungannya. Kalau pustaka
# runtime tidak diminta secara eksplisit, gd/intl/zip berhasil DIKOMPILASI
# tetapi gagal DIMUAT saat wadahnya berjalan — dan galatnya baru terlihat di
# log sebagai "Unable to load dynamic library". Dengan memintanya eksplisit,
# ia menjadi paket yang diminta langsung, sehingga `apk del` di bawah tidak
# menyentuhnya.
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        icu-libs \
        oniguruma \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libzip-dev \
        icu-dev \
        sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_sqlite \
        bcmath \
        exif \
        gd \
        intl \
        zip \
        opcache \
    && apk del libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev libzip-dev icu-dev \
    && rm -rf /var/cache/apk/*

# Membuktikan citra AKHIR benar-benar MEMUAT setiap ekstensi yang dipasang.
#
# Memasang ekstensi dan memuatnya adalah dua hal berbeda: kalau pustaka
# runtime-nya ikut terbuang, kompilasinya berhasil tetapi modulnya gagal
# dimuat — dan itu tidak terlihat sampai ada halaman yang memakainya.
#
# Spatie medialibrary menuntut ext-exif, dan citra ini sempat tertinggal
# darinya; baru ketahuan ketika penyebaran ke hosting gagal. Tahap Composer
# sengaja mengecualikan pemeriksaan itu, jadi di sinilah pemeriksaannya
# ditegakkan — pada citra yang benar-benar dijalankan.
RUN set -e; \
    for ext in pdo_mysql pdo_sqlite bcmath exif gd intl zip opcache; do \
        php -m | grep -qi "$ext" || { echo "GAGAL: ekstensi $ext tidak termuat"; exit 1; }; \
    done; \
    echo "Seluruh ekstensi termuat."

# Pekerja antrean dijalankan sebagai proses terpisah di dalam wadah ini, dan
# `queue:work` adalah proses panjang — batas waktunya harus dimatikan, kalau
# tidak PHP akan menghentikannya di tengah jalan.
RUN printf 'memory_limit = 256M\nupload_max_filesize = 20M\npost_max_size = 21M\nmax_execution_time = 0\n' \
        > /usr/local/etc/php/conf.d/raab.ini \
    && printf 'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=128\n' \
        > /usr/local/etc/php/conf.d/raab-opcache.ini

WORKDIR /var/www/html

# Pustaka PHP dari tahap 2 — sudah lengkap dengan autoloader yang dimampatkan.
COPY --from=php /app/vendor ./vendor

# Kode aplikasi.
COPY . .

# Pustaka JavaScript TIDAK diperlukan di produksi: asetnya sudah dibangun.
RUN rm -rf node_modules

# Aset yang sudah dibangun, ditumpuk di atas kode sumber.
COPY --from=aset /app/public/build ./public/build

# Templat nginx sudah ikut terbawa oleh `COPY . .` di atas; yang perlu
# dipasang di luar pohon aplikasi hanyalah konfigurasi supervisor dan skrip
# titik masuknya.
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/prod/masuk.sh /usr/local/bin/masuk.sh
RUN chmod +x /usr/local/bin/masuk.sh

# Folder yang harus bisa ditulis: cache, log, sesi, dan media unggahan.
#
# DIBUAT LEBIH DULU, dan itu wajib.
#
# `.dockerignore` sengaja mengabaikan `storage` dan `bootstrap/cache` supaya
# berkas dari laptop pengembang — cadangan basis data, gambar unggahan, log —
# tidak pernah ikut masuk ke dalam citra. Akibatnya kedua folder itu TIDAK ADA
# di dalam citra setelah `COPY . .`, dan `chown` di bawah akan gagal:
#
#   chown: cannot access 'storage': No such file or directory
#
# Build berhenti di situ. Karena itu kerangkanya dibuat di sini, bersih, bukan
# disalin dari mesin pengembang.
#
# Yang dibuat adalah kerangka yang WAJIB ada agar Laravel bisa berjalan: tanpa
# `storage/framework/views`, Laravel menolak merender tampilan apa pun; tanpa
# `storage/framework/cache`, cache gagal; tanpa `bootstrap/cache`, perintah
# `config:cache` dan `route:cache` gagal di titik masuk wadah.
RUN mkdir -p \
        storage/app/public \
        storage/app/private/cadangan \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Port ditentukan hosting lewat variabel lingkungan. Nilai bawaan 8080 supaya
# citra ini tetap bisa dijalankan langsung tanpa variabel apa pun.
ENV PORT=8080
EXPOSE 8080

# Pemeriksaan kesehatan memakai titik /up yang sudah disediakan Laravel.
# Tanpa ini, wadah yang "hidup" tapi tidak bisa melayani permintaan akan
# dianggap sehat, dan hosting tidak akan pernah menggantinya.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/up" || exit 1

CMD ["/usr/local/bin/masuk.sh"]
