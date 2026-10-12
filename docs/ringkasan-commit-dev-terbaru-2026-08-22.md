# Ringkasan commit terbaru di branch `dev`

**Commit:** `43bafb4f9171b908c591a78b745292de10115bb8`  
**Pesan:** `test: complete Fase B automation testing — 14 files, 165/165 passing`  
**Tanggal commit:** 2026-08-22  
**Branch:** `dev`

## Yang dikerjakan

Commit ini menyelesaikan Fase B pengujian otomatis backend menggunakan Pest. Sebanyak 14 file test feature ditambahkan, dengan 108 test baru untuk fitur-fitur berikut:

- Wishlist, pencarian produk, pelacakan order, dan invoice.
- Notifikasi user dan admin, banner, serta fulfillment.
- Bulk action untuk user, order, dan produk.
- Export order CSV/PDF, export log PDF, dan analytics admin.

Selain test, beberapa bug yang ditemukan saat penulisan test turut diperbaiki:

1. Bulk update fulfillment mencari `payment_id` pada tabel `orders`, padahal relasi payment menyimpan `order_id`. Query diperbaiki agar mencari order melalui relasi `payment`.
2. Bulk delete produk menggunakan authorization policy `delete` dengan class, padahal policy tersebut membutuhkan instance produk. Ditambahkan policy `deleteAny` untuk operasi class-based.
3. Export PDF order memuat nama relasi payment yang tidak sesuai dan view membaca nama relasi yang berbeda. Controller dan view diperbaiki, serta alias relasi `payment_details()` ditambahkan untuk kompatibilitas.
4. Relasi `Order::payment()` ditambahkan dan notifikasi perubahan status order menggunakan relasi payment yang benar, tanpa mengasumsikan `payment_id` tersedia pada tabel `orders`.

## Hasil yang tercatat

- Seluruh suite: **165 test lulus, 644 assertions** — 57 test lama dan 108 test baru.
- **Tidak ada regresi** yang tercatat saat verifikasi.
- `npm run build` berhasil.
- Semua 14 file test Fase B telah diverifikasi; detail skenario dan hasil per batch tercatat di [planning-fix-p0-dan-automation-testing-2026-08-22.md](./planning-fix-p0-dan-automation-testing-2026-08-22.md).

Hasil di atas merujuk pada verifikasi yang tercatat untuk commit tersebut; test dan build tidak dijalankan ulang saat ringkasan ini dibuat.

## Fase C — E2E Playwright (selesai 2026-08-22, belum di-commit)

Playwright + skenario end-to-end sudah dijalankan dan **9/9 test hijau** dalam 2,2 menit, dengan
`php artisan test` **173 passed (704 assertions)** dan `npm run build` sukses.

- Infrastruktur: `playwright.config.ts`, `tests/e2e/` (5 spec + helper), `scripts/e2e.sh`,
  `.env.e2e` + `docker-compose.e2e.yml` (DB E2E terpisah dari DB dev), `php artisan e2e:reset`
  (reset + seed ~4 detik), `database/seeders/E2ESeeder.php` (fixture deterministik).
- Skenario 4 memakai simulasi webhook lokal: signature `sha512(order_id+status_code+gross_amount+serverKey)`
  dihitung di dalam test, `snap.js` di-`page.route` — tanpa ketergantungan jaringan.

**Bug produksi yang ditemukan oleh E2E (semua sudah diperbaiki):**

1. 🔴 **Webhook Midtrans membalas 419** — `POST /midtrans/webhook` kena middleware CSRF, sedangkan
   Midtrans tidak mengirim token; test Pest tidak bisa menangkap ini karena token otomatis dilewati
   saat `runningUnitTests()`. Akibatnya pembayaran sukses tidak pernah mengubah status order di aplikasi.
2. 🔴 **Halaman admin reload tanpa henti** — `useTableFilters.js` memakai satu `ref` untuk dua `useEffect`;
   18 request ke `/admin/products` dalam 5 detik sebelum fix.
3. 🟠 **Toast admin tidak pernah tampil** — `<Toaster>` hanya di-mount di `WelcomeLayout`, bukan di `AdminLayout`.
4. 🟠 **Pesan stok habis tidak pernah sampai ke user** — `Checkout.jsx` membaca `errors.message`,
   padahal kontrak D3/A.2 mengirim `errors.stock`.
5. 🟠 **`VITE_MIDTRANS_CLIENT_KEY` tidak pernah ada** di `.env.example`, sehingga popup Snap tidak akan muncul.

### Perbaikan lanjutan — alur pembayaran buntu (sudah diperbaiki)

Temuan di atas ("`POST /payment` tidak pernah dipanggil dari frontend", "`payment_details` kosong")
sudah diklik dan diperbaiki:

6. 🔴 **User tidak pernah bisa membayar dari situs.** `POST /payment` tidak pernah dipanggil dari
   frontend mana pun — setelah checkout, user mendarat di `/pembayaran` tanpa tombol pembayaran sama
   sekali. Fix: `PaymentController::index` mengirim prop `unpaidOrder`, dan `Payment.jsx` menampilkan
   kartu "Pesanan Belum Dibayar" + tombol "Bayar Sekarang" yang memanggil `POST /payment`.
7. 🟠 **`payment_details` tidak pernah diisi** kode mana pun sehingga rincian item, invoice, dan
   export admin kosong. Fix: `PaymentController::store` menyalin `order_details` ke `payment_details`.
8. 🟠 **Risiko payment yatim.** Panggilan API Midtrans dipindah ke luar transaksi DB, dan
   `catch (\Exception)` generik diganti `catch (\Throwable)` yang sempit dengan pesan ramah —
   kalau Midtrans gagal, tidak ada tagihan setengah jadi yang tertinggal.
9. 🟠 **`Payment.jsx` menulis `item.price`** padahal kolomnya `harga` → "Rp NaN" begitu rincian
   item mulai tampil.

Verifikasi: `tests/Feature/PaymentInitiationTest.php` (8 test baru — payment, `payment_details`,
idempoten, gagal tanpa payment yatim, isolasi user, order kedaluwarsa) + skenario E2E 2b
(tombol benar-benar memanggil `POST /payment`). Total **E2E 9/9** dan **Pest 173/173**.

**Temuan yang masih terbuka (butuh keputusan produk):** setelah simpan produk, admin diarahkan ke
dashboard admin, bukan kembali ke daftar produk.

Rincian lengkap: `docs/planning-fase-c-playwright-e2e-2026-08-22.md`.

## Yang masih tersisa

- **Fase D:** setup GitHub Actions CI untuk menjalankan test dan build (serta Playwright).
