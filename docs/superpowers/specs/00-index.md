# Index — Marketplace Batik Refactor Specs

**Tanggal:** 2026-08-12

Peta dokumen dan urutan eksekusi wajib untuk 4 spec di folder ini. Baca file ini dulu sebelum mengeksekusi dokumen manapun — terutama kalau kamu (agent) cuma dikasih satu file spec tanpa konteks percakapan sebelumnya.

## Peta Dokumen

| Kode | File | Ringkasan |
|---|---|---|
| D1 | `2026-08-12-backend-refactor-plan.md` | Layering Controller/Service/Repository, rename model, migration tipe data, security guard (policy, rate limit), Exceptions |
| D2 | `2026-08-12-frontend-refactor-plan.md` | Hilangkan JWT/localStorage dari FE, unifikasi HTTP layer, bersihkan halaman mati |
| D3 | `2026-08-12-checkout-midtrans-business-plan.md` | Flow checkout server-authoritative, race condition handling, webhook Midtrans |
| D4 | `2026-08-12-activity-logging-plan.md` | Audit log user/admin/transaksi + admin UI |

## Urutan Eksekusi (wajib, jangan diacak)

```
D1 (backend-refactor)
   |
   +---> D2 (frontend-refactor)   -- boleh mulai coding paralel dengan D1,
   |                                  tapi verifikasi akhir (npm run build,
   |                                  test manual checkout) setelah D1 selesai
   |
   +---> D3 (checkout-midtrans)   -- WAJIB setelah D1 selesai (butuh
   |                                  InventoryManager, Exceptions,
   |                                  CheckoutRequest yang sudah dimodifikasi,
   |                                  kolom order_details.product_size_id)
   |                                  dan setelah D2 selesai (route /checkout
   |                                  dipanggil dari Checkout.jsx versi baru)
   |
   +---> D4 (activity-logging)    -- WAJIB paling terakhir, setelah D1 DAN D3
                                      selesai (hook logging dipasang ke
                                      CheckoutService, PaymentWebhookService,
                                      InventoryManager, MidtransService —
                                      semua class ini baru ada setelah D1+D3)
```

Kalau eksekusi dibagi ke beberapa agent paralel: **D1 harus selesai duluan (sequential gate)**. Setelah itu D2 dan D3 boleh jalan bersamaan asal D3 menunggu D2 selesai sebelum test end-to-end (D3 butuh `Checkout.jsx` sudah kirim POST `/checkout` tanpa `totalHarga`/`orders` dari client). D4 baru mulai setelah D1 dan D3 dua-duanya selesai.

## Ketergantungan silang penting (supaya tidak ada agent yang stuck)

- **`InventoryManager`** didefinisikan lengkap sekali di D1 §7, dipakai (bukan ditulis ulang) di D3 §2a/§3/§7 dan D4 (hook `stock.decrement`/`stock.restore`).
- **`InsufficientStockException` / `EmptyCartException`** didefinisikan di D1 §2b, dipakai di D3.
- **`CheckoutRequest`** sudah ada di codebase (`app/Http/Requests/CheckoutRequest.php`) dengan rule lama yang tidak aman (`totalHarga`, `orders` required dari client). D1 §6 mewajibkan ini **dimodifikasi**, bukan dibuat baru — dan ini prasyarat keamanan inti D3.
- **`order_details.product_size_id`** ditambahkan di D1 §2 (migration), diisi oleh `CheckoutService::createDraftOrder` di D3 §7, dipakai oleh semua pemanggilan `InventoryManager::restore()` di D3 (jangan pakai `$detail->size`, itu string label bukan ID).
- **Model `Products` → `Product`** (dan `product_image`→`ProductImage`, `ProductsSize`→`ProductSize`, `Carts`→`Cart`) di-rename di D1 §2 (Rename kelas PSR-4) sebelum langkah lain D1 dimulai — semua dokumen lain menyebut nama class barunya ("Product", dst) seolah sudah ada.
- **`localStorage`/`auth_token`** ditulis di `WelcomeLayout.jsx` (bukan cuma dipakai di `Checkout.jsx`/`bootstrap.js`) — lihat D2 §F1b. Checklist "tidak ada localStorage di seluruh resources/js/" butuh keempat file itu diubah (bootstrap.js, Checkout.jsx, WelcomeLayout.jsx, Header.jsx).

## Definisi Done (gabungan)

Refactor dianggap selesai kalau checklist di keempat dokumen ceklis semua, **dan**:
- `php artisan test` hijau
- `npm run build` sukses tanpa error
- Checkout end-to-end manual (add to cart -> checkout -> bayar sandbox Midtrans -> webhook -> status paid) berhasil di environment Docker (`docker compose up`, lihat `README.md` di root project)
