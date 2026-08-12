<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSize;
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
        // 1. BUAT AKUN ADMIN & USER (idempotent, aman dijalankan ulang)
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin Ganteng',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $admin->update([
            'name' => 'Admin Ganteng',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $user = User::firstOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'name' => 'Pelanggan Setia',
                'password' => Hash::make('password'),
                'role' => 'user',
            ]
        );

        $user->update([
            'name' => 'Pelanggan Setia',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        // 2. DATA DUMMY PRODUK
        $products = [
            [
                'name' => 'Kemeja Flanel Kotak',
                'harga' => '150000',
                'category' => 'pria',
                'description' => 'Kemeja flanel bahan adem, cocok untuk kuliah atau kerja santai. Motif kotak-kotak klasik yang tidak lekang oleh waktu.',
            ],
            [
                'name' => 'Kaos Polos Hitam',
                'harga' => '75000',
                'category' => 'pria',
                'description' => 'Kaos polos cotton combed 30s. Menyerap keringat dan sangat nyaman dipakai sehari-hari.',
            ],
            [
                'name' => 'Jaket Denim Vintage',
                'harga' => '250000',
                'category' => 'pria',
                'description' => 'Jaket jeans denim dengan gaya vintage washing. Membuat penampilanmu semakin maskulin dan trendy.',
            ],
            [
                'name' => 'Batik Lengan Panjang',
                'harga' => '180000',
                'category' => 'pria',
                'description' => 'Kemeja batik print dengan motif modern. Cocok untuk acara formal maupun kondangan.',
            ],
            [
                'name' => 'Hoodie Oversize',
                'harga' => '200000',
                'category' => 'wanita',
                'description' => 'Hoodie potongan oversize ala street style Korea. Bahan fleece tebal dan hangat.',
            ],
            [
                'name' => 'Celana Chino Cream',
                'harga' => '120000',
                'category' => 'wanita',
                'description' => 'Celana panjang chino warna cream. Bahan stretch melar, nyaman untuk bergerak.',
            ],
        ];

        // 3. LOOPING UNTUK INSERT/UPDATE PRODUK, UKURAN, GAMBAR (idempotent)
        foreach ($products as $item) {
            // Simpan / Perbarui Produk
            $product = Product::updateOrCreate(
                ['name' => $item['name']],
                [
                    'category' => $item['category'],
                    'harga' => $item['harga'],
                    'description' => $item['description'],
                ]
            );

            // Reset varian & gambar supaya tidak menumpuk saat dijalankan ulang
            $product->sizes()->delete();
            $product->images()->delete();

            // Simpan Ukuran & Stok (Random)
            $sizes = ['S', 'M', 'L', 'XL'];
            foreach ($sizes as $size) {
                ProductSize::create([
                    'product_id' => $product->id,
                    'size' => $size,
                    'stock' => rand(5, 50), // Stok acak antara 5 sampai 50
                ]);
            }

            // Generate placeholder gambar (jika belum ada) supaya tampilan tidak broken
            $imagePath = $this->makePlaceholderImage($product);
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
            ]);
        }
    }

    /**
     * Buat placeholder image (600x750) berwarna khas per produk,
     * simpan di storage/app/public/product_images.
     *
     * @return string path relatif (public/product_images/xxx.jpg)
     */
    private function makePlaceholderImage(Product $product): string
    {
        $dir = storage_path('app/public/product_images');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = preg_replace('/[^a-z0-9]+/', '_', strtolower($product->name)) . '.png';
        $file = $dir . '/' . $filename;

        if (! file_exists($file)) {
            $w = 600;
            $h = 750;

            $img = imagecreatetruecolor($w, $h);

            // Warna background diturunkan dari id produk (variatif tapi konsisten)
            $hue = ($product->id * 47) % 360;
            list($r, $g, $b) = $this->hslToRgb($hue / 360, 0.35, 0.30);
            $bg = imagecolorallocate($img, $r, $g, $b);
            imagefilledrectangle($img, 0, 0, $w, $h, $bg);

            // Border emas
            $gold = imagecolorallocate($img, 212, 175, 55);
            imagerectangle($img, 24, 24, $w - 24, $h - 24, $gold);

            // Teks label (font bawaan GD)
            $textColor = imagecolorallocate($img, 255, 255, 255);
            $label = strtoupper('HM AKMAL');
            $name = strtoupper(mb_substr($product->name, 0, 28));

            // Garis dekoratif kecil
            imageline($img, 40, 90, 240, 90, $gold);
            imagestring($img, 5, 40, 110, $label, $gold);
            imagestring($img, 5, 40, 140, $name, $textColor);

            imagepng($img, $file, 6);
            imagedestroy($img);
        }

        return 'public/product_images/' . $filename;
    }

    /**
     * Konversi HSL ke RGB (0-255).
     */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h * 6, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 1 / 6) { [$r, $g, $b] = [$c, $x, 0]; }
        elseif ($h < 2 / 6) { [$r, $g, $b] = [$x, $c, 0]; }
        elseif ($h < 3 / 6) { [$r, $g, $b] = [0, $c, $x]; }
        elseif ($h < 4 / 6) { [$r, $g, $b] = [0, $x, $c]; }
        elseif ($h < 5 / 6) { [$r, $g, $b] = [$x, 0, $c]; }
        else { [$r, $g, $b] = [$c, 0, $x]; }

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }
}
