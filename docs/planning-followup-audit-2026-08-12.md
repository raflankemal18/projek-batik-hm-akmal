# Planning — Follow-up Hasil Audit Website

**Tanggal:** 2026-08-12
**Dasar:** `docs/audit-website-2026-08-12.md` + spec D4 (`docs/superpowers/specs/2026-08-12-activity-logging-plan.md`)
**Status:** PLAN — belum ada eksekusi. Eksekusi hanya boleh dimulai setelah user memberi perintah.
**Aturan fase:** Setiap tahap WAJIB diverifikasi (test + build + smoke) sebelum lanjut ke tahap berikutnya. Tidak ada eksekusi paralel antar tahap yang menyentuh file yang sama.

---

## Peta Tahapan & Dependensi

```
Tahap 0 (Quick Wins Audit)
   |   ── syarat wajib sebelum D4 (edit method yang sama dengan D4)
   v
Tahap 1 (D4 Activity Logging)
   |
   v
Tahap 2 (Perbaikan Audit prioritas sedang)
   |
   v
Tahap 3 (Nice-to-have, per fitur = mini-spec sendiri)
   |
   v
Tahap 4 (Analytics grafik + data dashboard asli)
```

**Alasan Tahap 0 wajib sebelum D4** (dikonfirmasi user):
Tahap 0 dan D4 menyentuh file yang sama — `ProductController.php` dan `AdminDashboardController.php`. Kalau D4 jalan duluan, hook logging (D4 §3) nyantol di versi method yang belum final; saat Tahap 0 ngedit method yang sama, risiko hook kesenggol/ketimpa tinggi. Tahap 0 duluan → D4 tinggal nyuntik hook ke versi method yang sudah stabil.

---

## Tahap 0 — Quick Wins Audit (Prioritas Tinggi, risiko rendah)

Tujuan: bersihkan pemborosan & bug kecil tanpa mengubah perilaku produk. **Dilarang** menambahkan fitur baru di tahap ini.

| # | Item (referensi audit) | File yang disentuh | Detail |
|---|---|---|---|
| 0.1 | Hapus dependency mati (§1.1) | `package.json` | Uninstall 100% mati: `@mantine/core`, `@mantine/hooks`, `@mantinex/mantine-logo`, `postcss-preset-mantine`, `@coreui/coreui`, `@coreui/react`, `bootstrap`, `@inertiajs/inertia-react`, `tabler-icons-react`, `@tabler/icons-react`, `react-loading-icons`, `react-rating-stars-component`, `@nivo/line`, `@chakra-ui/icons`. Uninstall yang tinggal dipakai 1 file: `@chakra-ui/react`, `@emotion/react`, `@emotion/styled`, `@nextui-org/react`, `@nextui-org/button`, `@nextui-org/system`, `@nextui-org/theme`. |
| 0.2 | Hapus import Chakra di app.jsx (§1.1) | `resources/js/app.jsx` | `ColorModeProvider`/`ThemeProvider` di line 8 **hanya di-import, tidak pernah dipakai membungkus apapun** — `setup()` cuma `root.render(<App {...props} />)`. Ini import mati total, bukan mekanisme dark mode. **Keputusan: hapus baris import. JANGAN** ganti dengan provider lain / bikin fitur dark mode (itu fase terpisah). |
| 0.3 | Ganti NextUI Link di footer (§1.1) | `resources/js/Components/component/footer.jsx` | Ganti `Link` dari `@nextui-org/react` → `<Link>` dari `@inertiajs/react` (pola sudah dipakai di tempat lain). Baru boleh uninstall package NextUI di 0.1. |
| 0.4 | Hapus angka palsu admin Dashboard (§1.3) | `resources/js/Pages/admin/Dashboard.jsx` | Hapus 3 kartu `desc` hardcoded (`"+20.1%"`, `"+12 Order"`, `"+5 User"`) + panel "Target Bulan Ini". **Keputusan:** hapus dulu, versi asli (hitung delta bulan lalu) dikerjakan di Tahap 4. |
| 0.5 | Field kategori di form admin produk (§2.1) | `resources/js/Pages/admin/form/Form.jsx`, `app/Http/Requests/ProductRequest.php`, cek `ProductController.php` | Tambah `<select name="category">` opsi `( — ) / pria / wanita / sarimbit`. **Value harus persis** `"" / "pria" / "wanita" / "sarimbit"` — sinkron dengan id tab di `resources/js/Components/ProductCollection.jsx` line 14-17, supaya filter frontend user langsung nyambung tanpa ubah `ProductCollection.jsx`. Backend: whitelist value di `ProductRequest`. |
| 0.6 | Fix bug mobile nav (§2.6) | `resources/js/Components/Header.jsx` | Bungkus `MobileNavLink href="/pembayaran"` dengan `{isUser && (...)}` — samakan dengan versi desktop yang sudah di-guard. |
| 0.7 | Hapus kode mubazir (§1.4) | `app/Services/ProductService.php` | Hapus `$this->imageService->delete(null)` di `delete()` (no-op). |

### File yang TIDAK disentuh (jelas & disengaja)
- `Components/ui/*` class `dark:` — default styling shadcn/ui, **tidak aktif** (tidak ada toggle/context/`<html class="dark">`). Zero runtime cost, biarkan.
- `tailwind.config.js` `darkMode: "class"` — biarkan untuk fase dark mode terpisah.

### Verifikasi Tahap 0
- `php artisan test` → 49/49 hijau.
- `npm run build` → sukses tanpa error.
- Smoke: admin buka form produk → pilih kategori "wanita" → save → `/products?category=wanita` menampilkan produk tsb.
- Smoke: header mobile sebagai admin/guest → tidak ada link "pesanan saya".

---

## Tahap 1 — D4 Activity Logging (Spec: `2026-08-12-activity-logging-plan.md`)

Ikuti spec D4 verbatim (data model §1, service §2, event map §3, UI §4, retention §5, security §6, perf §7, tests §8).

### 1.1 Foundation
- Migration `create_activity_logs_table`: `type` enum, `user_id` nullable FK, `email` nullable (copy, survive user delete), `subject_type`/`subject_id` nullable, `action`, `description` nullable, `context` json nullable, `created_at` indexed. Index: type, user_id, subject_type+subject_id, created_at.
- Model `App\Models\ActivityLog` (fillable, casts `context => array`).
- `app/Services/AuditLogService.php`: `recordUser` / `recordAdmin` / `recordTransaction` → private `record` pakai `DB::afterCommit`. Sanitizer redact: password, password_confirmation, token, snap_token, server_key, card_number, cvv, exp_month, exp_year.

### 1.2 Hook Event User (Breeze)
| File | Action |
|---|---|
| `RegisteredUserController::store` | `recordUser('register', desc, ['email'])` |
| `AuthenticatedSessionController::store` (sukses) | `recordUser('login.success', ['email'])` |
| `AuthenticatedSessionController::store` (gagal) | `recordUser('login.failed', ['email','reason'])` |
| `AuthenticatedSessionController::destroy` | `recordUser('logout')` |
| `ProfileUpdateController::update` | `recordUser('profile.update', context {before, after})` |
| `PasswordController::update` | `recordUser('password.change')` |
| `ProfileUpdateController::destroy` | `recordUser('account.delete')` **SEBELUM** user dihapus (capture email dulu) |

### 1.3 Hook Event Admin
| File | Action |
|---|---|
| `AuthenticatedSessionController::store` | tambah `recordAdmin('admin.login', ['email'])` jika role admin |
| `ProductController::store` | `recordAdmin('product.create', $product, {name, harga, sizes, image_count})` |
| `ProductController::update` | `recordAdmin('product.update', $product, {before, after, images_added, images_removed})` |
| `ProductController::destroy` | `recordAdmin('product.delete', $product, {name, harga, image_count})` |
| Controller user delete (cek `AdminDashboardController`) | `recordAdmin('user.delete', null, {deleted_user_id, deleted_email})` sebelum delete |
| Controller user update (jika ada) | `recordAdmin('user.update', $user, {before, after})` |

> **Catatan:** hooks ini dipasang ke versi method yang SUDAH FINAL dari Tahap 0 — inilah kenapa Tahap 0 wajib duluan.

### 1.4 Hook Event Transaksi (service D3)
| File/Service | Action |
|---|---|
| `CheckoutService::createDraftOrder` | `recordTransaction('checkout.create', $order, {items, total})` |
| `InventoryManager::decrement` (sukses) | `recordTransaction('stock.decrement', null, {product_id, size, qty, stock_before, stock_after})` |
| InventoryManager (gagal/habis) | `recordTransaction('stock.rejected', null, {product_id, size, requested_qty, available})` |
| `InventoryManager::restore` | `recordTransaction('stock.restore', null, {product_id, size, qty, stock_before, stock_after, reason})` |
| `MidtransService::createTransaction` | `recordTransaction('payment.initiated', $payment, {payment_id, transaction_id, total_harga})` |
| `PaymentWebhookService::handle` | `recordTransaction('webhook.received', null, {transaction_id, status_code, transaction_status, signature_valid})` |
| PaymentWebhookService (paid) | `recordTransaction('payment.paid', $payment, {transaction_id, total_harga})` |
| PaymentWebhookService (deny) | `recordTransaction('payment.failed', $payment, {reason})` |
| PaymentWebhookService (expire) | `recordTransaction('payment.expired', $payment)` |
| PaymentWebhookService (batal) | `recordTransaction('order.cancelled', $order, {order_id, reason})` |
| `ReleaseExpiredOrders` | `recordTransaction('draft.expired', null, {order_id})` |

### 1.5 Admin UI Logs
- `app/Http/Controllers/Admin/ActivityLogController.php` — filter `type`/`date_from`/`date_to`/`search`, `paginate(20)`, render `admin/Logs`.
- Route `GET /admin/logs` → name `admin.logs`, middleware `auth`, `role:admin`, `throttle:30,1`, `LogsPolicy@view` (gate define di `AuthServiceProvider`).
- `resources/js/Pages/admin/Logs.jsx`:
  - Tab filter: Semua | User | Admin | Transaksi (badge count per type).
  - Form filter: date range + search (action/description/email), submit GET.
  - Tabel: waktu, badge type (user=blue, admin=purple, transaction=green), actor (email), action, subject (`ModelName: id`), description, tombol "Detail" → expandable JSON context (`<details><pre>`).
  - Pagination: komponen `Pagination` existing + hook `useTableFilters`.
- Sidebar admin: tambah menu "Activity Logs" (icon lucide).

### 1.6 Retention
- `app/Console/Commands/LogsCleanup.php` → `logs:cleanup {--months=12}`.
- `routes/console.php`: `Schedule::command('logs:cleanup')->daily();`.

### 1.7 Tests (spec D4 §8) — `tests/Feature/ActivityLogTest.php`
1. Login gagal → log `login.failed` tercatat + email benar.
2. Product update → log `product.update` + before/after.
3. Checkout draft → log `checkout.create` + items detail.
4. Webhook paid → log `webhook.received` + `payment.paid`.
5. Stock reject → log `stock.rejected` + detail.
6. Non-admin akses `/admin/logs` → 403.
7. Password/token tidak di-log (context ter-redact).
8. User delete → log `user.delete` tetap ada walau user dihapus (email copy).

### Verifikasi Tahap 1
- `php artisan test` → 49 lama + ~8 baru hijau.
- `npm run build` → sukses.
- Smoke: login gagal + sukses, buat produk admin, checkout, webhook paid → semua muncul di `/admin/logs`. User biasa → `/admin/logs` 403.

---

## Tahap 2 — Perbaikan Audit Prioritas Sedang

| # | Item (referensi audit) | Detail |
|---|---|---|
| 2.1 | Wire-up Invoice (§2.3) | **Keputusan: wire, JANGAN hapus** (99 baris kode siap pakai). Route `GET /payments/{id}/invoice` (auth, owner/admin, policy) + render `resources/js/Pages/Invoice.jsx` + tombol "Cetak Invoice" di `Payment.jsx` per transaksi paid. |
| 2.2 | Fulfillment status order (§2.5, §3.2) | Migration `add_fulfillment_status_to_orders` — enum nullable `processing`/`shipped`/`delivered`. Endpoint `PATCH /admin/orders/{id}/fulfillment` + policy (admin). Dropdown/select di `admin/Order.jsx` di samping `StatusBadge`. User: timeline status di `Payment.jsx`. |
| 2.3 | Pagination UI `Payment.jsx` (§2.2) | Backend sudah `paginate(10)`. Pasang komponen `Pagination` + hook `useTableFilters` di bawah list. |
| 2.4 | Cart badge header (§2.7) | Shared data `cartCount` di `HandleInertiaRequests` middleware (hitung jumlah qty cart user login). Badge angka di ikon `ShoppingBag` di `Header.jsx`. |
| 2.5 | Isi `user/Dashboard.jsx` (§2.4) | Ringkasan 3 metrik (total pesanan, dalam proses, selesai) + 5 pesanan terakhir + CTA "Lihat Semua". Data dari `UserDashboardController` baru (atau extend controller existing). |

### Verifikasi Tahap 2
- `php artisan test` hijau, `npm run build` hijau.
- Smoke flow: checkout → bayar → status paid → cetak invoice → admin ubah fulfillment ke "dikirim" → user lihat timeline → pagination next page.

---

## Tahap 3 — Nice-to-have (audit §3.1, §3.2 — prioritas rendah)

Eksekusi per fitur, **masing-masing butuh mini-spec tersendiri yang dikonfirmasi user dulu** (schema + keputusan desain belum ditetapkan).

| Fitur | Catatan desain yang perlu diputuskan |
|---|---|
| Wishlist | Schema `wishlists(user_id, product_id)`; toggle di `ProductDetail`; halaman `/wishlist`. |
| ~~Review & rating~~ | (Skip sesuai request user 2026-08-12) Schema `reviews`; moderasi vs auto-publish; rata-rata rating di kartu produk. |
| Search bar global | Input di navbar → `GET /search?q=` → halaman hasil. |
| Notifikasi admin order baru | Badge sidebar via polling 30s / Inertia shared prop. |
| Bulk actions admin | Checkbox multi-select + hapus/ubah massal di `admin/User.jsx`, `admin/Order.jsx`. |
| Export laporan CSV | `GET /admin/orders/export.csv` (streamed response). |
| Manajemen banner/promo | Tabel `banners` + CRUD admin + render di `ModernHero.jsx` dari prop. |
| Role & permission granular | Spatie Laravel Permission; refactor `RoleMiddleware`; peran menengah (staff gudang). |
| Notifikasi in-app user | Update status pesanan/promo. |
| Live chat / kontak CS | Butuh third-party (Crisp/Tawk) — di luar scope aplikasi sendiri. |
| Tracking resi | Field nomor resi/kurir di orders + tampilan user (tumpang tindih dengan 2.2). |

### Verifikasi Tahap 3
- Per fitur: `php artisan test` + `npm run build` + smoke manual.

---

## Tahap 4 — Analytics & Dashboard Data Asli

- **Library:** Recharts (ringan) — **JANGAN** pakai nivo (besar, sudah di-uninstall di 0.1).
- Backend: `AdminDashboardController::analytics` (atau method baru) — agregat `payments` per hari/minggu/bulan (SQL aggregate, whitelist periode).
- Halaman `/admin/analytics` (atau embed di dashboard): 3 chart — tren penjualan, top products (sudah ada), retention sederhana.
- **Kembali ke 0.4:** hitung ulang kartu statistik dashboard dari data asli — delta penjualan bulan ini vs bulan lalu, order baru, user baru.

### Verifikasi Tahap 4
- `php artisan test` + `npm run build` + smoke: grafik muncul dengan data seeder.

---

## Checklist Master

- [x] **Tahap 0** — uninstall deps mati, hapus import Chakra, footer Link, hapus angka palsu dashboard, field kategori form, fix mobile nav, hapus `delete(null)`.
- [x] **Tahap 0** — test 49/49 + build + smoke.
- [x] **Tahap 1** — migration + model + AuditLogService + sanitizer + afterCommit.
- [x] **Tahap 1** — hooks user, admin, transaksi.
- [x] **Tahap 1** — /admin/logs + Logs.jsx + policy + route + sidebar.
- [x] **Tahap 1** — logs:cleanup + scheduler.
- [x] **Tahap 1** — 8 test D4 §8 + test lama hijau.
- [x] **Tahap 2** — invoice wire, fulfillment, pagination Payment, cart badge, (user dashboard dihapus sesuai request user).
- [x] **Tahap 3** — Wishlist (schema, tombol heart aktif, badge navbar, halaman `/wishlist`).
- [x] **UI/UX Tambahan** — Refactor Profile Edit & ForgotPassword sesuai tema BatikNesia, ubah tombol logout menjadi Dropdown Profile modern.
- [ ] **Tahap 3** — Sisa fitur Tahap 3 (per fitur dengan mini-spec terkonfirmasi).
- [x] **Tahap 4** — analytics + data dashboard asli (deltas & recharts).

---

## Keputusan Final yang Sudah Dikonfirmasi User

1. **Chakra provider:** hapus seluruhnya (import mati, bukan mekanisme dark mode). Fitur dark mode beneran = fase terpisah. ✅
2. **Footer NextUI Link:** ganti ke Inertia `<Link>`. ✅
3. **Angka palsu dashboard:** hapus di Tahap 0, versi asli di Tahap 4. ✅
4. **Field kategori form:** `<select>` value `"" / "pria" / "wanita" / "sarimbit"` sinkron `ProductCollection.jsx`. ✅
5. **Invoice:** di-wire, bukan dihapus. ✅
6. **Fulfillment status:** dikerjakan (Tahap 2 #2). ✅
7. **Cart badge:** dikerjakan (Tahap 2 #4). ✅
8. **Urutan:** Tahap 0 → D4 → Tahap 2 → Tahap 3+4. ✅

---

## Prinsip Eksekusi (wajib saat mulai)

- **Tanpa perintah user, TIDAK ADA eksekusi.** Dokumen ini murni plan.
- Setiap tahap selesai → lapor hasil + verifikasi → tunggu instruksi lanjut.
- Tahap 3: sebelum eksekusi tiap fitur, ajukan mini-spec untuk dikonfirmasi.
- Ikuti pola kode existing (repositories/services/controllers, hook `useTableFilters`, komponen `Pagination`, `storageUrl`/`FallbackImage`).
