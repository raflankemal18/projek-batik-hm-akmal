<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture deterministik untuk E2E Playwright (Fase C).
 *
 * Berbeda dengan DatabaseSeeder (data demo acak untuk pengembangan), seeder ini
 * sengaja tanpa random: stok, harga, dan status pesanan harus bisa diprediksi
 * spec E2E.
 *
 * Dipakai oleh: tests/e2e/global-setup.ts & tests/e2e/support/db.ts
 *   php artisan migrate:fresh --seed --seeder=E2ESeeder --force
 *
 * Tiga user terpisah supaya alur satu skenario tidak mengotori data skenario lain:
 *   user@e2e.test    → keranjang berisi 5 pcs dari size yang stoknya 1 (skenario 3)
 *   shopper@e2e.test → keranjang kosong, untuk alur belanja normal (skenario 2)
 *   bayar@e2e.test   → punya tagihan pending siap dibayar (skenario 4)
 */
class E2ESeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@e2e.test';
    public const USER_EMAIL = 'user@e2e.test';
    public const SHOPPER_EMAIL = 'shopper@e2e.test';
    public const PAYER_EMAIL = 'bayar@e2e.test';
    public const PASSWORD = 'password';

    /** Produk berstok 1 → pemicu kontrak error stok habis. */
    public const LOW_STOCK_PRODUCT = 'Batik E2E Pria Stok Satu';
    public const LOW_STOCK_SIZE = 'S';

    public const WANITA_PRODUCT = 'Batik E2E Women';
    public const SEARCH_KEYWORD = 'Motifwali';

    /** Produk bermotif untuk skenario E2E pemilihan motif. */
    public const VARIANT_PRODUCT = 'Batik E2E Motif Parang';
    public const VARIANT_MOTIF = 'Parang';
    public const VARIANT_SOLD_OUT_MOTIF = 'Kawung';

    public const PENDING_TRANSACTION_ID = 'E2E-PENDING-0001';
    public const PAID_TRANSACTION_ID = 'E2E-PAID-0001';

    public function run(): void
    {
        $admin = $this->makeUser('Admin E2E', self::ADMIN_EMAIL, 'admin');
        $user = $this->makeUser('User E2E', self::USER_EMAIL, 'user');
        $shopper = $this->makeUser('Shopper E2E', self::SHOPPER_EMAIL, 'user');
        $payer = $this->makeUser('Payer E2E', self::PAYER_EMAIL, 'user');

        $wanita = $this->product(self::WANITA_PRODUCT, 'wanita', 275000, 'Kemeja batik wanita untuk alur belanja normal.');
        $wanitaSize = ProductSize::create([
            'product_id' => $wanita->id,
            'size' => 'M',
            'stock' => 10,
        ]);

        $motif = $this->product('Kain E2E Motifwali Parang', 'pria', 320000, 'Kain dengan motif parang khas Jawa Timur.');
        ProductSize::create(['product_id' => $motif->id, 'size' => 'L', 'stock' => 7]);

        // Produk bermotif: satu motif berstok, satu motif habis (untuk menguji
        // tombol tambah yang nonaktif).
        $batikMotif = $this->product(self::VARIANT_PRODUCT, 'wanita', 150000, 'Produk dengan pilihan motif kain.');
        $batikMotif->update(['has_variants' => true, 'fabric_size' => '2 m x 1,1 m']);
        ProductVariant::create([
            'product_id' => $batikMotif->id,
            'name' => self::VARIANT_MOTIF,
            'price' => 150000,
            'stock' => 6,
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $batikMotif->id,
            'name' => self::VARIANT_SOLD_OUT_MOTIF,
            'price' => 175000,
            'stock' => 0,
            'is_active' => true,
        ]);

        $lowStock = $this->product(self::LOW_STOCK_PRODUCT, 'pria', 150000, 'Produk stok satu untuk uji kontrak error checkout.');
        $lowStockSize = ProductSize::create([
            'product_id' => $lowStock->id,
            'size' => self::LOW_STOCK_SIZE,
            'stock' => 1,
        ]);

        // --- Skenario 3: keranjang melebihi stok ---
        // Dibuat langsung di DB karena CartService menolak qty > stok saat item
        // ditambahkan lewat UI, jadi kondisi "keranjang berisi 5, stok 1" tidak
        // bisa direproduksi dengan klik di browser. Yang diuji di sini adalah
        // kontrak error checkout, bukan validasi keranjang.
        $cart = Cart::create(['user_id' => $user->id]);
        CartDetail::create([
            'cart_id' => $cart->id,
            'product_id' => $lowStock->id,
            'quantity' => 5,
            'size' => self::LOW_STOCK_SIZE,
        ]);

        $this->paidOrderFor($user, $wanita, $wanitaSize, self::PAID_TRANSACTION_ID);

        $this->pendingOrderFor($payer, $wanita, $wanitaSize, self::PENDING_TRANSACTION_ID);

        unset($admin, $shopper);
    }

    /** Order lunas (dipakai skenario 5: admin ubah status kirim → user melihat). */
    private function paidOrderFor(User $user, Product $product, ProductSize $size, string $transactionId): void
    {
        $order = $this->order($user, $product, $size);
        $order->update(['status' => 'paid']);

        Payment::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
            'total_harga' => 275000,
            'status' => 'success',
            'snap_token' => 'e2e-token-lunas',
            'paid_at' => now(),
        ]);
    }

    /**
     * Order dengan tagihan pending + snap token (dipakai skenario 4: klik "Bayar
     * Sekarang", lalu webhook Midtrans yang disimulasikan mengubahnya jadi lunas).
     */
    private function pendingOrderFor(User $user, Product $product, ProductSize $size, string $transactionId): void
    {
        $order = $this->order($user, $product, $size);
        $order->update(['status' => 'pending_payment']);

        $payment = Payment::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
            'total_harga' => 275000,
            'status' => 'pending',
            'snap_token' => 'e2e-token-menunggu',
        ]);

        PaymentDetail::create([
            'payment_id' => $payment->id,
            'product_id' => $product->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '081234567890',
            'alamat' => 'Jl. E2E No. 2, Jakarta',
            'size' => $size->size,
            'quantity' => 1,
            'harga' => 275000,
        ]);
    }

    private function order(User $user, Product $product, ProductSize $size): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'total_harga' => 275000,
            'status' => 'draft',
            'expires_at' => now()->addMinutes(10),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '081234567890',
            'alamat' => 'Jl. E2E No. 1, Jakarta',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_size_id' => $size->id,
            'size' => $size->size,
            'quantity' => 1,
            'harga' => 275000,
        ]);

        return $order;
    }

    /**
     * `role` sengaja tidak ada di User::$fillable (kebijakan Fase A.1: jangan
     * buka mass-assignment untuk kolom peran), jadi nilainya dipasang lewat
     * property assignment lalu save — pola yang sama dipakai
     * RegisteredUserController. Kalau_mass assignment_dipakai di sini, hasilnya
     * bergantung pada apakah seeder dipanggil lewat `db:seed` (yang aktif dalam
     * keadaan unguarded) atau dipanggil langsung.
     */
    private function makeUser(string $name, string $email, string $role): User
    {
        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make(self::PASSWORD);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function product(string $name, string $category, int $harga, string $description): Product
    {
        return Product::create([
            'name' => $name,
            'category' => $category,
            'harga' => $harga,
            'description' => $description,
        ]);
    }
}