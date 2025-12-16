<?php

namespace Database\Seeders;

use App\Models\product_image;
use App\Models\Products;
use App\Models\ProductsSize;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. BUAT AKUN ADMIN & USER
        User::create([
            'name' => 'Admin Ganteng',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'), // Password: password
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Pelanggan Setia',
            'email' => 'user@gmail.com',
            'password' => Hash::make('password'), // Password: password
            'role' => 'user',
        ]);

        // 2. DATA DUMMY PRODUK
        $products = [
            [
                'name' => 'Kemeja Flanel Kotak',
                'harga' => '150000',
                'description' => 'Kemeja flanel bahan adem, cocok untuk kuliah atau kerja santai. Motif kotak-kotak klasik yang tidak lekang oleh waktu.',
                'image' => 'public/product_images/flanel.jpg'
            ],
            [
                'name' => 'Kaos Polos Hitam',
                'harga' => '75000',
                'description' => 'Kaos polos cotton combed 30s. Menyerap keringat dan sangat nyaman dipakai sehari-hari.',
                'image' => 'public/product_images/kaos_hitam.jpg'
            ],
            [
                'name' => 'Jaket Denim Vintage',
                'harga' => '250000',
                'description' => 'Jaket jeans denim dengan gaya vintage washing. Membuat penampilanmu semakin maskulin dan trendy.',
                'image' => 'public/product_images/jaket_denim.jpg'
            ],
            [
                'name' => 'Batik Lengan Panjang',
                'harga' => '180000',
                'description' => 'Kemeja batik print dengan motif modern. Cocok untuk acara formal maupun kondangan.',
                'image' => 'product_images/batik.jpg'
            ],
            [
                'name' => 'Hoodie Oversize',
                'harga' => '200000',
                'description' => 'Hoodie potongan oversize ala street style Korea. Bahan fleece tebal dan hangat.',
                'image' => 'public/product_images/hoodie.jpg'
            ],
            [
                'name' => 'Celana Chino Cream',
                'harga' => '120000',
                'description' => 'Celana panjang chino warna cream. Bahan stretch melar, nyaman untuk bergerak.',
                'image' => 'public/product_images/chino.jpg'
            ],
        ];

        // 3. LOOPING UNTUK INSERT PRODUK, UKURAN, DAN GAMBAR
        foreach ($products as $item) {
            // Simpan Produk
            $product = Products::create([
                'name' => $item['name'],
                'harga' => $item['harga'],
                'description' => $item['description'],
            ]);

            // Simpan Ukuran & Stok (Random)
            $sizes = ['S', 'M', 'L', 'XL'];
            foreach ($sizes as $size) {
                ProductsSize::create([
                    'product_id' => $product->id,
                    'size' => $size,
                    'stock' => rand(5, 50), // Stok acak antara 5 sampai 50
                ]);
            }

            // Simpan Placeholder Gambar
            // Catatan: Karena kita tidak benar-benar upload file, gambar ini akan broken (blank) di frontend
            // kecuali kamu menaruh file asli bernama 'flanel.jpg' dll di folder storage.
            product_image::create([
                'product_id' => $product->id,
                'image_path' => $item['image'],
            ]);
        }
    }
}