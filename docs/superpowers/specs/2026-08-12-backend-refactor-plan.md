# Backend Refactor Plan

**Kode Dokumen:** D1
**Tanggal:** 2026-08-12
**Status:** Approved
**Dependency:** Tidak ada (dieksekusi paling pertama). Lihat [00-index.md](./00-index.md) untuk urutan eksekusi lengkap.

## 1. Arsitektur (Layering)

```
Controllers (tipis) -> FormRequest (validasi)
   -> Services (business logic / transaksi)
   -> Repository Interface + EloquentRepository
   -> Models / Policies (authorization)
```

### Layer baru

| Layer | Lokasi | Contoh |
|---|---|---|
| Contract | `app/Contracts/Repositories/*RepositoryInterface.php` | Product, Cart, Order, Payment, User |
| Implementasi | `app/Repositories/Eloquent/*Repository.php` | wrapper query Eloquent |
| Base | `app/Repositories/Eloquent/BaseRepository.php` | CRUD generik + OwnedScopes |
| Service | `app/Services/*Service.php` | CartService, CheckoutService, ProductService, ImageService, OrderNumberService |
| Policy | `app/Policies/*Policy.php` | Product, Payment, Cart, User |
| FormRequest | `app/Http/Requests/Admin/Product/` | AddProductRequest, UpdateProductRequest |
| Exception | `app/Exceptions/*.php` | InsufficientStockException, EmptyCartException (lihat §2b) |

PaymentService terpecah jadi MidtransService (konfigurasi + Snap token) + PaymentWebhookService (verifikasi notifikasi).

## 2. Migration & Data

Ubah tipe data, tambah kolom, tambah index + constraints.

| Tabel | Kolom | Perubahan |
|---|---|---|
| products | `harga` | `string` -> `decimal(12,2)` |
| orders | `total_harga` | `string` -> `decimal(12,2)` |
| order_details | `harga` | `string` -> `decimal(12,2)` |
| payments | `total_harga` | `string` -> `decimal(12,2)` |
| products_sizes | `stock` | `integer` -> `unsignedInteger` + stock >= 0 |
| orders | +`status` | `enum('draft','pending_payment','paid','cancelled')` default `draft` |
| orders | +`expires_at` | `nullableTimestamp` |
| payments | `id` | auto-increment, bukan manual random_int |
| payments | +`transaction_id` | `string unique` (ID unik Midtrans) |
| payments | +`paid_at` | `nullableTimestamp` |
| order_details | +`product_size_id` | `foreignId` -> `products_sizes.id`. **Wajib** — tanpa ini, `InventoryManager::restore()` di D3 (dipanggil dari webhook & `ReleaseExpiredOrders`) tidak punya cara akurat menentukan baris stock mana yang harus dikembalikan (kolom `size` yang ada sekarang cuma string label seperti "M"/"L", bukan ID, dan bisa ambigu kalau nama size berubah). Diisi saat `CheckoutService::createDraftOrder` (D3 §7) membuat `OrderDetail`. |

### Index baru

```
products_sizes(product_id)
product_images(product_id)
payments(user_id, status)
payments(transaction_id)        -- unique
payment_details(payment_id, product_id)
orders(user_id, status)
users(role)
```

### Rename kelas PSR-4

Nama file model saat ini (cek dulu sebelum rename, jangan asumsikan dari nama tabel):

| File saat ini (`app/Models/`) | Class saat ini | Jadi |
|---|---|---|
| `Products.php` | `Products` | `Product` (rename file + class + tabel tetap `products`) |
| `product_image.php` | `product_image` | `ProductImage` |
| `ProductsSize.php` | `ProductsSize` | `ProductSize` |
| `Carts.php` | `Carts` | `Cart` |
| `CartDetail.php` | `CartDetail` | *(sudah benar, tidak perlu diubah)* |

`Products` model paling sering dirujuk sepanjang dokumen ini (`ProductRepositoryInterface`, `ProductPolicy`, dst mengasumsikan class sudah bernama `Product`) — rename ini wajib dieksekusi lebih dulu sebelum langkah lain di dokumen ini dimulai.

Update semua reference (bukan cuma `use` statement) di: **Models** (relasi internal — `Products.php` sendiri punya `hasMany(ProductsSize::class, ...)` dan `hasMany(product_image::class, ...)` yang harus ikut diubah), Controllers, Repos, Services, Seeders, Factories, dan file test.

### 2b. Custom Exceptions

Dipakai di D3 (checkout-midtrans-business-plan) — didefinisikan di sini karena D3 dieksekusi setelah D1.

```php
// app/Exceptions/InsufficientStockException.php
namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected $code = 422;
}
```

```php
// app/Exceptions/EmptyCartException.php
namespace App\Exceptions;

use Exception;

class EmptyCartException extends Exception
{
    protected $code = 422;
}
```

Handler (`bootstrap/app.php` atau `app/Exceptions/Handler.php` tergantung versi Laravel 11 yang dipakai — cek struktur project dulu):

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (InsufficientStockException|EmptyCartException $e, Request $request) {
        return back()->withErrors(['stock' => $e->getMessage()]);
    });
})
```

## 3. Strict Query Guard

Di AppServiceProvider::boot:

```php
Model::preventLazyLoading(!app()->isProduction());
Model::shouldBeStrict(!app()->isProduction());
```

Setiap relasi tanpa eager load langsung throw MissingAttributeException di dev/staging.

## 4. Security: Query-level Guard

### 4a. OwnedScopes Trait

```php
trait OwnedScopes
{
    public function findOwned(int $id, $user)
    {
        return $this->model->where('id', $id)->where('user_id', $user->id)->firstOrFail();
    }
}
```

### 4b. Policy Register

| Policy | Rules |
|---|---|
| ProductPolicy | viewAny -> admin; view -> public; create/update/delete -> admin only |
| PaymentPolicy | view/viewAny -> owner + admin; create -> authenticated; update -> admin/webhook |
| CartPolicy | update/delete -> owner only |
| UserPolicy | viewAny -> admin; update -> self + admin; delete -> admin, tidak boleh delete admin |

### 4c. Controller Gates

Setiap method controller wajib panggil `$this->authorize('update', $payment)`. Dilarang Model::find($id) langsung tanpa repository + ownership scope.

### 4d. Rate Limiting

| Route group | Throttle |
|---|---|
| `/cart/*` | `throttle:30,1` |
| `/checkout` | `throttle:10,1` |
| `/payment` | `throttle:10,1` |
| `/midtrans/webhook` | `throttle:60,1` |
| Login | `throttle:5,1` (sudah ada di Breeze) |

## 5. Contract + Repository

### Interfaces

```php
interface ProductRepositoryInterface
{
    public function getAllPaginated(int $perPage = 12);
    public function findWithRelations(int $id, array $relations = []);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id);
    public function getTopSelling(int $limit = 5);
}
```

### Binding di AppServiceProvider

```php
$this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
$this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
$this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
$this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
```

## 6. FormRequest

| Request | Lokasi | Keterangan |
|---|---|---|
| AddProductRequest | `app/Http/Requests/Admin/Product/` | name, harga numeric, sizes, images mimes+max+dimensions |
| UpdateProductRequest | `app/Http/Requests/Admin/Product/` | + existing product id |
| CartAddRequest | `app/Http/Requests/Cart/` | product_id exists, size, quantity min:1 |
| CartQuantityRequest | `app/Http/Requests/Cart/` | cart_detail_id exists, quantity min:1 |
| CheckoutRequest | `app/Http/Requests/` | **Sudah ada — MODIFIKASI, jangan buat baru.** Rule saat ini masih `totalHarga => required\|numeric` dan `orders => required\|array` (percaya harga dari client). Hapus kedua rule itu; sisakan cuma `name, email, phone, alamat`. Ini prasyarat wajib untuk tujuan keamanan D3 ("harga 100% dari DB") — kalau rule lama dibiarkan, D3 tidak akan efektif walau CheckoutService-nya sudah benar. |

## 7. Services

### ProductService
- create(AddProductRequest), update(id, UpdateProductRequest), delete(id) + hapus gambar
- getAllPaginated(perPage), getTopSelling(limit) - SQL aggregate bukan collection groupBy

### CartService
- add(CartAddRequest, User), updateQuantity(CartQuantityRequest, User)
- remove(cartDetailId, User), getCartWithProducts(User)

### CheckoutService
- Dibuat di Dokumen 3 (checkout-midtrans-business-plan)

### ImageService
- storeUploaded(UploadedFile, dir), replace(newFile, oldPath), delete(path), getThumbnailPath(path)

### MidtransService
- configure() - baca config sekali, getSnapToken(params), verifyNotification(payload)

### PaymentWebhookService
- handle(MidtransNotificationRequest)
- verify signature SHA512(order_id+status_code+gross_amount+serverKey)
- idempotent: if already final, skip
- update payment status + order status + restore stock on fail

### OrderNumberService
- generate() - format: INV-ymd-His-random6

### InventoryManager

Class service penuh (bukan potongan logic) — **satu-satunya tempat** yang boleh mengubah `stock`. Semua kode lain (CheckoutService, PaymentWebhookService, ReleaseExpiredOrders di D3) wajib memanggil method static ini, dilarang menulis ulang query decrement/restore inline.

```php
// app/Services/InventoryManager.php
namespace App\Services;

use App\Models\ProductsSize as ProductSize; // rename sesuai §Rename kelas PSR-4
use App\Exceptions\InsufficientStockException;

class InventoryManager
{
    public static function decrement(int $productSizeId, int $qty): void
    {
        $affected = ProductSize::where('id', $productSizeId)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty);

        if ($affected === 0) {
            throw new InsufficientStockException(
                "Stok tidak cukup untuk product size #{$productSizeId}"
            );
        }
    }

    public static function restore(int $productSizeId, int $qty): void
    {
        ProductSize::where('id', $productSizeId)->increment('stock', $qty);
    }
}
```

Method ini **tidak** membuka `DB::transaction` sendiri (caller yang bertanggung jawab membungkus transaksi + `lockForUpdate` pada baris yang relevan sebelum memanggil `decrement`/`restore`), supaya bisa dipanggil berkali-kali dalam satu transaksi checkout tanpa nested transaction.

## 8. Controllers

### AdminController -> ProductController + AdminDashboardController
- ProductController: store, update, destroy - via ProductService + ImageService
- AdminDashboardController: index (SQL aggregate topSelling), users (paginate), orders (paginate)

### CartController
- add -> CartService::add
- updateQuantity -> CartService::updateQuantity
- remove -> CartService::remove
- checkout -> CheckoutService::createDraftOrder

**Hapus:** dd() di checkout (baris 106), Log::info($orders->toArray()) di HomeController:47.

### PaymentController
- index -> paginate payments own
- store (web, session) -> create payment, get snap token
- webhook -> PaymentWebhookService::handle (publik, verified signature)

**Hapus:** storeApi (auth api/jwt), updateStatus (client-driven).

### UserController
- index -> user dashboard
- destroy -> UserPolicy::delete

**Pindah:** datauser GET delete -> DELETE /admin/users/{user} dengan UserPolicy.

## 9. Routes (final)

```php
// Public
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [HomeController::class, 'products'])->name('products');
Route::get('/products/{id}', [HomeController::class, 'show'])->name('products.show');
Route::post('/midtrans/webhook', [PaymentController::class, 'webhook'])->middleware('throttle:60,1');

// Auth + admin
Route::middleware('auth', 'role:admin', 'throttle:30,1')->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/admin/users', [AdminDashboardController::class, 'users']);
    Route::get('/admin/orders', [AdminDashboardController::class, 'orders']);
    Route::get('/admin/products', [ProductController::class, 'index']);
    Route::post('/admin/products', [ProductController::class, 'store']);
    Route::put('/admin/products/{product}', [ProductController::class, 'update']);
    Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']);
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);
});

// Auth + user
Route::middleware('auth', 'role:user', 'throttle:30,1')->group(function () {
    Route::get('/user/dashboard', [UserController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::post('/cart/update', [CartController::class, 'updateQuantity']);
    Route::post('/cart/remove', [CartController::class, 'remove']);
    Route::get('/cart', [HomeController::class, 'keranjang']);
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/pembayaran', [PaymentController::class, 'index'])->name('pembayaran');
    Route::post('/payment', [PaymentController::class, 'store'])->middleware('throttle:10,1');
});

// Profile
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
});
```

## 10. Token / Credential

- Hapus api guard dari config/auth.php (jwt driver).
- Hapus tymon/jwt-auth dari composer.json.
- Semua request via cookie session httpOnly + CSRF.
- Sanctum dipertahankan untuk skenario mobile/3rd-party nanti (personal access token).

## 11. Query Optimization

| Query | Fix |
|---|---|
| HomeController products | paginate(12) + with(['sizes','images'=>fn q=>q->limit(1)]) + select kolom penting |
| AdminController topSelling | SQL aggregate: selectRaw sum(quantity) groupBy product_id + join products + limit 5 |
| PaymentController index | paginate(10) + orderBy('created_at','desc') |
| AdminController users/orders | paginate(10-20), select kolom render-only |

Cache ringan: Cache::remember('products.catalog', 300, ...) + cache forget di repo write.

## 12. Testing

- Feature: Repositories ownership, Policies (403 non-owner)
- Webhook: signature valid/invalid + idempotency
- IDOR: cart/payment tanpa ownership
- Mass-assignment: harga dari client diabaikan, total dari DB
- Race condition: double stock decrement, double checkout

Done = php artisan test hijau + tidak ada Model::find() langsung di controller + dd/Log::debug hilang.

## Status Eksekusi (2026-08-12)

✅ **SELESAI** — `php artisan test` 49/49 hijau, Pint bersih, tidak ada `Model::find()` langsung di controller, `dd()`/`Log::debug` hilang (satu-satunya `Log::warning` di `PaymentWebhookService` adalah log keamanan yang sah).
