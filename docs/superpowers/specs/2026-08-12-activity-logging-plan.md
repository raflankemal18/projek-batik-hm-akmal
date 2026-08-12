# Activity & Transaction Logging Plan

**Kode Dokumen:** D4
**Tanggal:** 2026-08-12
**Status:** Dieksekusi (2026-08-12) — lihat catatan koreksi di §1 dan §10.
**Dependency:** D1 + D3 selesai (WAJIB — dokumen ini memasang hook logging ke `CheckoutService`, `PaymentWebhookService`, `InventoryManager`, `MidtransService`, dan Controllers hasil refactor D1; semua class itu baru ada setelah D1+D3 dieksekusi. Jangan mulai dokumen ini lebih dulu / paralel dengan D1 atau D3). Lihat [00-index.md](./00-index.md).

## Tujuan

Semua aktivitas terlacak & bisa dicek admin di aplikasi (bukan cuma file log), dengan 3 kategori: **user**, **admin**, **transaksi**. Single-merchant (HM Akmal), jadi tidak ada log multi-seller.

## 1. Data Model

Migration `create_activity_logs_table`:

```php
$table->id();
$table->enum('type', ['user', 'admin', 'transaction'])->index();
$table->foreignId('user_id')->nullable()->index(); // causer
$table->string('email')->nullable(); // copy email, survive user delete
$table->string('subject_type')->nullable(); // Model::class
$table->string('subject_id')->nullable(); // model id
$table->string('action'); // e.g. login, product.delete, payment.paid
$table->text('description')->nullable();
$table->json('context')->nullable(); // ip, user_agent, before, after, extras
$table->timestamp('created_at')->index(); // untuk filter tanggal
```

### Index

```
type
user_id
subject_type + subject_id
created_at
```

### Rules

- **Append-only** — tidak ada route update/delete log.
- **Jangan log**: password, token, snap_token, server key, kartu kredit → sanitize di formatter.
- **~~DB::afterCommit~~ — KOREKSI (dieksekusi 2026-08-12):** Ide awalnya bagus (log ditulis setelah transaksi bisnis commit, supaya rollback tidak meninggalkan log yatim), tapi **tidak dipakai di implementasi final**. Dua alasan: (1) `DB::afterCommit()` **tidak pernah fire di dalam test** yang pakai `RefreshDatabase` — trait itu membungkus tiap test dalam transaksi yang di-rollback di akhir, bukan di-commit, jadi semua assertion `ActivityLog::where(...)->exists()` selalu `false` meski kodenya benar. Dites langsung dan gagal 7 dari 8 test sebelum di-fix. (2) Ternyata **tidak perlu** — implementasi final menaruh tiap panggilan `AuditLogService::record*()` PERSIS di dalam blok `DB::transaction()` yang sama dengan aksi bisnis yang di-log (atau di luar transaksi kalau aksinya sudah final/transaksinya sudah rollback duluan, contoh: `stock.rejected` di-log dari exception handler `bootstrap/app.php`, BUKAN dari titik `throw`-nya, karena saat itu transaksi checkout-nya sudah pasti rollback). Dengan penempatan begini, `ActivityLog::create()` langsung (tanpa `afterCommit`) otomatis ikut rollback bareng aksi bisnisnya kalau ada yang gagal — guarantee "no orphan log" tetap dapat, tanpa masalah testability. Lihat `App\Exceptions\InsufficientStockException` (property `$context` baru) dan `bootstrap/app.php` untuk pola log-setelah-rollback ini.

## 2. AuditLogService

```php
// app/Services/AuditLogService.php

class AuditLogService
{
    public static function recordUser(string $action, ?string $description = null, array $context = []): void
    {
        self::record('user', $action, $description, $context);
    }

    public static function recordAdmin(string $action, $subject = null, ?string $description = null, array $context = []): void
    {
        self::record('admin', $action, $description, $context, $subject);
    }

    public static function recordTransaction(string $action, $subject = null, ?string $description = null, array $context = []): void
    {
        self::record('transaction', $action, $description, $context, $subject);
    }

    private static function record(string $type, string $action, ?string $description, array $context, $subject = null): void
    {
        $causer = auth()->user();

        DB::afterCommit(function () use ($type, $action, $description, $context, $subject, $causer) {
            ActivityLog::create([
                'type' => $type,
                'user_id' => $causer?->id,
                'email' => $causer?->email,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->id,
                'action' => $action,
                'description' => $description,
                'context' => array_merge($context, [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]),
            ]);
        });
    }
}
```

### Sanitizer (dipanggil sebelum log)

```php
private static function sanitize(array $context): array
{
    $sensitive = ['password', 'password_confirmation', 'token', 'snap_token',
                  'server_key', 'card_number', 'cvv', 'exp_month', 'exp_year'];

    foreach ($sensitive as $key) {
        if (isset($context[$key])) {
            $context[$key] = '***REDACTED***';
        }
    }

    return $context;
}
```

## 3. Peta Event Logging (dipanggil dari Service/Controller)

### Kategori: user

| Event | Action | Description | Context |
|---|---|---|---|
| Register | `register` | User baru daftar | `{ email }` |
| Login sukses | `login.success` | Login berhasil | `{ email }` |
| Login gagal | `login.failed` | Password salah | `{ email, reason }` |
| Logout | `logout` | User logout | - |
| Update profil | `profile.update` | Update nama/email | `{ before, after }` |
| Ganti password | `password.change` | Password diganti | - |
| Hapus akun | `account.delete` | User request hapus | - |

### Kategori: admin

| Event | Action | Description | Context |
|---|---|---|---|
| Login admin | `admin.login` | Admin login | `{ email }` |
| Product create | `product.create` | Produk baru | `{ name, harga, sizes, image_count }` |
| Product update | `product.update` | Produk diubah | `{ before: {name,harga,...}, after: {...}, images_added, images_removed }` |
| Product delete | `product.delete` | Produk dihapus | `{ name, harga, image_count }` |
| User delete | `user.delete` | User dihapus | `{ deleted_user_id, deleted_email }` |
| User update | `user.update` | User diubah | `{ before, after }` |

### Kategori: transaction

| Event | Action | Description | Context |
|---|---|---|---|
| Checkout draft | `checkout.create` | Draft order dibuat | `{ order_id, items: [{product, size, qty, harga, subtotal}], total }` |
| Payment initiated | `payment.initiated` | Payment + snap token | `{ payment_id, transaction_id, total_harga }` |
| Stock decrement | `stock.decrement` | Stok dikurangi | `{ product_id, size, qty, stock_before, stock_after }` |
| Stock restore | `stock.restore` | Stok dikembalikan | `{ product_id, size, qty, stock_before, stock_after, reason }` |
| Webhook received | `webhook.received` | Notifikasi Midtrans | `{ transaction_id, status_code, transaction_status, signature_valid }` |
| Payment paid | `payment.paid` | Pembayaran sukses | `{ payment_id, transaction_id, total_harga }` |
| Payment failed | `payment.failed` | Pembayaran gagal | `{ payment_id, transaction_id, reason }` |
| Payment expired | `payment.expired` | Pembayaran expired | `{ payment_id, transaction_id }` |
| Order cancelled | `order.cancelled` | Order dibatalkan | `{ order_id, reason }` |
| Stock reject | `stock.rejected` | Checkout ditolak (stok habis) | `{ product_id, size, requested_qty, available }` |
| Draft expired | `draft.expired` | Draft order kadaluarsa | `{ order_id }` |

## 4. Admin UI

### Backend

```php
// app/Http/Controllers/Admin/ActivityLogController.php

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('action', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $logs = $query->latest('created_at')->paginate(20);

        return Inertia::render('admin/Logs', [
            'logs' => $logs,
            'filters' => $request->only(['type', 'date_from', 'date_to', 'search']),
        ]);
    }
}
```

### Route

```php
// di routes/web.php, admin group
Route::get('/admin/logs', [ActivityLogController::class, 'index'])->name('admin.logs');
```

### Frontend

`resources/js/Pages/admin/Logs.jsx`:

- Tab filter: Semua | User | Admin | Transaksi
- Form filter: date range, search (action/description/email)
- Tabel: waktu, badge type (warna: user=blue, admin=purple, transaction=green), actor, action, subject (model name + id), description, expandable JSON context
- Pagination komponen `Pagination`

## 5. Retention Cleanup

```php
// app/Console/Commands/LogsCleanup.php

class LogsCleanup extends Command
{
    protected $signature = 'logs:cleanup {--months=12}';

    public function handle()
    {
        $cutoff = now()->subMonths($this->option('months'));
        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();
        $this->info("Deleted {$deleted} logs older than {$this->option('months')} months.");
    }
}
```

Di `routes/console.php`:
```php
use App\Console\Commands\LogsCleanup;
Schedule::command('logs:cleanup')->daily();
```

## 6. Security

| Rule | Detail |
|---|---|
| Append-only | Tidak ada route/endpoint untuk update/delete log |
| Admin-only view | `/admin/logs` pakai middleware `auth` + `role:admin` + `LogsPolicy` |
| Rate limiting | `throttle:30,1` di `/admin/logs` |
| Sanitize | Password, token, snap_token, server key, card number → `***REDACTED***` |
| Retention | Hapus > 12 bulan via scheduler |

## 7. Performance

| Aspek | Detail |
|---|---|
| Write | Sync di MVP (1 INSERT per event, kecil) |
| Upgrade path | Kalau volume naik → dispatch ke queue (`dispatch(new LogActivityJob(...))`) |
| Index | type, user_id, subject_type+subject_id, created_at |

## 8. Tests

| Test | Expected |
|---|---|
| Login gagal | Log `login.failed` tercatat + email |
| Product update | Log `product.update` + before/after |
| Checkout draft | Log `checkout.create` + items detail |
| Webhook paid | Log `webhook.received` + `payment.paid` |
| Stock reject | Log `stock.rejected` + detail |
| Non-admin akses `/admin/logs` | 403 |
| Password/token tidak di-log | Context tidak berisi raw password/token |
| User delete | Log `user.delete` tetap ada walau user dihapus |

## 9. Integration Points (referensi ke dokumen lain)

| Dokumen | Bagian | Koneksi |
|---|---|---|
| D1 | T6 Services | Setiap service panggil `AuditLogService::record*` |
| D1 | T7 Controllers | Controller panggil `AuditLogService` untuk user/admin events |
| D1 | T9 ImageService | Log image add/remove di product update |
| D3 | CheckoutService | Log checkout.create, stock.decrement, stock.rejected |
| D3 | PaymentWebhookService | Log webhook.received, payment.paid/failed/expired, stock.restore, order.cancelled |
| D3 | InventoryManager | Log stock.decrement, stock.restore |
| D2 | Admin Logs page | `Pages/admin/Logs.jsx` |

## 10. Checklist Done

- [x] Migration `activity_logs` + index + model `ActivityLog`
- [x] `AuditLogService` + sanitizer (bukan `DB::afterCommit` — lihat koreksi di §1)
- [x] Hooks di semua event user (register, login, logout, profile, password, account.delete)
- [x] Hooks di semua event admin (admin.login, product CRUD, user.delete)
- [x] Hooks di semua event transaksi (checkout.create, stock.decrement/restore/rejected, webhook.received, payment.initiated/paid/failed/expired, order.cancelled, draft.expired)
- [x] `/admin/logs` + filter (type/date/search) + pagination
- [x] `Pages/admin/Logs.jsx` (tab + table + filter + expandable context)
- [x] `logs:cleanup` + scheduler (`Schedule::command('logs:cleanup')->daily()`)
- [x] Test hijau — 8 test baru (`tests/Feature/ActivityLogTest.php`), full suite 57/57 passed

**Tidak dieksekusi (di luar peta event D4, tidak ada di controller manapun saat ini):** `user.update` (tidak ada admin action untuk update user selain delete).
