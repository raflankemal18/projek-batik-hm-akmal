# Planning — Fix P0 & Automation Testing

**Tanggal:** 2026-08-22
**Dasar:** `docs/audit-implementasi-2026-08-22.md`
**Status:** Fase A & Fase A.5 (migrasi repository pattern + fix deadlock) SELESAI & TERVERIFIKASI (2026-08-22). Sudah di-commit (`4cccf32` — "Complete Fase A P0 fixes and integrate refactors from other agents") dan di-push ke branch `dev`. Selanjutnya: Fase B (automation test).
**Status Git:** commit `4cccf32` di branch `dev` — 57/57 test terverifikasi ulang PERSIS di commit ini (bukan cuma di working tree sebelum commit). Commit ini juga membawa 2 file di luar scope Fase A/A.5 yang sudah nangkring uncommitted di working tree sebelumnya (`resources/js/Components/ModernHero.jsx`, hapus `resources/js/Pages/admin/Order.jsx.orig`) — bukan hasil kerja sesi ini, ikut ter-bundle karena posisinya di working tree yang sama saat commit dibuat.
**Aturan fase:** Setiap fase WAJIB diverifikasi (test + build + smoke) sebelum lanjut ke fase berikutnya. Fase A wajib selesai duluan — Fase B/C/D akan menulis test terhadap kode yang perilakunya masih berubah kalau Fase A belum kelar (percuma nulis test untuk bug yang lagi diperbaiki).

```
Fase A (Fix P0 — kecil, risiko rendah)
   |
   v
Fase B (Automation Testing Backend — Pest, isi gap 15 fitur)
   |
   v
Fase C (E2E — Playwright, alur kritis lintas halaman)
   |
   v
Fase D (CI — GitHub Actions)
```

---

## Fase A — Fix P0 (dari audit-implementasi-2026-08-22.md)

| # | Item | File | Detail |
|---|---|---|---|
| A.1 | Set `role` eksplisit saat registrasi | `app/Http/Controllers/Auth/RegisteredUserController.php` | Tambah `'role' => 'user'` ke array `User::create([...])`. **Jangan** tambahkan `role` ke `$fillable` — itu membuka celah mass-assignment role dari request manapun yang lupa whitelist. Fix harus di titik pembuatan user, bukan di model. |
| A.2 | Hapus catch generik di Checkout | `app/Http/Controllers/CheckoutController.php` | Hapus `catch (\Exception $e)` yang membungkus `createDraftOrder()`. Biarkan `InsufficientStockException`/`EmptyCartException` menjalar ke handler terpusat di `bootstrap/app.php` (sudah benar sejak D3/D4 — set `errors.stock` + catat `stock.rejected`). Kalau memang perlu fallback untuk exception lain yang genuinely tak terduga, tangkap tipe spesifik, jangan `\\Exception` polos. |
| A.3 | Hapus catch generik di Webhook | `app/Http/Controllers/PaymentController.php` | Hapus `catch (\Exception $e)` di method `webhook()`. Biarkan exception signature-invalid menjalar apa adanya supaya balas 403 (final reject), bukan 500 (Midtrans akan retry). Cek `PaymentWebhookService::handle()` untuk pastikan exception yang dilempar untuk kasus ini sudah tipe yang jelas (bukan `\\Exception` generik juga). |

### Status Eksekusi (2026-08-22)

**Status:** Fase A SELESAI & TERVERIFIKASI — **57/57 test passing**, build sukses, smoke test manual lolos.

> **Catatan integritas dokumen:** revisi sebelumnya dari bagian ini ditulis oleh proses lain di luar sesi audit ini, dan berisi klaim yang salah (mengaku hasil akhir "43/57 passing" dengan "3 bug tersisa" di ProfileTest) plus beberapa kalimat yang secara teknis tidak koheren (istilah yang tidak nyata di Laravel/Pest). Bagian ini ditulis ulang berdasarkan verifikasi langsung — test suite dijalankan ulang dari nol, bukan disalin dari klaim sebelumnya.

#### A.1 — Set `role` eksplisit saat registrasi
- **Percobaan pertama salah:** `User::create([..., 'role' => 'user'])` melanggar `$fillable` (role sengaja tidak fillable) dan melempar `MassAssignmentException` — registrasi jadi gagal total, regresi lebih parah dari bug awal. Ketahuan lewat `RegistrationTest` dan dikonfirmasi langsung via tinker.
- **Fix final:** `User::create([...])` tanpa `role`, lalu `$user->role = 'user'; $user->save();` — direct property assignment, tidak lewat guard mass-assignment. Dikonfirmasi tidak melempar exception dan `role` tersimpan benar di DB.
- **Root cause tambahan yang ikut ditutup:** `database/factories/UserFactory.php` juga tidak punya `role` default, jadi 13 test lama (scaffolding Auth/Profile bawaan Breeze) kena masalah yang sama persis saat request HTTP-nya menyentuh `HandleInertiaRequests::share()`. Ditambahkan `'role' => 'user'` ke `UserFactory::definition()` — menutup seluruh sisa kegagalan sekaligus, bukan per-test.

#### A.2 & A.3 — Hapus catch generik di Checkout & Webhook
- Sesuai rencana, tidak ada revisi dari draft awal. Terverifikasi: `CheckoutTest` (stok habis → `errors.stock` terisi + log `stock.rejected` tercatat) dan `WebhookTest` (signature invalid → HTTP 403, bukan 500) keduanya lolos.

### Verifikasi Fase A (final)
- `php artisan test` → **57/57 passed (142 assertions)**.
- `npm run build` → sukses tanpa error.
- Smoke manual: registrasi user baru → mendarat di dashboard tanpa error. Checkout stok habis → pesan error tampil + log muncul di `/admin/logs`. Webhook signature salah → 403.

---

## Fase A.5 — Migrasi Repository Pattern (di luar rencana awal, diminta user 2026-08-22)

**Konteks:** setelah Fase A selesai, audit lanjutan menemukan repository pattern (5 interface dari D1) ternyata cuma benar-benar dipakai di 2 file (`UserController`, `ProductService`) dari sekitar 20 file yang harusnya pakai — Auth dan service inti (Cart, Checkout, Inventory) masih akses Eloquent langsung. User minta migrasi Auth + service tersebut.

### Sudah dieksekusi & terverifikasi
| Area | Perubahan |
|---|---|
| Auth | `RegisteredUserController` → `UserRepositoryInterface::create()` (method `create` ditambahkan ke interface) |
| Cart | `CartService` full lewat `CartRepositoryInterface` (ditambah method baru `findDetailForProduct`, tadinya belum ada) |
| Checkout & Inventory | `CheckoutService`, `InventoryManager` (dikonversi dari static class ke instance service ter-inject), `PaymentWebhookService`, `ReleaseExpiredOrders` — semua lewat repository, termasuk versi ber-lock (`getActiveDraftForUserForUpdate`, `getForCheckout`, `findByProductAndSizeForUpdate`, `getExpiredDraftsForUpdate`) supaya `lockForUpdate()` tetap presis sama seperti sebelum migrasi (bukan cuma "swap ke repository yang ada" — repository lama tidak lock, jadi dibuatkan varian ber-lock khusus) |
| Baru | `ProductSizeRepositoryInterface`/`ProductSizeRepository` (belum ada sebelumnya — `InventoryManager` & `CheckoutService` operasi di level `ProductSize`, bukan `Product`) |

### Bug yang ketemu & ikut diperbaiki
1. **`OrderRepository::findOwned()` melanggar kontrak trait (LSP violation).** Signature `findOwned(int $id, int $userId)` bentrok dengan `OwnedScopes::findOwned(int $id, $user): Model` yang dipakai `BaseRepository` — fatal error `Declaration ... must be compatible` begitu class ini pertama kali benar-benar di-resolve dari container. Bug ini sudah ada sejak D1, tapi baru muncul sekarang karena `OrderRepositoryInterface` sebelumnya tidak pernah benar-benar di-inject ke mana pun (kode yang tidak pernah dijalankan tidak pernah kena fatal error). Fix: hapus override yang redundan di `OrderRepository`, biarkan warisan dari trait (perilakunya identik, cuma tipe parameter lebih fleksibel).
2. **Race-condition diverifikasi ulang secara nyata**, bukan cuma mengandalkan Pest (yang jalan sekuensial dalam satu koneksi, tidak pernah benar-benar menguji lock konkuren). Test: 2 user login asli via curl, checkout produk yang sama dengan stok=1, dua request `POST /checkout` ditembak paralel. Hasil: **aman** — stok akhir 0 (bukan minus), cuma 1 order yang jadi. Tapi user yang kalah race dapat **HTTP 500 mentah** (`SQLSTATE[40001]: Deadlock found`), bukan pesan "stok habis" yang ramah. Dikonfirmasi ini **bukan regresi dari migrasi** — query SQL yang dihasilkan repository identik dengan kode lama, jadi risiko deadlock ini sudah ada sejak awal, baru ketahuan sekarang karena diuji dengan request paralel sungguhan.

### A.5.1 & A.5.2 — dieksekusi & terverifikasi (2026-08-22)
| # | Item | File | Detail |
|---|---|---|---|
| A.5.1 | Index gabungan `(product_id, size)` | `database/migrations/2026_08_22_000000_add_composite_index_to_products_sizes.php` | Tabel `products_sizes` sebelumnya cuma punya index di `product_id` saja. |
| A.5.2 | Retry otomatis saat deadlock | `app/Services/CheckoutService.php` | `DB::transaction(function () {...}, 3)` — parameter `$attempts` bawaan Laravel. Hanya retry untuk `QueryException` deadlock/serialization failure; `EmptyCartException`/`InsufficientStockException` tetap langsung throw (bukan deadlock, tidak masuk kondisi retry Laravel). |

**Verifikasi ulang race-condition (produk baru, stok=1, 2 user login asli + curl paralel) setelah kedua fix:**
- Sebelum fix: user yang kalah dapat HTTP 500 (`SQLSTATE[40001]: Deadlock found`).
- **Setelah fix: kedua request 302** — pemenang redirect ke pembayaran, yang kalah redirect back dengan `errors.stock` terisi + log `stock.rejected` tercatat (`available: 0`). Tidak ada lagi 500 mentah.
- Stok akhir tetap 0 (tidak oversell), cuma 1 order terbentuk. Data test dibersihkan setelah verifikasi.

### Verifikasi Fase A.5 (final)
- `php artisan test` → 57/57 passed (tidak ada regresi dari seluruh migrasi + fix deadlock).
- `npm run build` → sukses.
- Race-condition test manual dijalankan 2x (sebelum & sesudah A.5.1/A.5.2) untuk membuktikan perbaikan nyata, bukan cuma teori.

---

## Fase B — Automation Testing Backend (Pest)

Isi gap 15 fitur yang saat ini nol test coverage (lihat `find tests -name '*.php'` di audit — hanya D1–D4 yang punya test). Satu file test per area fitur, ikuti pola existing (`tests/Feature/*.php`, helper function di atas, `RefreshDatabase`).

| Area | File test baru | Skenario minimum |
|---|---|---|
| Wishlist | `tests/Feature/WishlistTest.php` | toggle add/remove, hanya user login, wishlist milik user lain tidak kebuka |
| Search global | `tests/Feature/SearchTest.php` | query cocok nama produk, query kosong, query tanpa hasil |
| Notifikasi in-app user | `tests/Feature/NotificationTest.php` | notifikasi terkirim saat status order berubah, mark-as-read hanya untuk pemilik |
| Notifikasi admin order baru | `tests/Feature/AdminNotificationTest.php` | `pendingOrdersCount` bertambah saat ada order paid/processing baru |
| Bulk actions — User | `tests/Feature/Admin/UserBulkActionTest.php` | bulk delete, hanya admin, tidak bisa hapus diri sendiri (cek kalau ada guard ini) |
| Bulk actions — Order | `tests/Feature/Admin/OrderBulkActionTest.php` | bulk fulfillment update, hanya admin |
| Bulk actions — Produk | `tests/Feature/Admin/ProductBulkActionTest.php` | bulk delete, stok/gambar terkait ikut terhapus dengan benar |
| Export CSV | `tests/Feature/Admin/OrderExportTest.php` | response content-type csv, hanya admin, isi baris sesuai data |
| Export PDF (order & logs) | `tests/Feature/Admin/PdfExportTest.php` | response content-type pdf, hanya admin |
| Banner | `tests/Feature/Admin/BannerTest.php` | CRUD + toggle aktif/nonaktif, hanya admin, banner nonaktif tidak tampil di homepage |
| Tracking resi | `tests/Feature/OrderTrackingTest.php` | admin isi nomor resi → user bisa lihat di halaman pembayaran/order |
| Fulfillment status | `tests/Feature/Admin/FulfillmentTest.php` | update status single + bulk, hanya admin, hanya order yang sudah paid |
| Invoice | `tests/Feature/InvoiceTest.php` | owner/admin bisa akses, user lain 403, order belum paid 404 (sudah ada logikanya di controller, tinggal ditest) |
| Analytics | `tests/Feature/Admin/AnalyticsTest.php` | endpoint hanya admin, agregat angka sesuai data seed |

### Fase B — Batch 1 (2026-08-22)

**Status:** Selesai & terverifikasi — 4 file test baru, **86/86 test passing (324 assertions)** (+29 test baru dari baseline 57/57), `npm run build` sukses.

| File test | Skenario yang dites | Hasil |
|---|---|---|
| `tests/Feature/WishlistTest.php` | Tamu/admin dicegah akses (redirect/403), user lihat produk miliknya di Inertia view, isolasi data antar user, toggle tambah/hapus produk, validasi `product_id`. | **9/9 passed** (40 assertions) |
| `tests/Feature/SearchTest.php` | Akses publik tamu & user, pencarian cocok `name`, pencarian cocok `description`, query kosong return semua produk, query tanpa hasil return array kosong, passing props query. | **6/6 passed** (64 assertions) |
| `tests/Feature/OrderTrackingTest.php` | Admin update fulfillment status, resi, & kurir (`PATCH /admin/orders/{order}/fulfillment`), user lihat resi di `/pembayaran`, isolasi order antar user, proteksi non-admin & tamu (403/redirect), notifikasi in-app `OrderStatusUpdated` ke user, audit log `order.fulfillment_updated`, validasi status. | **8/8 passed** (47 assertions) |
| `tests/Feature/InvoiceTest.php` | Proteksi tamu (redirect login), owner & admin bisa akses invoice paid (`GET /payments/{payment}/invoice`), user lain 403, payment non-success (pending) 404, payment tanpa order 404, props Inertia view `Invoice`. | **6/6 passed** (31 assertions) |

### Fase B — Batch 2 (2026-08-22)

**Status:** Selesai & terverifikasi — 4 file test baru, **112/112 test passing (470 assertions)** (+26 test baru dari Batch 1: 86/86, baseline: 57/57), `npm run build` sukses.

| File test | Skenario yang dites | Hasil |
|---|---|---|
| `tests/Feature/NotificationTest.php` | Notifikasi in-app tersimpan di DB saat status order berubah, shared props Inertia `notifications`, pemilik dapat mark-as-read, user lain dapat 404 (tidak bisa mark-as-read milik orang lain), tamu redirect ke login. | **5/5 passed** (25 assertions) |
| `tests/Feature/AdminNotificationTest.php` | Shared prop `pendingOrdersCount` untuk admin (bernilai 0 saat kosong, bertambah saat ada order paid dengan status processing/null, berkurang saat status diubah ke shipped/delivered, mengabaikan order pending/unpaid), non-admin selalu menerima nilai 0. | **5/5 passed** (45 assertions) |
| `tests/Feature/Admin/BannerTest.php` | Admin melihat list banner di `/admin/banners`, proteksi non-admin & tamu (403/redirect), create banner + upload image, update banner + ganti image, toggle aktif/nonaktif, delete banner + hapus file storage, banner nonaktif tidak tampil di homepage, validasi input. | **9/9 passed** (51 assertions) |
| `tests/Feature/Admin/FulfillmentTest.php` | Admin single fulfillment update (`PATCH /admin/orders/{order}/fulfillment`), single update kirim notifikasi & log audit, admin bulk fulfillment update (`PATCH /admin/orders/bulk-fulfillment`), bulk update kirim notifikasi ke masing-masing customer, proteksi non-admin & tamu (403/redirect), validasi input. | **7/7 passed** (25 assertions) |

#### Temuan & Perbaikan saat Penulisan Test Batch 2
1. **Query `Order::bulkUpdateFulfillment` mengasumsikan kolom `payment_id` di tabel `orders`:**
   - Di `App\Http\Controllers\Admin\OrderController::bulkUpdateFulfillment()`, kode awal memanggil `Order::whereIn('payment_id', $request->ids)`.
   - Karena tabel `orders` tidak memiliki kolom `payment_id` (foreign key `order_id` ada di tabel `payments`), query ini menghasilkan SQL error `Unknown column 'payment_id'`.
   - Diperbaiki menjadi: `Order::whereHas('payment', fn($q) => $q->whereIn('id', $request->ids))->with('payment.user')->get()`.

### Fase B — Batch 3 (2026-08-22)

**Status:** Selesai & terverifikasi — 3 file test baru, **141/141 test passing (564 assertions)** (+29 test baru dari Batch 2: 112/112, Batch 1: 86/86, baseline: 57/57), `npm run build` sukses.

| File test | Skenario yang dites | Hasil |
|---|---|---|
| `tests/Feature/Admin/UserBulkActionTest.php` | Tamu redirect login & user 403 untuk `DELETE /admin/users/bulk`, admin bulk delete beberapa user sekaligus, guard tidak bisa hapus diri sendiri (filter `auth()->id()`), hanya id yang diminta yang terhapus, validasi `ids` wajib array & `exists:users,id`, validasi id tidak ada (422) | **8/8 passed** (27 assertions) |
| `tests/Feature/Admin/OrderBulkActionTest.php` | Tamu redirect & user 403 untuk `DELETE /admin/orders/bulk` & `PATCH /admin/orders/bulk-fulfillment`, admin bulk delete payments terpilih (`ids` → `exists:payments,id`), hanya payment yang diminta yang terhapus, admin bulk fulfillment update via payment ids (`ids` + `status` in:processing,shipped,delivered) → update `orders.fulfillment_status` lewat `whereHas('payment')`, notifikasi `OrderStatusUpdated` ke setiap pemilik order, validasi `ids`/`status` | **12/12 passed** (32 assertions) |
| `tests/Feature/Admin/ProductBulkActionTest.php` | Tamu redirect & user 403 untuk `DELETE /admin/products/bulk`, admin bulk delete produk beserta stok & gambar terkait (cascade `products_sizes`/`product_images` via FK + `ProductService::delete` hapus file), hanya produk yang diminta yang terhapus, audit log `product.delete` untuk setiap produk, validasi `ids` wajib array & `exists:products,id` | **9/9 passed** (35 assertions) |

#### Temuan & Perbaikan saat Penulisan Test Batch 3
1. **Payload bulk action:** semua endpoint bulk memakai key `ids` (array of id), bukan `id` tunggal. `admin/users/bulk` (`UserController::bulkDelete`) memfilter `auth()->id()` sebelum delete (self-guard). `admin/orders/bulk` (`Admin/OrderController::bulkDelete`) dan `admin/orders/bulk-fulfillment` (`bulkUpdateFulfillment`) keduanya memakai `exists:payments,id` — artinya payload berisi **payment id**, bukan order id (order di-lookup via `whereHas('payment', fn($q) => $q->whereIn('id', ids))`). `admin/products/bulk` (`ProductController::bulkDelete`) memakai `exists:products,id` dan menghapus lewat `ProductService::delete()` (hapus file storage + `products_sizes`/`product_images` ikut terhapus via FK cascade).
2. **Bug authorize `ProductController::bulkDelete`:** kode awal memanggil `$this->authorize('delete', Product::class)` yang melempar `ArgumentCountError` (policy `ProductPolicy::delete(?User, Product)` butuh instance Product, tidak bisa dipanggil dengan class string). Belum pernah terdeteksi karena endpoint ini belum ada test sebelumnya. Fix: tambah method `ProductPolicy::deleteAny(?User $user): bool` (cek `role === admin`) dan ubah controller menjadi `$this->authorize('deleteAny', Product::class)`. Perilaku sebelum fix: semua request bulk delete produk akan 500, bukan 403/validasi.
3. **Tidak ada guard tambahan untuk bulk delete selain yang sudah ada:** user bulk self-guard sudah benar, order & product bulk tidak punya guard self/ownership tambahan — sudah sesuai karena hanya admin yang bisa akses (middleware `role:admin`).

### Fase B — Batch 4 (2026-08-22) — FINAL

**Status:** Selesai & terverifikasi — 3 file test baru, **165/165 test passing (644 assertions)** (+24 test baru dari Batch 3: 141/141, Batch 2: 112/112, Batch 1: 86/86, baseline: 57/57), `npm run build` sukses. **14/14 file Fase B selesai.**

| File test | Skenario yang dites | Hasil |
|---|---|---|
| `tests/Feature/Admin/OrderExportTest.php` | Tamu redirect login & user 403 untuk `GET /admin/orders/export`, admin export CSV default (`text/csv`, disposition `orders_export_*.csv`, header `Order ID,Tanggal,User Email,Total Harga,Status` + baris per payment), isi baris sesuai 2 payments, header saja saat kosong, admin export PDF via `?format=pdf` (`application/pdf`, `%PDF`, disposition `.pdf`), proteksi PDF tamu/user | **8/8 passed** (33 assertions) |
| `tests/Feature/Admin/PdfExportTest.php` | Tamu/user 403/redirect untuk `GET /admin/orders/export?format=pdf` & `GET /admin/logs/export?format=pdf`, admin export orders PDF (`application/pdf`, `%PDF`), admin export logs PDF (`application/pdf`, `%PDF` via `pdf.logs` view), admin export logs CSV (`text/csv`, header `ID,Type,Action,...` + baris), header saja saat kosong, user 403 untuk logs CSV | **9/9 passed** (24 assertions) |
| `tests/Feature/Admin/AnalyticsTest.php` | Tamu redirect & user 403 untuk `GET /admin/analytics`, admin akses JSON `sales`+`categories`, sales agregat 7 hari terakhir (hanya `status=success` dalam 7 hari, pending/8 hari tidak ikut, group by date sum), kategori kosong→[], agregat `categories` via `order_details` join `products` (`whereNotNull category`, sum `quantity` per category), null category diabaikan | **7/7 passed** (23 assertions) |

#### Temuan & Perbaikan saat Penulisan Test Batch 4
1. **Response export riil:** `GET /admin/orders/export` default `text/csv` (`Content-Type: text/csv`, `Content-Disposition: attachment; filename="orders_export_Ymd_His.csv"`), `?format=pdf` → `application/pdf` via `Barryvdh\DomPDF` (`Content-Disposition: attachment; filename="orders_export_*.pdf"`, body diawali `%PDF`). `GET /admin/logs/export` default `text/csv` (`attachment; filename=activity_logs.csv`, header `ID,Type,Action,Description,User,Tanggal`), `?format=pdf` → `application/pdf` (`activity_logs.pdf`). `GET /admin/analytics` → `application/json` (`{"sales":[{"date","total"}],"categories":[{"category","total_sold"}]}`).
2. **Bug relasi PDF orders:** `Admin/OrderController::export()` untuk pdf memanggil `Payment::with("user","payment_details.product")` dan view `pdf/orders.blade.php` memakai `$payment->payment_details->first()`, padahal relasi di model bernama `paymentDetails()` (camelCase) — mengakibatkan `BadMethodCallException: Call to undefined relationship [payment_details]` dan PDF selalu 500 sebelum fix. Fix: ubah controller menjadi `with("user","paymentDetails.product")`, view menjadi `$payment->paymentDetails`, serta tambah alias `payment_details()` di `App\Models\Payment` agar kedua penulisan tetap kompatibel.
3. **Analytics hanya untuk admin:** endpoint `GET /admin/analytics` dijaga middleware `role:admin` (bukan policy) — tamu redirect login, user 403, hanya admin 200 JSON.

### Verifikasi Fase B — FINAL (Batch 1-4)

- `php artisan test` → **165 passed (644 assertions)** — **57 test lama + 108 test baru (14 file)**. Rincian: Batch 1 29 test, Batch 2 26 test, Batch 3 29 test, Batch 4 24 test. Zero regression. Per-file: `WishlistTest` 9, `SearchTest` 6, `OrderTrackingTest` 8, `InvoiceTest` 6, `NotificationTest` 5, `AdminNotificationTest` 5, `BannerTest` 9, `FulfillmentTest` 7, `UserBulkActionTest` 8, `OrderBulkActionTest` 12, `ProductBulkActionTest` 9, `OrderExportTest` 8, `PdfExportTest` 9, `AnalyticsTest` 7.
- **Catatan selisih angka planning:** planning awal tertulis "71 test: 57 lama + 14 baru" dengan asumsi 1 test per file. Implementasi riil menulis **multiple scenario per file** (rata-rata 7-8 skenario/file: guard tamu/user/admin, isolasi, validasi, agregat) sehingga total Fase B = **165 test (57+108)**, bukan 71. Jumlah **file** tetap 14/14 sesuai planning — yang berbeda adalah granularitas test per file, bukan cakupan fitur.
- `npm run build` → sukses tanpa error (built in 21.25s).
- Semua 14 file Fase B telah diverifikasi per-file via `php artisan test --filter=<Nama>` sebelum full suite.

---

## Fase C — E2E (Playwright)

**Kenapa Playwright, bukan Dusk:** stack sudah pakai Vite/Node untuk frontend, jadi toolchain Node bukan beban baru. Playwright lebih natural untuk interaksi SPA React+Inertia (auto-wait, trace viewer untuk debug kegagalan CI) dan tidak butuh ChromeDriver/Selenium yang sering rewel di CI container.

Rencana lengkap (termasuk temuan dari baca kode & keputusan yang dikunci bersama user) ada di
[`docs/planning-fase-c-playwright-e2e-2026-08-22.md`](./planning-fase-c-playwright-e2e-2026-08-22.md).

### Status Eksekusi (2026-08-22)

**Status: SELESAI & TERVERIFIKASI — 9/9 test hijau (2,2 menit), `php artisan test` 173/173, build sukses.**

Sesi lanjutan yang sama juga menutup dua temuan terbuka (alur pembayaran buntu & `payment_details`
kosong) — rinciannya di §10 dokumen `planning-fase-c-playwright-e2e-2026-08-22.md`.

Keputusan yang dikunci bersama user sebelum eksekusi: skenario 4 memakai **simulasi webhook lokal**
(bukan Snap sandbox asli), boleh mengubah kode produksi (testid + fix bug), stack dijalankan lewat
**Docker Compose penuh** (nginx `:8000` + php-fpm + MySQL), dan Fase D tidak dikejar sekarang.

#### Yang dibuat
| File | Isi |
|---|---|
| `playwright.config.ts` | `baseURL http://localhost:8000`, `workers: 1` (satu DB dibagi semua spec), `trace: 'retain-on-failure'`, `screenshot: 'only-on-failure'`, `use.serviceWorkers: 'block'`, `webServer` nonaktif (stack dikelola docker) |
| `.env.e2e` + `docker-compose.e2e.yml` | Container app membaca `.env.e2e` (DB terpisah `marketplace_batik_e2e`), jadi data dev tidak tersentuh |
| `scripts/e2e.sh` | `up -d --build` → tunggu `/up` → build aset → `npx playwright test` |
| `app/Console/Commands/E2eResetCommand.php` | `php artisan e2e:reset` — kosongkan 22 tabel + seed `E2ESeeder` dalam ~4 detik, dan menolak jalan kalau DB aktif bukan database E2E |
| `database/seeders/E2ESeeder.php` | Fixture deterministik: 4 user (admin/user/shopper/payer), 3 produk, keranjang melebihi stok, 1 order lunas, 1 tagihan pending |
| `tests/e2e/*.spec.ts` (5 file) | auth, checkout, catalog, fulfillment, payment |
| `tests/e2e/support/*` | `helpers.ts` (login/logout dua layout, signature Midtrans), `selectors.ts` (konstanta testid), `db.ts` (reset per test) |

#### Hasil per skenario
| Skenario | Hasil |
|---|---|
| 1. Registrasi → sesi → logout → login | **hijau** |
| 2. Browse + filter kategori + keranjang + checkout | **hijau** |
| 3. Checkout stok habis → pesan `errors.stock` tampil | **hijau** |
| 4. Klik Bayar Sekarang (Snap di-stub) + webhook Midtrans disimulasikan → status lunas | **hijau** |
| 5. Admin ubah fulfillment + resi → user melihat di `/pembayaran` | **hijau** |
| 6. Admin buat produk → muncul di filter kategori user | **hijau** |
| 7. Search global → hasil relevan | **hijau** |
| (tambahan) Login akun hasil seed | **hijau** |

Total: **8 passed** saat fase ini; ditambah 1 skenario (2b) pada sesi lanjutan → **9 passed** overall.

#### Bug produksi yang ditemukan oleh E2E (semua sudah diperbaiki di sesi ini)

1. **🔴 Webhook Midtrans membalas 419 — pembayaran tidak pernah terkonfirmasi di produksi.**
   Route `POST /midtrans/webhook` berada di middleware group `web` tanpa pengecualian CSRF, sedangkan
   Midtrans mengirim notifikasi dari server mereka tanpa token CSRF. Test suite Pest **tidak pernah
   bisa menangkap ini** karena `VerifyCsrfToken` otomatis dilewati saat `runningUnitTests()` — dan
   `WebhookTest` jelas hijau. Fix: `$middleware->validateCsrfTokens(except: ['midtrans/webhook'])` di
   `bootstrap/app.php`; keamanan tetap dijaga signature Midtrans di `PaymentWebhookService::verifySignature()`.
   Ini yang pertama kali membuat skenario 4 bisa melewati tahap webhook sama sekali.
2. **🔴 Halaman admin (Produk/Pesanan/Pelanggan) reload tanpa henti sejak dibuka.**
   `resources/js/hooks/useTableFilters.js` memakai **satu** `ref` untuk dua `useEffect`. Effect
   `search` (dideklarasikan lebih dulu) men-set ref itu jadi `false`, lalu effect `sort/direction`
   langsung menganggap ini bukan render pertama dan memanggil `router.reload()`. Karena Inertia
   me-remount komponen halaman pada setiap visit, ref ter-reset lagi → reload lagi → loop.
   Terukur: **18 request ke `/admin/products` dalam 5 detik** (setelah fix: 1). Gejalanya di E2E:
   modal produk admin tidak pernah selesai dimuat sehingga test tidak bisa mengisinya.
   Fix: dua ref terpisah (`firstSearch`, `firstSort`).
3. **🟠 Notifikasi toast di halaman admin tidak pernah tampil.** `Toaster` hanya di-mount di
   `WelcomeLayout`; seluruh halaman admin memakai `AdminLayout`. Padahal hampir semua aksi admin
   memanggil `toast.success`/`toast.error` (simpan produk, update status kirim, bulk action) —
   hasilnya selalu sunyi, admin tidak pernah tahu aksi berhasil atau gagal.
   Fix: mount `<Toaster />` di `AdminLayout`.
4. **🟠 Pesan error stok tidak pernah sampai ke user.** `bootstrap/app.php` mengirim `errors.stock`,
   tapi `Checkout.jsx` hanya membaca `errors?.message` → user hanya melihat "Gagal memproses checkout."
   tanpa penjelasan stok. Fix: baca `errors.stock ?? errors.message`. (Inilah alasan skenario 3
   masih hijau di Fase A.5: saat itu yang diverifikasi adalah backend + test, bukan layer UI-nya.)
5. **🟠 Client key Midtrans tidak pernah terkirim ke frontend.** `Payment.jsx` membaca
   `import.meta.env.VITE_MIDTRANS_CLIENT_KEY`, sedangkan `.env.example` hanya punya
   `MIDTRANS_CLIENT_KEY` (tanpa prefiks `VITE_`) → `data-client-key` bernilai `"undefined"` dan popup
   Snap tidak akan pernah muncul. Fix: `VITE_MIDTRANS_CLIENT_KEY` ditambahkan ke `.env.example`.

#### Temuan lain (belum diperbaiki, di luar scope Fase C — dicatat untuk sesi berikutnya)

- **~~🔴 Alur pembayaran tidak punya tombol di UI.~~ → SUDAH DIPERBAIKI (sesi lanjutan).** Kartu
  "Pesanan Belum Dibayar" + tombol `POST /payment` ditambahkan di `Payment.jsx`, `PaymentController::index`
  mengirim `unpaidOrder`, dan `store()` dipindah pemanggilan Midtrans ke luar transaksi DB supaya tidak
  ada payment yatim. Lihat §10 dokumen `planning-fase-c-playwright-e2e-2026-08-22.md`.
- **~~🟠 `payment_details` tidak pernah diisi.~~ → SUDAH DIPERBAIKI.** `PaymentController::store` kini
  menyalin `order_details` ke `payment_details`. Bonus: `Payment.jsx` menulis `item.price` padahal
  kolomnya `harga` — akan tampil "Rp NaN" begitu rincian item terisi.
- **🟠 Redirect setelah simpan produk (belum diperbaiki).** `ProductController::store` mengarahkan
  admin ke `admin.dashboard`, bukan kembali ke daftar produk, padahal aksi itu dilakukan dari
  `/admin/products`.

#### Catatan teknis yang sudah diverifikasi
- **Reset database**: `migrate:fresh --seed` butuh **~105 detik** (migrasi penuh) sehingga selalu
  memakan timeout per-test Playwright. Karena itu dibuat `php artisan e2e:reset` (DELETE per tabel +
  seed) yang selesai dalam **~4 detik**. `TRUNCATE` sempat dicoba dan justru **100 detik** untuk 22
  tabel di lingkungan virtual/container ini, jadi `DELETE FROM` yang dipakai.
- **`role` tidak bisa di-mass-assign di seeder.** `User::$fillable` sengaja tidak memuat `role`
  (kebijakan Fase A.1). `E2ESeeder` karena itu memasang `role` lewat property assignment lalu `save()`,
  pola yang sama dipakai `RegisteredUserController` — hasilnya tidak bergantung pada apakah seeder
  dipanggil lewat `db:seed` (yang aktif dalam keadaan unguarded) atau dipanggil langsung.
- **CSRF webhook tidak bisa diuji lewat Pest.** Karena token otomatis dilewati saat
  `runningUnitTests()`, verifikasi 419 ini hanya bisa dilakukan lewat E2E — inilah sebabnya
  skenario 4 punya nilai sebagai test, bukan sekadar formalitas.

### Verifikasi Fase C (final)
- `npx playwright test` → **9 passed (2.2 menit)**.
- `php artisan test` → **173 passed (704 assertions)** — nol regresi dari seluruh perubahan JSX,
  `bootstrap/app.php`, command baru, seeder baru, dan perbaikan alur pembayaran.
- `tests/Feature/PaymentInitiationTest.php` → 8 test baru untuk alur `POST /payment` (payment,
  `payment_details`, idempoten, gagal tanpa payment yatim, isolasi user).
- `npm run build` → sukses.
- Trace & screenshot on-failure aktif (`test-results/`, `playwright-report/` sudah di-`.gitignore`).

---

## Fase D — CI (GitHub Actions)

Belum ada `.github/workflows` sama sekali saat ini (dicek di audit). Minimal:

```
.github/workflows/ci.yml
  - checkout
  - setup PHP + composer install
  - setup Node + npm install
  - php artisan test
  - npm run build
  - (setelah Fase C ada) npx playwright test
```

Jalan di setiap push & pull request ke branch utama. Ini akan menangkap regresi seperti dua bug P0 di audit **sebelum** masuk ke `main`, bukan ketauan lewat audit manual seperti kemarin.

### Verifikasi Fase D
- Buat PR percobaan dengan bug yang disengaja (mis. balikin salah satu bug P0 sementara) → pastikan CI merah. Revert, pastikan CI hijau lagi.

---

## Checklist Master

- [x] **Fase A** — fix role registrasi (+ default role di `UserFactory`), hapus catch generik checkout & webhook.
- [x] **Fase A** — test 57/57 + build + smoke.
- [x] **Fase A.5** — migrasi repository pattern (Auth, Cart, Checkout, InventoryManager) + fix bug LSP `OrderRepository::findOwned`.
- [x] **Fase A.5** — index gabungan `(product_id, size)` + retry deadlock di `CheckoutService`, terverifikasi lewat race-condition test ulang.
- [x] **Fase B** - 14 file test baru untuk fitur Tahap 2–4. — **14/14 selesai & hijau (165/165, 644 assertions: 57 lama +108 baru)** — Batch 1: Wishlist, Search, OrderTracking, Invoice (29 test) | Batch 2: Notification, AdminNotification, Banner, Fulfillment (26 test) | Batch 3: UserBulk, OrderBulk, ProductBulk (29 test) | Batch 4: OrderExport, PdfExport, Analytics (24 test).
- [x] **Fase B** - full suite hijau — **165 passed (644 assertions), zero regression, build sukses**.
- [x] **Fase C** - setup Playwright + 7 skenario E2E. — **SELESAI & hijau: 8/8 test (7 skenario + 1 login tambahan), stack Docker Compose + DB E2E terpisah. Menemukan 2 bug P0 (webhook Midtrans 419, loop reload halaman admin) + 3 bug P1 yang semuanya sudah diperbaiki — rinciannya di `docs/planning-fase-c-playwright-e2e-2026-08-22.md`.**
- [ ] **Fase D** - GitHub Actions CI (pest + build, lalu + playwright).

---

## Prinsip Eksekusi (wajib saat mulai)

- **Tanpa perintah user, TIDAK ADA eksekusi.** Dokumen ini murni plan.
- Setiap fase selesai → lapor hasil + verifikasi → tunggu instruksi lanjut.
- Fase A harus 100% selesai & terverifikasi sebelum Fase B dimulai (menulis test terhadap kode yang bug-nya belum diperbaiki = kerja dua kali).
- Fase B boleh ditulis bertahap per area fitur kalau user mau prioritas tertentu duluan (mis. checkout/payment dulu karena paling kritis secara bisnis).
