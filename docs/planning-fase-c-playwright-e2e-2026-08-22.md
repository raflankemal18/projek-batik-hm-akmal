# Planning — Fase C: E2E Playwright (7 skenario alur kritis)

**Tanggal:** 2026-08-22
**Dasar:** `docs/planning-fix-p0-dan-automation-testing-2026-08-22.md` (Fase C), `docs/audit-implementasi-2026-08-22.md`
**Status:** **SELESAI & TERVERIFIKASI (2026-08-22)** — `npx playwright test` → **9 passed** (2,2 menit), `php artisan test` → **173 passed (704 assertions)**, `npm run build` sukses. Alur pembayaran yang tadinya buntu juga sudah diperbaiki (lihat §10).
Dokumen ini awalnya ditulis sebagai PLAN hasil baca kode aktual; bagian §9 berisi hasil eksekusi & bug yang ditemukan.
**Keputusan yang sudah dikunci bersama user (2026-08-22):**
1. Skenario 4 (bayar) → **simulasi webhook lokal**, bukan Snap sandbox asli.
2. Boleh ubah kode produksi: tambah `data-testid` + perbaiki bug yang ketahuan (`errors.stock`, `VITE_MIDTRANS_CLIENT_KEY`).
3. Server E2E dijalankan lewat **Docker Compose penuh** (nginx `:8000` + php-fpm + MySQL), bukan `php artisan serve`.
4. Fokus Fase C dulu — **Fase D (CI) tidak dieksekusi sekarang**.

---

## 1. Kondisi repo saat perencanaan (hasil baca, bukan asumsi)

- `vendor/`, `node_modules/`, `public/build/` **belum ada** di working tree → perlu bootstrap penuh.
- Tidak ada `.env` (hanya `.env.example`).
- Docker daemon hidup (29.4.0) tapi **container belum jalan**.
- Playwright/Dusk/Cypress belum ada di `composer.json`/`package.json`; `.github/workflows` juga belum ada (itu Fase D).
- `tests/Feature/*` sudah 165 test (Fase B) — E2E ini murni lapisan tambahan, tidak menggantikan Pest.

## 2. Temuan dari baca kode yang mempengaruhi rencana

### 2.1 (Bug produksi) Client key Midtrans tidak pernah terkirim ke frontend
`resources/js/Pages/Payment.jsx:21`

```js
const clientKey = import.meta.env.VITE_MIDTRANS_CLIENT_KEY;
```

`.env.example` hanya punya `MIDTRANS_CLIENT_KEY` (tanpa prefiks `VITE_`), dan tidak ada baris `VITE_MIDTRANS_CLIENT_KEY` di mana pun. Akibatnya `script.setAttribute("data-client-key", clientKey)` bernilai `"undefined"` → popup Snap tidak akan pernah muncul. Tombol "Bayar Sekarang" di halaman `/pembayaran` praktis tidak bisa dipakai user asli. **Fix: tambahkan `VITE_MIDTRANS_CLIENT_KEY` ke `.env.example`.**

### 2.2 (Bug produksi) Kontrak `errors.stock` tidak pernah sampai ke UI
`bootstrap/app.php:38` mengirim `back()->withErrors(['stock' => ...])` untuk `InsufficientStockException`/`EmptyCartException` (kontrak D3/A.2). Tapi `resources/js/Pages/Checkout.jsx:56` hanya membaca:

```js
onError: (errors) => toast.error("Gagal memproses checkout.", { description: errors?.message }),
```

`grep "errors.stock"` di `resources/js` = **0 hasil**. Jadi user melihat toast generik tanpa penjelasan stok — persis kelas bug yang A.2 intention-nya perbaiki di backend, tapi masih terbuka di frontend. **Fix: `errors.stock ?? errors.message`.**

### 2.3 Selector E2E
`grep -rn "data-testid" resources/js` = **0 hasil**. Tanpa `data-testid`, semua selector harus texts/role yang rapuh (mis. teks Indonesia dengan kapitalisasi campur). Rencana ini menambah `data-testid` pada elemen yang benar-benar disentuh test saja (±12 titik), tanpa mengubah perilaku render.

### 2.4 Hook yang harus diakomodasi di config test
- **Service worker**: `resources/views/app.blade.php` mendaftarkan `/sw.js` di **semua** halaman, dan `public/sw.js` melakukan caching response. Wajib `use: { serviceWorkers: 'block' }` supaya test tidak membaca HTML basi.
- **Tombol cart/wishlist hover-only**: `ProductCards.jsx` memakai `opacity-0 ... group-hover:opacity-100` → test wajib `hover()` dulu sebelum klik.
- **Toast**: dari `sonner` (DOM `data-sonner-toast`), assertion cukup pakai `getByText`.
- **Kategori**: `ProductCollection.jsx:13-18` memakai list hardcode `pria/wanita/sarimbit`, sama persis dengan opsi di form admin (`form/modal/Modal.jsx:135-137`) → skenario 6 konsisten, tidak ada mismatch.

### 2.5 Skenario 4 bisa offline
`app/Services/MidtransService::verifySignature()` = `sha512(order_id . status_code . gross_amount . serverKey)`. Field-nya semua diketahui di dalam test → signature bisa dihitung sendiri, tidak perlu jaringan ke Midtrans. `snap.js` bisa di-`page.route` (stub) supaya jalur UI "klik Bayar Sekarang" tetap teruji tanpa iframe pihak ketiga.

## 3. C.0 — Bootstrap stack Docker (prasyarat)

Nilai default `.env.example` **tidak bisa dipakai di dalam container**: `DB_HOST=127.0.0.1` (loopback ke container app, bukan service `db`) dan `DB_DATABASE=tokobatik` (sedangkan service `db` hanya membuat `marketplace_batik`). `docker/entrypoint.sh` sendiri menunggu DB di `${DB_HOST:-db}`.

Rencana:

1. `.env.e2e` (host, **tidak** di-commit) = turunan `.env.example` dengan `DB_HOST=db`, `DB_PASSWORD=root`, `DB_DATABASE=marketplace_batik_e2e`, `APP_ENV=local`, `APP_DEBUG=true`, plus `VITE_MIDTRANS_CLIENT_KEY`.
2. `docker-compose.e2e.yml` (override):
   - `app.volumes`: mount `./.env.e2e:/var/www/html/.env` (menimpa bind `.env` host pada target yang sama),
   - `db.environment.MYSQL_DATABASE: marketplace_batik_e2e`.
   - Praktisnya: `docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --build db app nginx`.
   - DB dev dan DB E2E jadi terpisah tanpa menyentuh `.env` milik user.
   - *Alternatif (kalau mau lebih sedikit file): satu `.env` docker untuk semua + `CREATE DATABASE marketplace_batik_e2e` manual di service `db`.*
3. Seed DB E2E: `docker compose -f … exec app php artisan migrate:fresh --seed --seeder=E2ESeeder --force`.
4. `php artisan test` (Pest) tetap memakai `marketplace_batik_test` sesuai `phpunit.xml` → tidak terganggu.
5. Playwright jalan di **host** (Node 24 + npm 11 sudah ada). `npm i -D @playwright/test` di host tidak bentrok dengan named volume `node-modules-data` milik container.

## 4. C.1 — File setup yang akan dibuat

| File | Isi |
|---|---|
| `playwright.config.ts` | `testDir: tests/e2e`, `baseURL http://localhost:8000`, `workers: 1` (satu DB dibagi semua spec), `trace: 'retain-on-failure'`, `screenshot: 'only-on-failure'`, `use: { serviceWorkers: 'block' }`, reporter `list` + `html`. `webServer` **nonaktif** — stack dikelola docker, bukan di-spawn Playwright. |
| `tests/e2e/global-setup.ts` | Tunggu `GET /up` ≤60s dengan pesan error eksplisit; reset+seed DB E2E lewat `docker compose exec`. |
| `scripts/e2e.sh` | `up -d --build` → tunggu healthy → seed → `npm run build` bila JSX berubah → `npx playwright test`. |
| `database/seeders/E2ESeeder.php` | Fixture deterministik (lihat §5). |
| `tests/e2e/support/auth.ts` | Login lewat UI untuk `admin`/`user` (bukan token/session API). |
| `tests/e2e/support/helpers.ts` | Email unik per test, util kirim webhook dengan signature sha512. |
| `tests/e2e/support/selectors.ts` | Konstanta `data-testid` (satu sumber kebenaran antara spec dan JSX). |
| `.gitignore` | `playwright-report/`, `test-results/`, `.playwright/`, `.env.e2e`. |
| `package.json` | script `test:e2e`, devDependency `@playwright/test`. |

### Fixture `E2ESeeder` (nilai diketahui test, tidak acak)

| Data | Nilai |
|---|---|
| Admin | `admin@e2e.test` / `password`, role `admin` |
| User | `user@e2e.test` / `password`, role `user` |
| Produk A | "Batik E2E Pria" — kategori `pria`, size S stok **1** (kasus stok habis) |
| Produk B | "Batik E2E Wanita" — kategori `wanita`, size S stok 10 (target skenario 6) |
| Produk C | "Kain E2E Motifwali" — kategori `pria`, stok 10 (target skenario 7) |
| Cart untuk user | Produk A size S qty **5** (melebihi stok 1) → skenario 3. Dibuat langsung di DB, bukan lewat UI, karena `CartService` sudah menolak qty > stok saat add — jadi tidak bisa direproduksi lewat UI tanpa browser kedua. Skenario ini sengaja menguji **kontrak error checkout**, bukan再現-aturan cart. |
| Order untuk user | order `paid` + payment `success`, `fulfillment_status` null → skenario 5 |

## 5. C.2 — Perubahan kode produksi

1. **`data-testid`** pada: field Register (4), field Login (2), tombol size `ModernDetail.jsx`, tombol "Masukkan Keranjang", link kartu produk `ProductCards.jsx`, tombol "Checkout Sekarang" `Keranjang.jsx`, field `phone`/`alamat`/`message` + submit `Checkout.jsx`, input search `Header.jsx`, `select` fulfillment `admin/Order.jsx`, tombol & field modal produk admin.
2. **Fix `Checkout.jsx:56`**: `errors.stock ?? errors.message`.
3. **`VITE_MIDTRANS_CLIENT_KEY`** ditambahkan ke `.env.example`.

Semua perubahan bersifat additive/non-regresi; `php artisan test` (165) + `npm run build` dipakai sebagai pagar.

## 6. C.3 — Tujuh skenario

| # | Spec | Alur → assertion |
|---|---|---|
| 1 | `auth.spec.ts` | `/register` isi 4 field → submit → redirect `/dashboard` → landing `/` tanpa 500, nama user tampil di header. Lalu `/profile` → logout → `/login` → masuk lagi. **Guard regresi A.1/P0-1.** |
| 2 | `checkout.spec.ts` | `/products` → klik tab kategori → request Inertia ke server → `/products/{id}` → pilih size S → "Masukkan Keranjang" → `/cart` (item + badge cartCount) → "Checkout Sekarang" → `/co` isi telepon & alamat → submit → `/pembayaran` menampilkan "Menunggu Pembayaran". |
| 3 | `checkout.spec.ts` | Pakai cart ber-qty 5 / stok 1 dari seeder → `/co` → submit → toast **"Stok tidak cukup untuk …"** tampil, URL tetap `/co`, order tidak terbentuk. |
| 4 | `payment.spec.ts` | `page.route` stub `**/snap/snap.js` → klik "Bayar Sekarang" (jalur UI teruji) → `request.post('/midtrans/webhook')` dengan `signature_key = sha512(order_id+status_code+gross_amount+serverKey)` → reload → status **"LUNAS / DIPROSES"**. |
| 5 | `fulfillment.spec.ts` | Admin → `/admin/orders` → ubah `FulfillmentSelect` ke `shipped` + resi/kurir → toast sukses. Logout, login user → `/pembayaran` → badge "Dikirim" + nomor resi tampil. |
| 6 | `catalog.spec.ts` | Admin → `/admin/products` → "Tambah Produk" (modal Radix) → nama/harga/deskripsi/Kategori `wanita`/size+stok → "Simpan Perubahan" → baris baru di tabel. Logout, login user → `/products` → tab "Batik Wanita" → produk baru tampil. |
| 7 | `catalog.spec.ts` | Dari header ketik "Motifwali" → submit → `/search` → judul "Menampilkan hasil untuk…" + "1 produk ditemukan" + kartu produk. |

## 7. C.4 — Verifikasi

- `bash scripts/e2e.sh` → 7/7 hijau.
- `docker compose … exec app php artisan test` → tetap **165/165** (regresi nol).
- `npm run build` sukses.
- Trace/screenshot on-failure aktif supaya kegagalan didiagnosis tanpa akses server.
- Update `docs/planning-fix-p0-dan-automation-testing-2026-08-22.md` (status Fase C + checklist master) dan `docs/ringkasan-commit-dev-terbaru-2026-08-22.md`.

## 8. Di luar scope

Fase D (GitHub Actions CI: pest + build + playwright) — menunggu instruksi setelah Fase C hijau. Keputusan service container & cache di sana lebih besar dan sebaiknya diambil setelah Fase C terbukti stabil lokal.

---

## 9. Hasil eksekusi (2026-08-22)

### Verifikasi
| Yang dijalankan | Hasil |
|---|---|
| `bash scripts/e2e.sh` → `npx playwright test` | **9 passed (2.2 menit)** |
| `php artisan test` (dalam container) | **173 passed (704 assertions)** — 165 lama + 8 test pembayaran baru |
| `npm run build` (dalam container) | sukses (21–24 detik) |

### Deviasi dari rencana (alasan)
1. **Reset DB pakai `php artisan e2e:reset`, bukan `migrate:fresh`.** Migrasi penuh butuh ~105 detik dan
   selalu melebihi timeout per-test Playwright (timeout 60 detik). Perintah baru mengosongkan tabel +
   seed dalam ~4 detik. `TRUNCATE` sempat dipakai tapi justru 100 detik untuk 22 tabel di lingkungan
   virtual/container ini, jadi yang dipakai `DELETE FROM` per tabel dengan `FOREIGN_KEY_CHECKS=0`.
2. **Tiga user fixture, bukan satu.** Skenario 2 (belanja normal) dan skenario 3 (keranjang melebihi
   stok) butuh kondisi keranjang yang berbeda, dan `CartService` menolak qty > stok saat item
   ditambahkan lewat UI — kondisi "keranjang berisi 5, stok 1" tidak bisa direproduksi dengan klik.
3. **`page.route` untuk `snap.js`, bukan akun sandbox asli.** Sesuai keputusan: signature webhook
   dihitung di dalam test (`sha512(order_id+status_code+gross_amount+serverKey)`, cermin
   `MidtransService::verifySignature()`), jadi tidak ada dependensi jaringan.
4. **Skenario 2 tidak bisa mengassert tagihan pending muncul setelah checkout**, karena faktanya tidak ada jalur UI untuk membuat payment (lihat temuan di bawah). Yang diassert: redirect ke `/pembayaran`
   benar dan draft order terbentuk. Skenario 4 memakai user dengan tagihan pending dari fixture.

### Bug produksi yang ditemukan oleh E2E (seluruhnya diperbaiki di sesi ini)

| # | Severity | Bug | Akar | Fix |
|---|---|---|---|---|
| 1 | 🔴 | Webhook Midtrans membalas **419** — pembayaran tidak pernah terkonfirmasi di produksi | `POST /midtrans/webhook` di dalam group `web` tanpa pengecualian CSRF; Pest tidak bisa menangkap karena `runningUnitTests()` melewati cek token | `validateCsrfTokens(except: ['midtrans/webhook'])` di `bootstrap/app.php` |
| 2 | 🔴 | Halaman admin (Produk/Pesanan/Pelanggan) **reload tanpa henti** | `useTableFilters.js`: satu `ref` dipakai dua `useEffect` → effect kedua langsung `router.reload()`, dan Inertia me-remount komponen → loop | Dua ref terpisah: `firstSearch`, `firstSort` (18 request/5 dtk → 1) |
| 3 | 🟠 | Toast admin tidak pernah tampil | `<Toaster>` hanya di `WelcomeLayout`, sedangkan halaman admin memakai `AdminLayout` | Mount `<Toaster />` di `AdminLayout` |
| 4 | 🟠 | Pesan "Stok tidak cukup …" tidak pernah tampil ke user | `Checkout.jsx` hanya membaca `errors.message`, kontrak D3/A.2 memakai `errors.stock` | Baca `errors.stock ?? errors.message` |
| 5 | 🟠 | Client key Midtrans tidak pernah masuk ke frontend | `Payment.jsx` baca `VITE_MIDTRANS_CLIENT_KEY`, `.env.example` hanya punya `MIDTRANS_CLIENT_KEY` | Tambah `VITE_MIDTRANS_CLIENT_KEY` ke `.env.example` |

Bug #1 arguably yang paling serius: tanpa itu, pembayaran yang sukses di sisi Midtrans **tidak pernah**
mengubah status order di aplikasi — dan suite Pest 165 test tetap hijau selama ini.

### Temuan yang sudah diperbaiki di sesi lanjutan
Lihat **§10** — alur pembayaran buntu & `payment_details` kosong sudah diklik dan diperbaiki.

### Temuan yang masih TIDAK diperbaiki (di luar scope, perlu keputusan produk)

1. **🟠 Setelah simpan produk, admin diarahkan ke dashboard**, bukan kembali ke daftar produk
   (`ProductController::store` → `redirect route admin.dashboard`), padahal aksi dilakukan dari
   `/admin/products`.

### Catatan menjalankan ulang
```bash
docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d   # stack (sekali saja)
bash scripts/e2e.sh                                                   # build aset + jalankan suite
bash scripts/e2e.sh checkout.spec.ts                                 # subset
E2E_SKIP_DB_RESET=1 npx playwright test                               # tanpa reset DB
```
`docker/entrypoint.sh` sudah otomatis `npm install` + `composer install` + `migrate` + `storage:link`.
Kalau `vendor` di dalam container hanya berisi dependency produksi (`--no-dev`), `php artisan test`
tidak bisa jalan — jalankan `docker compose … exec app composer install` sekali di container itu.


---

## 10. Perbaikan alur pembayaran buntu (sesi lanjutan, 2026-08-22)

### Masalah
`POST /payment` (`PaymentController::store`) existed, tapi **tidak pernah dipanggil dari frontend mana
pun** (`grep router.post('/payment')` = 0 hasil). Akibatnya: user yang baru checkout mendarat di
`/pembayaran` yang menampilkan "Belum ada riwayat transaksi" dan **tidak punya tombol apa pun untuk
membayar** — initiate pembayaran mustahil dari sisi user. Sebaliknya `payment_details` juga
tidak pernah diisi kode mana pun, dan `Payment.jsx` menulis `item.price` padahal kolomnya `harga`
(rencana "Rp NaN" begitu rincian item mulai tampil).

### Perubahan

**Backend — `app/Http/Controllers/PaymentController.php`**
- `index()` sekarang mengirim prop `unpaidOrder`: order milik user dengan status `draft`, belum
  kedaluwarsa, dan belum punya payment `pending`/`success`. Di-eager-load `details.product` + `payment`
  (strict mode Laravel melarang lazy loading di luar production).
- `store()` ditulis ulang:
  - Panggilan API Midtrans dipindah **ke luar transaksi DB**. Sebelumnya `Payment::create()` dilakukan
    lebih dulu di dalam transaksi dan baru THEN memanggil Midtrans; kalau panggilan itu gagal, blok
    `catch (\Exception)` mengembalikan "Gagal memproses checkout" yang menyesatkan dan, kalau
    `DB::rollBack()` tidak terpanggil, meninggalkan payment yatim. Sekarang tagihan tidak pernah
    dibuat sebelum token berhasil didapat.
  - `catch (\Exception)` generik diganti `catch (\Throwable)` yang sempit di sekitar pemanggilan
    Midtrans saja, dengan pesan ramah ("Gagal menghubungi layanan pembayaran") + `report($e)`
    supaya tetap terlacak.
  - **`payment_details` diisi** dari `order_details` (product, size, quantity, harga, nama, email,
    telepon, alamat). Inilah yang membuat rincian item di halaman pembayaran, invoice, dan export
    admin akhirnya punya isi.
  - Order dipindahkan ke `pending_payment` di dalam transaksi yang sama dengan pembuatan payment,
    dan `payment.initiated` dicatat lewat `AuditLogService`.
  - Order yang sudah kedaluwarsa (`expires_at` terlewat) dan order milik user lain ditolak sebelum
    menyentuh Midtrans.

**Frontend — `resources/js/Pages/Payment.jsx`**
- Kartu baru "**Pesanan Belum Dibayar**" (testid `unpaid-order`) menampilkan order + rincian item +
  tombol "Bayar Sekarang" (testid `start-payment`) yang memanggil `router.post('/payment')`.
- `item.price` → `item.harga` dan format memakai `formatRupiah` (sebelumnya `Rp NaN`).

### Verifikasi
| Lapisan | Test | Hasil |
|---|---|---|
| Backend (Pest) | `tests/Feature/PaymentInitiationTest.php` — 8 test baru: prop `unpaidOrder` terkirim & terisolasi per user, tamu ditolak, payment + `payment_details` + status `pending_payment`, idempoten, **gagal Midtrans tidak meninggalkan payment yatim**, order kedaluwarsa & order milik user lain ditolak | **8/8 passed** |
| E2E | Skenario 2b: checkout → `/pembayaran` menampilkan "Pesanan Belum Dibayar" → klik tombol benar-benar issuing `POST /payment` (request di-`page.route` agar tidak menyentuh jaringan) | **passed** |
| E2E total | `npx playwright test` | **9 passed (2,2 menit)** |
| Regresi | `php artisan test` | **173 passed (704 assertions)** |

Catatan: perilaku `POST /payment` (termasuk pemanggilan API Midtrans) diuji di layer Pest dengan
`MidtransService` dimock, sementara E2E hanya memverifikasi wiring UI-nya. Keduanya dipakai karena
Mockery tidak tersedia di Node dan API Midtrans tidak boleh dipanggil dari test.
