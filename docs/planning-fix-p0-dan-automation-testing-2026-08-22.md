# Planning — Fix P0 & Automation Testing

**Tanggal:** 2026-08-22
**Dasar:** `docs/audit-implementasi-2026-08-22.md`
**Status:** EKSEKUSI — Fase A & Fase A.5 (migrasi repository pattern) selesai & terverifikasi (2026-08-22). Lanjut ke 2 item follow-up dari Fase A.5.
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

### Verifikasi Fase B
- `php artisan test` → semua file baru hijau + tidak merusak test lama.
- Coverage kasar (opsional): `php artisan test --coverage` kalau Xdebug/PCOV tersedia di image Docker — cek dulu sebelum janji angka coverage.

---

## Fase C — E2E (Playwright)

**Kenapa Playwright, bukan Dusk:** stack sudah pakai Vite/Node untuk frontend, jadi toolchain Node bukan beban baru. Playwright lebih natural untuk interaksi SPA React+Inertia (auto-wait, trace viewer untuk debug kegagalan CI) dan tidak butuh ChromeDriver/Selenium yang sering rewel di CI container.

### C.1 Setup
- `npm install -D @playwright/test` + `npx playwright install --with-deps chromium`.
- `playwright.config.ts` — `baseURL` mengarah ke instance Docker lokal (atau `php artisan serve` di CI), `webServer` config supaya Playwright auto-start app sebelum test jalan.
- Folder `tests/e2e/`.

### C.2 Alur kritis yang perlu dites (urutan prioritas)
1. **Registrasi → login → landing di dashboard yang benar** (ini yang paling relevan — langsung memverifikasi Fase A.1 tidak regresi lagi di masa depan).
2. Browse produk → filter kategori → tambah ke keranjang → checkout → isi alamat.
3. Checkout dengan stok habis → pesan error tampil (verifikasi Fase A.2).
4. Bayar via Midtrans **sandbox** (pakai kredensial sandbox, bukan produksi) → status order berubah jadi paid.
5. Admin: lihat order baru → ubah fulfillment status → user melihat perubahan status di halaman pembayaran.
6. Admin: buat produk baru dengan kategori → cek muncul di filter user.
7. Search global → hasil relevan muncul.

### Verifikasi Fase C
- Semua skenario di atas hijau lokal (`npx playwright test`) sebelum masuk ke CI.
- Screenshot/trace on-failure aktif (`trace: 'retain-on-failure'`) supaya kegagalan CI gampang didiagnosis tanpa akses server.

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
- [ ] **Fase B** - 14 file test baru untuk fitur Tahap 2–4.
- [ ] **Fase B** - full suite hijau.
- [ ] **Fase C** - setup Playwright + 7 skenario E2E.
- [ ] **Fase D** - GitHub Actions CI (pest + build, lalu + playwright).

---

## Prinsip Eksekusi (wajib saat mulai)

- **Tanpa perintah user, TIDAK ADA eksekusi.** Dokumen ini murni plan.
- Setiap fase selesai → lapor hasil + verifikasi → tunggu instruksi lanjut.
- Fase A harus 100% selesai & terverifikasi sebelum Fase B dimulai (menulis test terhadap kode yang bug-nya belum diperbaiki = kerja dua kali).
- Fase B boleh ditulis bertahap per area fitur kalau user mau prioritas tertentu duluan (mis. checkout/payment dulu karena paling kritis secara bisnis).
