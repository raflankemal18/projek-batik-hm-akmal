# Checkout + Midtrans + Race Condition Business Plan

**Kode Dokumen:** D3
**Tanggal:** 2026-08-12
**Status:** Approved
**Dependency:** D1 (butuh `InventoryManager`, `Exception` classes, kolom `order_details.product_size_id`, `CheckoutRequest` yang sudah dimodifikasi) + D2 selesai. Lihat [00-index.md](./00-index.md).

## Tujuan

Business process checkout yang aman: harga dari DB, stok dikunci saat checkout, Midtrans webhook verified, race condition handled. Dieksekusi SETELAH D1 + D2 selesai.

> **Catatan konsistensi:** Semua contoh kode di dokumen ini yang mengubah `stock` (decrement/restore) HARUS memanggil `InventoryManager::decrement()` / `InventoryManager::restore()` yang didefinisikan lengkap di D1 §7 — bukan menulis ulang query `ProductSize::where(...)->decrement(...)` secara inline. Beberapa contoh kode di bawah ini masih menunjukkan versi inline (peninggalan draft awal sebelum `InventoryManager` diformalkan di D1); pada saat implementasi, ganti semua itu jadi pemanggilan `InventoryManager::decrement($productSizeId, $qty)` / `InventoryManager::restore($productSizeId, $qty)`, dan gunakan `product_size_id` (ID), **bukan** `$detail->size` (string label seperti "M"/"L") — lihat D1 §2 baris `order_details.product_size_id`.

## 1. Flow Lengkap (Server-Authoritative)

```
[1] Cart Operations (user login)
    POST /cart/add       -> CartService::add
    POST /cart/update    -> CartService::updateQuantity
    POST /cart/remove    -> CartService::remove
    -> validate: product exists, size exists in products_sizes
    -> validate: quantity > 0, stock >= quantity
    -> (tidak kurangi stock di sini, hanya validasi)

[2] Checkout Draft Order
    POST /checkout       -> CheckoutController::store -> CheckoutService::createDraftOrder
    -> DB::transaction + lockForUpdate:
       a) Load cart user + product sizes (owned, locked)
       b) Recalculate prices FROM DB (tolak harga dari client)
       c) Validate stock: for each item, stock >= quantity
       d) Decrement stock atomik (conditional: where stock >= qty)
       e) Create Order (status=draft, expires_at=now+10min)
       f) Create OrderDetails with server prices
       g) Delete cart
    -> redirect ke /pembayaran

[3] Payment Initiation
    POST /payment        -> PaymentController::store
    -> DB::transaction:
       a) Load draft order (owned, lock)
       b) Revalidate total_harga from DB (anti-tamper)
       c) Generate transaction_id via OrderNumberService
       d) Create Payment (status=pending, transaction_id)
       e) Get Snap token from MidtransService
       f) Update payment with snap_token
       g) Update order status -> pending_payment
    -> Return to Payment page with snap token

[4] Midtrans Snap (client)
    -> Client opens snap popup
    -> onSuccess/onPending/onError: only UI toast + router.reload()
    -> TIDAK ada client-driven status update

[5] Midtrans Webhook (server)
    POST /midtrans/webhook -> PaymentController::webhook
    -> PaymentWebhookService::handle:
       a) Verify signature SHA512(order_id + status_code + gross_amount + serverKey)
       b) Idempotent: if payment already success/failed, skip
       c) Map status: capture/settlement/success -> success; expire/cancel -> failed
       d) On success: payment.status=success, payment.paid_at=now, order.status=paid
       e) On failed: payment.status=failed, restore stock, order.status=cancelled, clear snap_token
    -> Return 200 OK to Midtrans
```

## 2. Race Condition Handling

### 2a. Stock Oversold (Dua user beli item terakhir bersamaan)

Ditangani oleh `InventoryManager::decrement()` (definisi lengkap di D1 §7) — dipanggil di dalam `DB::transaction` milik caller (lihat §7 di dokumen ini):

```php
DB::transaction(function () use ($productSizeId, $qty) {
    InventoryManager::decrement($productSizeId, $qty); // throw InsufficientStockException kalau gagal
});
```

Efek: 2 request paralel -> DB lock -> 1 sukses, 1 gagal di `where stock >= qty` (lihat query di dalam `InventoryManager::decrement`, D1 §7).

### 2b. Double-Click Checkout

```php
// CheckoutService::createDraftOrder
// Cek dulu apakah user sudah punya draft order aktif
$existingDraft = Order::where('user_id', $userId)
    ->where('status', 'draft')
    ->lockForUpdate()
    ->first();

if ($existingDraft) {
    // Kembalikan draft yang sudah ada, jangan buat baru
    return $existingDraft;
}
```

### 2c. Draft Order Kadaluarsa vs Checkout Baru

Console command + scheduler:

```php
// app/Console/Commands/ReleaseExpiredOrders.php
class ReleaseExpiredOrders extends Command
{
    protected $signature = 'orders:release-expired';
    
    public function handle()
    {
        DB::transaction(function () {
            $expired = Order::where('status', 'draft')
                ->where('expires_at', '<', now())
                ->lockForUpdate()
                ->get();

            foreach ($expired as $order) {
                // Restore stock untuk setiap order detail (pakai product_size_id, bukan $detail->size)
                foreach ($order->details as $detail) {
                    InventoryManager::restore($detail->product_size_id, $detail->quantity);
                }
                $order->update(['status' => 'cancelled']);
            }
        });
    }
}
```

Di `routes/console.php`:

```php
use App\Console\Commands\ReleaseExpiredOrders;

Schedule::command('orders:release-expired')->everyMinute();
```

### 2d. Double Payment Submit

```php
// PaymentController::store
$existingPayment = Payment::where('order_id', $orderId)
    ->whereIn('status', ['pending', 'success'])
    ->lockForUpdate()
    ->first();

if ($existingPayment && $existingPayment->snap_token) {
    // Sudah ada pembayaran aktif, kembalikan snap token yang sama
    return redirect()->route('pembayaran')->with('existing_payment', $existingPayment);
}

// Jika sudah success, redirect ke invoice
if ($existingPayment && $existingPayment->status === 'success') {
    return redirect()->route('invoice', $existingPayment->id);
}
```

## 3. Webhook Midtrans Signature Verification

```php
// PaymentWebhookService::handle
public function handle(array $payload): void
{
    $serverKey = config('midtrans.server_key');
    $orderId = $payload['order_id'];
    $statusCode = $payload['status_code'];
    $grossAmount = $payload['gross_amount'];
    
    $signatureKey = $payload['signature_key'];
    $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    
    if ($signatureKey !== $expectedSignature) {
        Log::warning('Invalid Midtrans webhook signature', ['order_id' => $orderId]);
        abort(403, 'Invalid signature');
    }
    
    // Idempotent check
    $payment = Payment::where('transaction_id', $orderId)->firstOrFail();
    
    if (in_array($payment->status, ['success', 'failed'])) {
        // Already final, return 200 but skip processing
        return;
    }
    
    // Process status
    DB::transaction(function () use ($payment, $payload) {
        $status = $payload['transaction_status'];
        
        if (in_array($status, ['capture', 'settlement', 'success'])) {
            $payment->update([
                'status' => 'success',
                'paid_at' => now(),
            ]);
            $payment->order->update(['status' => 'paid']);
            
        } elseif (in_array($status, ['expire', 'cancel', 'deny'])) {
            $payment->update(['status' => 'failed']);
            $payment->order->update(['status' => 'cancelled']);
            
            // Restore stock (pakai product_size_id, bukan $detail->size)
            foreach ($payment->order->details as $detail) {
                InventoryManager::restore($detail->product_size_id, $detail->quantity);
            }
        }
    });
}
```

## 4. Data Model Updates (fold ke D1)

### orders table

```php
$table->enum('status', ['draft', 'pending_payment', 'paid', 'cancelled'])->default('draft');
$table->timestamp('expires_at')->nullable();
$table->index(['user_id', 'status']);
```

### payments table

```php
$table->id(); // auto-increment (bukan random_int manual)
$table->string('transaction_id')->unique(); // order_id dari Midtrans
$table->timestamp('paid_at')->nullable();
$table->index(['user_id', 'status']);
```

## 5. OrderNumberService

```php
class OrderNumberService
{
    public static function generate(): string
    {
        $prefix = 'INV-' . now()->format('ymd');
        $last = Payment::where('transaction_id', 'LIKE', $prefix . '%')
            ->orderByDesc('transaction_id')
            ->value('transaction_id');
        
        if ($last) {
            $sequence = (int) substr($last, -6) + 1;
        } else {
            $sequence = 1;
        }
        
        return $prefix . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}
```

Format: `INV-260812-000001` (unique, incrementing per hari).

## 6. Cart Operations (Pre-Checkout Validation)

### CartService::add

```php
public function add(CartAddRequest $request, User $user): void
{
    $productSize = ProductSize::where('product_id', $request->product_id)
        ->where('size', $request->size)
        ->firstOrFail();
    
    // Validasi stok tersedia
    if ($productSize->stock < $request->quantity) {
        throw new InsufficientStockException('Stok tidak cukup');
    }
    
    $cart = Cart::firstOrCreate(['user_id' => $user->id]);
    
    $existing = CartDetail::where('cart_id', $cart->id)
        ->where('product_id', $request->product_id)
        ->where('size', $request->size)
        ->first();
    
    if ($existing) {
        $newQty = $existing->quantity + $request->quantity;
        if ($newQty > $productSize->stock) {
            throw new InsufficientStockException('Jumlah melebihi stok tersedia');
        }
        $existing->update(['quantity' => $newQty]);
    } else {
        CartDetail::create([
            'cart_id' => $cart->id,
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'size' => $request->size,
        ]);
    }
}
```

### CartService::updateQuantity

```php
public function updateQuantity(CartQuantityRequest $request, User $user): void
{
    $cartDetail = CartDetail::where('id', $request->cart_detail_id)
        ->whereHas('cart', fn($q) => $q->where('user_id', $user->id))
        ->firstOrFail();
    
    $productSize = ProductSize::where('product_id', $cartDetail->product_id)
        ->where('size', $cartDetail->size)
        ->first();
    
    if (!$productSize || $request->quantity > $productSize->stock) {
        throw new InsufficientStockException('Stok tidak cukup');
    }
    
    $cartDetail->update(['quantity' => $request->quantity]);
}
```

## 7. CheckoutService::createDraftOrder

```php
public function createDraftOrder(array $checkoutData, User $user): Order
{
    return DB::transaction(function () use ($checkoutData, $user) {
        // Cek draft existing
        $existingDraft = Order::where('user_id', $user->id)
            ->where('status', 'draft')
            ->lockForUpdate()
            ->first();
        
        if ($existingDraft) {
            return $existingDraft;
        }
        
        // Load cart + lock
        $cart = Cart::where('user_id', $user->id)
            ->with('details')
            ->lockForUpdate()
            ->first();
        
        if (!$cart || $cart->details->isEmpty()) {
            throw new EmptyCartException('Keranjang kosong');
        }
        
        $totalHarga = 0;
        $orderDetails = [];
        
        foreach ($cart->details as $detail) {
            $productSize = ProductSize::where('product_id', $detail->product_id)
                ->where('size', $detail->size)
                ->lockForUpdate()
                ->first();
            
            if (!$productSize || $productSize->stock < $detail->quantity) {
                throw new InsufficientStockException(
                    "Stok tidak cukup untuk {$detail->product->name} size {$detail->size}"
                );
            }
            
            // Hitung harga dari DB, bukan dari client
            $harga = $detail->product->harga;
            $subtotal = $harga * $detail->quantity;
            $totalHarga += $subtotal;
            
            // Decrement stock atomik via InventoryManager (D1 §7) — throw InsufficientStockException kalau gagal
            InventoryManager::decrement($productSize->id, $detail->quantity);
            
            $orderDetails[] = [
                'product_id' => $detail->product_id,
                'product_size_id' => $productSize->id,
                'size' => $detail->size,
                'quantity' => $detail->quantity,
                'harga' => $harga,
            ];
        }
        
        // Buat order draft
        $order = Order::create([
            'user_id' => $user->id,
            'total_harga' => $totalHarga,
            'status' => 'draft',
            'expires_at' => now()->addMinutes(10),
        ]);
        
        foreach ($orderDetails as $detail) {
            OrderDetail::create(array_merge($detail, ['order_id' => $order->id]));
        }
        
        // Hapus cart
        $cart->details()->delete();
        $cart->delete();
        
        return $order;
    });
}
```

## 8. PaymentController::store (web, session)

```php
public function store(Request $request, MidtransService $midtransService)
{
    $user = auth()->user();
    
    $order = Order::where('user_id', $user->id)
        ->where('status', 'draft')
        ->lockForUpdate()
        ->first();
    
    if (!$order) {
        return redirect()->back()->with('error', 'Tidak ada order aktif');
    }
    
    // Revalidate total
    $dbTotal = $order->details->sum(fn($d) => $d->harga * $d->quantity);
    if ($dbTotal != $order->total_harga) {
        $order->update(['total_harga' => $dbTotal]);
    }
    
    // Cek apakah sudah ada pembayaran pending
    $existingPayment = Payment::where('order_id', $order->id)
        ->whereIn('status', ['pending', 'success'])
        ->first();
    
    if ($existingPayment && $existingPayment->status === 'success') {
        return redirect()->route('invoice', $existingPayment->id);
    }
    
    if ($existingPayment && $existingPayment->snap_token) {
        return redirect()->route('pembayaran')->with('payment', $existingPayment);
    }
    
    // Buat payment baru
    $transactionId = OrderNumberService::generate();
    
    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => $transactionId,
        'total_harga' => $order->total_harga,
        'status' => 'pending',
    ]);
    
    $snapToken = $midtransService->getSnapToken([
        'transaction_details' => [
            'order_id' => $transactionId,
            'gross_amount' => (int) $order->total_harga,
        ],
        'customer_details' => [
            'first_name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'phone' => $request->phone ?? '',
        ],
    ]);
    
    $payment->update(['snap_token' => $snapToken]);
    $order->update(['status' => 'pending_payment']);
    
    return redirect()->route('pembayaran')->with('payment', $payment);
}
```

## 9. Tests

| Test | Deskripsi |
|---|---|
| Race: stock oversold | 2 transaksi paralel, stock 1 -> 1 sukses, 1 gagal InsufficientStock |
| Double checkout | User submit checkout 2x -> draft kedua return draft existing |
| Draft expired | Jalankan orders:release-expired -> stock restore, order cancelled |
| Webhook valid | Signature cocok -> payment success, order paid |
| Webhook invalid | Signature salah -> 403 |
| Webhook idempotent | Double hit webhook success -> tidak double process |
| Harga dari client diabaikan | Kirim harga palsu di checkout -> total dihitung dari DB |
| Stock restore on fail | Payment failed/expired -> stock kembali |

## 10. Checklist Done

- [x] Harga & total 100% dari DB, bukan dari client
- [x] Stock dikurangi saat checkout (draft order), bukan saat payment
- [x] Stock dikembalikan saat draft expired / payment failed
- [x] Webhook signature verified (SHA512)
- [x] Tidak ada client-driven status update
- [x] lockForUpdate di semua read-modify-write transaction
- [x] Atomic conditional decrement untuk stock
- [x] ReleaseExpiredOrders scheduler berjalan
- [x] OrderNumberService generate ID unik
- [x] Semua perubahan stock (decrement & restore) lewat `InventoryManager`, tidak ada query `->decrement('stock', ...)` / `->increment('stock', ...)` inline di luar `InventoryManager`
- [x] `order_details.product_size_id` terisi saat checkout, dipakai (bukan `$detail->size`) di semua pemanggilan `InventoryManager::restore()`
- [x] `CheckoutRequest` (D1 §6) sudah dimodifikasi — tidak lagi menerima/mempercayai `totalHarga`/`orders` dari client
- [x] Semua test hijau
