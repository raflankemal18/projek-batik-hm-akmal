# Marketplace Batik HM Akmal

Aplikasi marketplace batik berbasis Laravel 11 + Inertia/React (Vite), dengan MySQL sebagai database. Auth pakai session cookie (Laravel Breeze) — tidak ada lagi JWT/`tymon/jwt-auth` (dicopot saat backend refactor, lihat `docs/superpowers/specs/`).

Ada dua cara menjalankan project ini: **pakai Docker** (direkomendasikan, tidak perlu install PHP/Composer/Node/MySQL di mesin kamu) atau **tanpa Docker** (install semua tool langsung di mesin).

---

## Opsi A — Menjalankan dengan Docker (direkomendasikan)

### Prasyarat
- Docker & Docker Compose plugin sudah terinstall (`docker --version`, `docker compose version`)

### Langkah pertama kali
```bash
# dari root project
docker compose up --build -d
```

Ini akan:
1. Build image PHP-FPM 8.2 (alpine) berisi extension yang dibutuhkan (`pdo_mysql`, `gd`, `intl`, dll), Composer, dan Node.js
2. Menjalankan 3 container: `app` (PHP-FPM), `nginx` (web server, port `8000`), `db` (MySQL 8.0)
3. Saat container `app` start, `docker/entrypoint.sh` otomatis:
   - `composer install` & `npm install && npm run build` (kalau `vendor/`, `node_modules/`, atau `public/build/` belum ada)
   - Generate `APP_KEY` kalau `.env` belum punya
   - Menunggu MySQL siap, lalu `php artisan migrate --force`
   - `php artisan storage:link`
   - Baru menjalankan `php-fpm`

> **Catatan scheduler:** `routes/console.php` mendaftarkan `orders:release-expired` untuk jalan tiap menit (`Schedule::command(...)->everyMinute()`) — ini yang membatalkan draft order kadaluarsa & mengembalikan stok. Definisi jadwal ini **tidak otomatis jalan sendiri**; Laravel butuh sesuatu yang memanggil `php artisan schedule:run` tiap menit. Container `app` saat ini cuma menjalankan `php-fpm`, tidak ada proses cron/scheduler terpisah — jadi di setup Docker ini scheduler **belum aktif**. Untuk mengaktifkan, jalankan manual sesekali:
> ```bash
> docker compose exec app php artisan orders:release-expired
> ```
> atau tambahkan service terpisah di `docker-compose.yml` yang menjalankan `php artisan schedule:work` di dalam container `app` (pakai image yang sama, command override).

Proses pertama kali agak lama (install dependencies + build assets). Setelah selesai, buka:

```
http://localhost:8000
```

### Perintah sehari-hari
```bash
# lihat log realtime
docker compose logs -f app

# masuk ke shell container app
docker compose exec app sh

# jalankan artisan command apapun
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker

# stop container (data DB tetap ada)
docker compose down

# stop + hapus semua volume (reset total, termasuk data DB & vendor/node_modules)
docker compose down -v

# rebuild image setelah ubah Dockerfile/composer.json/package.json
docker compose up --build -d
```

### Konfigurasi
- `.env` di root project dipakai langsung oleh container `app` (di-bind mount, bukan disalin ke image), jadi edit `.env` di host lalu `docker compose restart app` untuk apply.
- Kredensial DB default (lihat `docker-compose.yml`): host `db`, database `marketplace_batik`, user `root`, password `root`. Cocok dengan isi `.env` bawaan.
- Port `8000` (nginx) dan `3306` (MySQL, supaya bisa diakses tool GUI seperti TablePlus/DBeaver dari host) di-expose ke host.

### Catatan teknis (kenapa setup-nya begini)
Setup Docker sebelumnya **tidak bisa jalan** karena dua bug:
1. Container `app` menjalankan `php artisan serve` (HTTP biasa di port 8000), padahal `nginx.conf` meneruskan request PHP ke `app:9000` pakai protokol **FastCGI** — dua hal yang tidak kompatibel. Sekarang `app` menjalankan `php-fpm` (default untuk base image `php:8.2-fpm-alpine`) yang memang bicara FastCGI di port 9000.
2. `docker-compose.yml` bind-mount seluruh project (`.:/var/www/html`), yang otomatis **menutupi** `vendor/`, `node_modules/`, dan `public/build/` hasil build saat `docker build` (karena folder itu cuma ada di image, bukan di host). Sekarang dipakai *named volume* khusus untuk ketiga folder itu, dan instalasi/build dijalankan di `entrypoint.sh` saat container start (bukan cuma saat build image) supaya selalu konsisten baik ada bind mount maupun tidak.
3. File `.env` di host biasanya permission `600` (cuma bisa dibaca pemiliknya), sedangkan PHP-FPM worker jalan sebagai user `www-data` — sehingga Laravel gagal baca `.env` secara diam-diam (fallback ke `APP_ENV=production` tanpa `APP_KEY`, error "No application encryption key"). `entrypoint.sh` sekarang otomatis `chmod 644 .env` setiap start.

---

## Opsi B — Menjalankan tanpa Docker (instalasi penuh di mesin lokal)

### Prasyarat

Install semua ini di mesin kamu dulu sebelum mulai:

- **PHP 8.2+** dengan extension: `pdo_mysql`, `mbstring`, `zip`, `exif`, `pcntl`, `bcmath`, `gd`, `intl`, `xml`, `curl`
  ```bash
  php -v                 # cek versi
  php -m | grep -E 'pdo_mysql|mbstring|zip|exif|pcntl|bcmath|gd|intl'   # cek extension terpasang
  ```
- **Composer 2.x** — `composer --version`
- **Node.js 18+ dan npm** — `node -v && npm -v`
- **MySQL 8.0** (atau MariaDB yang kompatibel) jalan sebagai service lokal — `mysql --version`, pastikan service-nya aktif (`sudo systemctl status mysql` di Linux, atau `brew services list` di macOS)

### 1. Clone & install dependencies

```bash
git clone <url-repo-ini> marketplace-batik-hm-akmal
cd marketplace-batik-hm-akmal

composer install
npm install
```

### 2. Siapkan `.env`

```bash
cp .env.example .env
```

Edit `.env`, field yang **wajib** disesuaikan untuk jalan di lokal (tanpa Docker, MySQL diakses lewat `127.0.0.1` bukan hostname `db`):

```env
APP_NAME="Marketplace Batik"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace_batik
DB_USERNAME=root
DB_PASSWORD=<password MySQL lokal kamu>
```

`MIDTRANS_SERVER_KEY` / `MIDTRANS_CLIENT_KEY` di `.env.example` sudah diisi sandbox key contoh — ganti dengan sandbox key akun Midtrans kamu sendiri dari [dashboard.sandbox.midtrans.com](https://dashboard.sandbox.midtrans.com) kalau mau test pembayaran beneran.

### 3. Buat database

```bash
mysql -u root -p -e "CREATE DATABASE marketplace_batik"
```

### 4. Generate application key

```bash
php artisan key:generate
```

(Tidak ada lagi langkah `jwt:secret` — auth sekarang murni session cookie, bukan JWT.)

### 5. Jalankan migrasi

```bash
php artisan migrate
```

Ini akan menjalankan seluruh migration secara berurutan, termasuk dua migration hasil refactor backend (`2026_08_12_000000_refactor_order_payment_stock_schema` dan `2026_08_12_000001_add_contact_info_to_orders`) yang mengubah tipe kolom harga jadi `decimal`, menambah `orders.status`/`expires_at`, `order_details.product_size_id`, dan `payments.transaction_id`/`paid_at`/`order_id`.

### 6. (Opsional) Isi data contoh

```bash
php artisan db:seed
```

Bikin 2 akun (`admin@gmail.com` / `user@gmail.com`, password `password` untuk keduanya) + 6 produk contoh dengan ukuran & stok acak.

### 7. Link storage (untuk gambar produk)

```bash
php artisan storage:link
```

### 8. Build frontend

```bash
npm run build
# atau untuk development dengan hot-reload (jalankan di terminal terpisah, biarkan tetap running):
# npm run dev
```

### 9. Jalankan server

```bash
php artisan serve
```

Buka `http://localhost:8000`.

### 10. Aktifkan scheduler (penting — jangan dilewatkan)

`orders:release-expired` (membatalkan draft order yang kadaluarsa + mengembalikan stok) dijadwalkan lewat Laravel Scheduler di `routes/console.php`, tapi scheduler **butuh dipicu** dari luar. Tambahkan cron entry ini (`crontab -e`):

```cron
* * * * * cd /path/ke/marketplace-batik-hm-akmal && php artisan schedule:run >> /dev/null 2>&1
```

Tanpa ini, draft order yang ditinggal user (checkout tapi tidak bayar) tidak akan pernah dibatalkan otomatis dan stoknya tidak kembali. Untuk testing manual tanpa nunggu cron:

```bash
php artisan orders:release-expired
```

### Menjalankan test suite (non-Docker)

Butuh database test terpisah (konfigurasinya ada di `phpunit.xml`, `DB_DATABASE=marketplace_batik_test`):

```bash
mysql -u root -p -e "CREATE DATABASE marketplace_batik_test"
php artisan test
```

### Perintah sehari-hari (non-Docker)

```bash
php artisan migrate:fresh --seed     # reset & isi ulang database
php artisan tinker                   # REPL
php artisan orders:release-expired   # jalankan manual tanpa nunggu cron
npm run dev                          # dev server Vite dengan HMR (jalan bareng php artisan serve)
php artisan test                     # jalankan test suite
```

---

## Perbandingan singkat

| | Docker | Tanpa Docker |
|---|---|---|
| Install PHP/Composer/Node/MySQL manual | Tidak perlu | Perlu |
| Isolasi dari environment mesin | Ya | Tidak |
| Kecepatan setup pertama kali | Lebih lambat (build image) | Cepat kalau tool sudah ada |
| Cocok untuk | Konsistensi tim, deploy | Development cepat/debug langsung |
