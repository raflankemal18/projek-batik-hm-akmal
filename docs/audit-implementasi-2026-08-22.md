# Audit Implementasi — Status Nyata vs Dokumentasi (2026-08-22)

**Konteks:** Sejak D4 selesai (2026-08-12), ada ~35 commit tambahan yang mengeksekusi seluruh Tahap 0–4 dari `docs/planning-followup-audit-2026-08-12.md` (wishlist, search, notifikasi, bulk actions, export CSV/PDF, banner, tracking resi, analytics, dll — lihat `git log`). Checklist di dokumen tersebut menandai semua sudah "[x]" selesai dan hijau. Audit ini memverifikasi klaim itu dengan menjalankan test suite, build, dan membaca kode aktual — bukan hanya membaca dokumen.

**Hasil singkat:** Fitur-fiturnya **nyata ada** (bukan vaporware — sudah dicek via `route:list`, 59 route valid, `npm run build` sukses). Tapi test suite yang tadinya 57/57 hijau sekarang **16 gagal, 41 lolos**, dan ada 2 bug P0 yang berdampak ke user asli di produksi, bukan cuma soal test merah.

---

## 🔴 P0-1: User baru daftar berisiko langsung error setelah register

**File:** `app/Models/User.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`

**Akar masalah:** Kolom `role` (enum `admin`/`user`, default `user`) ada di tabel `users`, tapi **tidak ada di `$fillable`** milik model `User` — dan ini sudah begitu sejak commit D1 (`4f39c36`), bukan perubahan baru:

```php
protected $fillable = ['name', 'email', 'password']; // role hilang
```

Commit yang sama juga mengaktifkan `Model::shouldBeStrict(!app()->isProduction())` di `AppServiceProvider`. Efeknya: kalau sebuah `User` dibuat lewat mass-assignment **tanpa** eksplisit mengirim `role` (persis pola yang dipakai `RegisteredUserController::store()` untuk pendaftaran user asli), objek `User` di memori **tidak punya atribut `role` sama sekali** walau baris di DB punya nilai default `'user'` — karena Eloquent tidak reload dari DB setelah insert.

Saya verifikasi ini langsung lewat tinker:
```
$u2 = User::factory()->create();   // tanpa role, persis seperti registrasi asli
$u2->getAttributes()               // → role TIDAK ADA di array atribut
```

Masalahnya: `HandleInertiaRequests::share()` (jalan di **hampir semua request**) dan closure route `/dashboard` di `routes/web.php` sama-sama langsung akses `auth()->user()->role` tanpa guard. Total ada **19 titik pakai `->role`** di codebase (`app/`, `routes/`, `resources/js`).

**Dampak nyata:** Alur `RegisteredUserController::store()` → `Auth::login($user)` → `redirect(route('dashboard'))` memakai `$user` yang persis punya masalah ini. User yang **baru mendaftar hari ini di web produksi** berpotensi langsung kena error begitu sampai di halaman berikutnya — ini bukan bug kosmetik, ini alur onboarding inti.

**Bukti test yang gagal karena ini:** `AuthenticationTest::users can logout`, `ProfileTest` (5 test), `EmailVerificationTest` (2 test), `PasswordConfirmationTest` (3 test), `PasswordUpdateTest` (2 test) — semua pakai `User::factory()->create()` polos tanpa role eksplisit.

**Kenapa fitur lain (login admin, checkout, dll) tidak kena:** helper test seperti `logAdmin()`/`logUser()` di `ActivityLogTest` secara eksplisit passing `['role' => 'admin']` — dan factory Laravel memang bypass mass-assignment guard, jadi kalau role di-set eksplisit, aman. Masalah hanya muncul saat role **tidak** di-set eksplisit — persis kondisi registrasi user asli.

**Rekomendasi fix (pilih salah satu, bukan dua-duanya):**
- **Opsi aman:** di `RegisteredUserController::store()`, tambahkan `'role' => 'user'` eksplisit ke `User::create([...])`. Ini menutup lubang di jalur registrasi tanpa membuka `role` sebagai mass-assignable dari input user mana pun (mencegah privilege escalation via form registrasi).
- **Jangan** cukup menambahkan `'role'` ke `$fillable` tanpa audit lebih lanjut — itu berarti field `role` bisa di-mass-assign dari request manapun yang lupa whitelist, termasuk berpotensi dari form registrasi publik kalau ada perubahan di masa depan yang lupa strip field ini.

---

## 🔴 P0-2: `catch (\Exception)` generik di Checkout & Webhook menghapus kontrak error D3 dan mem-buta-kan audit log D4

**File:** `app/Http/Controllers/CheckoutController.php`, `app/Http/Controllers/PaymentController.php`

Commit "fix(backend): improve error handling and fallbacks" (`64d4b78`) menambahkan try/catch generik di dua tempat:

```php
// CheckoutController::store
} catch (\Exception $e) {
    return back()->withErrors(['message' => 'Gagal membuat pesanan: ' . $e->getMessage()]);
}

// PaymentController::webhook
} catch (\Exception $e) {
    return response()->json(['error' => 'Gagal memproses webhook: ' . $e->getMessage()], 500);
}
```

Masalahnya: `bootstrap/app.php` **sudah** punya exception handler khusus untuk `InsufficientStockException|EmptyCartException` yang (a) mengembalikan error di key session `errors.stock` sesuai kontrak D3, dan (b) mencatat `stock.rejected` ke `AuditLogService` — persis fitur yang dibangun di D4. Catch generik di atas **menangkap exception itu duluan**, sebelum sempat sampai ke handler terpusat. Akibatnya:

1. Frontend yang baca `errors.stock` untuk menampilkan pesan stok habis sekarang dapat `errors.message` — kemungkinan besar UI-nya tidak menampilkan apa-apa di tempat yang seharusnya.
2. **`stock.rejected` tidak pernah tercatat lagi di Activity Logs** untuk kasus checkout gagal karena stok — ini justru skenario paling penting yang D4 dirancang untuk menangkapnya (audit trail percobaan checkout yang ditolak sistem).
3. Webhook Midtrans dengan **signature tidak valid sekarang balas HTTP 500, bukan 403**. Ini bukan cuma soal test — Midtrans memperlakukan 5xx sebagai sinyal "coba kirim ulang", sementara 403 adalah penolakan final. Webhook palsu/rusak jadi di-retry terus oleh Midtrans alih-alih ditolak tegas.

**Rekomendasi fix:** hapus catch generik di kedua tempat ini (atau persempit jadi hanya menangkap exception yang benar-benar generik/tak terduga, dan tetap `throw` ulang untuk `InsufficientStockException`/`EmptyCartException`/exception signature webhook), biarkan handler terpusat di `bootstrap/app.php` yang menangani — itu sudah didesain benar sejak D3/D4.

---

## Hasil Test Suite Aktual (bukan asumsi dari checklist dokumen)

```
php artisan test
Tests: 16 failed, 41 passed (114 assertions)
```

16 gagal: 5× `ProfileTest`, 3× `PasswordConfirmationTest`, 2× `PasswordUpdateTest`, 2× `EmailVerificationTest`, 1× `AuthenticationTest`, 1× `CheckoutTest`, 1× `WebhookTest`, 1× `ActivityLogTest` — semua berakar dari dua bug P0 di atas, bukan 16 bug independen.

Dokumen `planning-followup-audit-2026-08-12.md` menandai setiap Tahap "Verifikasi: test hijau" — ini **tidak lagi benar** untuk state kode saat ini. Kemungkinan besar test terakhir kali benar-benar hijau adalah sebelum commit `64d4b78`/`435a36d` (dua commit "improve error handling" paling akhir), dan checklist tidak di-re-run setelah commit itu.

## Cakupan Test — Gap Besar di Fitur Tahap 2–4

`find tests -name '*.php'` hanya menghasilkan file test dari D1–D4 (Auth bawaan Breeze, Checkout, Webhook, Policy, RepositoryOwnership, Exception, ActivityLog, Profile). **Nol test** untuk ~15 fitur yang diklaim "Selesai" di checklist Tahap 2–4:

wishlist, search global, notifikasi in-app, notifikasi admin, bulk actions (user/order/produk — 3 fitur), export CSV, export PDF, manajemen banner, tracking resi, fulfillment status + endpoint bulk-nya, invoice, analytics dashboard.

Semua fitur ini punya route nyata dan build sukses, tapi **tidak ada jaring pengaman otomatis** — perubahan berikutnya bisa merusaknya tanpa ketahuan (persis seperti yang baru saja terjadi ke checkout & webhook).

## Status Dokumen Lain di `/docs`

- `docs/audit-website-2026-08-12.md` (audit awal) — kini **usang**: hampir semua temuan "belum dikerjakan"-nya sudah dieksekusi lewat Tahap 0–4. Tidak ada penanda di file ini bahwa isinya sudah superseded oleh `planning-followup-audit-2026-08-12.md`, jadi berisiko menyesatkan kalau dibaca berdiri sendiri di masa depan.
- `docs/superpowers/specs/*` (D1–D4) — masih akurat untuk apa yang dieksekusi saat itu (sudah diverifikasi & dikoreksi di audit sebelumnya), tidak terdampak oleh temuan baru ini.

---

## Bisa Automation Test untuk Web Ini?

**Bisa, dan infrastrukturnya sudah separuh jalan** — Pest sudah terpasang dan dipakai untuk backend (`tests/Feature/*`). Yang belum ada sama sekali: **CI** (tidak ada folder `.github/workflows`) dan **E2E/browser test** (tidak ada Dusk/Playwright/Cypress di `composer.json` maupun `package.json`).

Rekomendasi bertahap:

1. **Backend (Pest) — perluas cakupan, ini paling murah & paling mendesak.** Tulis feature test untuk 15 fitur yang masih nol-coverage di atas, minimal untuk jalur otorisasi (admin-only) dan efek samping penting (stok, log, notifikasi terkirim). Ini juga akan langsung menangkap kelas bug seperti P0-2 di atas kalau ada di masa depan.
2. **E2E/browser — untuk alur kritis lintas halaman** (daftar → login → browse → checkout → bayar Midtrans sandbox → admin ubah fulfillment → user lihat status). Untuk stack Laravel+Inertia+React, saya rekomendasikan **Playwright** dibanding Laravel Dusk: Playwright lebih natural untuk interaksi SPA React (auto-wait, trace viewer buat debug kegagalan CI), tidak perlu ChromeDriver/Selenium yang sering rewel, dan bisa jalan di GitHub Actions dengan container resmi. Dusk lebih masuk akal kalau tim mau tetap 100% di ekosistem PHP tanpa Node tooling tambahan — tapi Node toolchain di sini sudah ada (Vite), jadi Playwright bukan beban baru.
3. **CI** — GitHub Actions minimal: jalankan `php artisan test` + `npm run build` di setiap push/PR. Ini akan menangkap regresi seperti P0-1/P0-2 di atas **sebelum** masuk ke `main`, bukan ketauan pas audit manual seperti sekarang.

Saya belum implementasikan test baru atau CI apa pun — ini masih ranah audit + rekomendasi. Kalau mau saya lanjutkan, saya sarankan urutan: **fix P0-1 & P0-2 dulu** (kecil, jelas, dan test yang sudah ada balik hijau), baru setelah itu bahas skenario mana yang mau ditulis test-nya duluan / setup Playwright & CI.
