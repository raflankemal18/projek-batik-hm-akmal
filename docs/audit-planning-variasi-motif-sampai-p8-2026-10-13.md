# Hasil Audit — Planning Variasi Motif sampai P8

**Tanggal:** 2026-10-13  
**Branch:** `dev`  
**Dokumen yang diaudit:** [planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md)  
**Cakupan:** P0–P8, klaim hasil verifikasi, perubahan implementasi terkait, dan konsistensi rencana. P9 dinilai hanya sebagai pekerjaan tersisa, bukan diaudit sebagai implementasi.

## Ringkasan

P1–P8 telah menghasilkan implementasi substansial: backend variasi, migrasi data lama, administrasi motif, katalog/detail pembeli, tampilan pesanan, feature tests, dan skenario E2E. Perbaikan audit sebelumnya juga terlihat pada kode.

Namun, **saya belum dapat mengonfirmasi bahwa P0–P8 sepenuhnya lulus saat ini**. Ada satu masalah data transaksi yang secara eksplisit masih terbuka: menghapus produk menghapus rincian item pada order. Selain itu, admin dapat mematikan mode variasi meski variasi masih ada, beberapa bagian dokumen masih menyatakan P5/P6 belum dikerjakan, dan verifikasi build/test yang diklaim di dokumen tidak dapat diulang pada workspace ini.

**Kesimpulan audit:** status “P0–P8 selesai secara implementasi” masuk akal sebagai catatan pekerjaan, tetapi **belum layak dibaca sebagai “seluruh kriteria selesai dan terverifikasi tanpa temuan terbuka”**. P9 masih menunggu seed konten dan smoke final. Sebelum rilis, putuskan penanganan penghapusan produk dan lindungi transisi mode variasi.

## Temuan

| # | Prioritas | Temuan | Dampak |
|---|---|---|---|
| 1 | **Tinggi** | Hard-delete produk menghapus baris rincian order; test P8 membuktikan dan mengharapkan penghapusan itu. | Riwayat transaksi kehilangan rincian barang yang dibeli; bertentangan dengan kriteria data transaksi tetap. |
| 2 | **Sedang** | Admin dapat mematikan `has_variants` tanpa menonaktifkan/menghapus variasi aktif. | Produk dapat beralih ke UI dan sumber stok ukuran sementara variasi masih aktif/tersimpan. |
| 3 | **Sedang** | Dokumen menyebut P0–P8 selesai, tetapi beberapa bagian lama masih menyebut UI belum ada dan menyarankan mulai P5/P6. | Status dan instruksi implementasi saling bertentangan. |
| 4 | **Sedang** | Klaim 228 Pest / 12 Playwright / build / race check tidak dapat direproduksi di workspace saat audit. Build saat ini gagal karena `react-is` tidak ditemukan. | Status hijau yang tercatat belum dikonfirmasi terhadap working tree sekarang. |
| 5 | **Rendah** | Link laporan audit sebelumnya di planning memakai nama file yang tidak cocok dengan file audit yang ada. | Pembaca tidak dapat membuka sumber audit melalui tautan dokumen. |

### 1. Penghapusan produk menghilangkan rincian item order

P8 sendiri mencatat bahwa `ProductController::destroy()` melakukan hard delete. Foreign key `order_details.product_id` menggunakan cascade ke tabel produk. Test “histori order tetap terbaca setelah produk dihapus” kemudian secara eksplisit mengharapkan `OrderDetail`-nya **tidak ada** setelah penghapusan; order dan totalnya tetap ada, tetapi itemnya hilang. Lihat [ProductVariantStockTest.php](../tests/Feature/ProductVariantStockTest.php) dan [ProductController.php](../app/Http/Controllers/ProductController.php).

Ini bukan sekadar skenario hipotetis: test mengabadikan perilaku kehilangan rincian tersebut. Perilaku itu bertentangan dengan kriteria di planning bahwa data transaksi tidak berubah ketika data katalog diubah/dihapus. Catatan P8 telah jujur menyebutnya sebagai temuan terbuka, tetapi status keseluruhan P0–P8 “selesai & terverifikasi” sebaiknya tidak mengaburkan temuan ini.

**Tindak lanjut:** putuskan aturan toko, lalu implementasikan salah satu kebijakan sebelum menganggap kriteria histori terpenuhi:

- larang penghapusan produk yang pernah dipakai transaksi dan sediakan aksi arsip/nonaktif; atau
- gunakan soft delete dan pastikan relasi/riwayat dapat tetap memuat produk terhapus; atau
- simpan snapshot item transaksi yang cukup agar invoice/order tetap utuh tanpa bergantung pada produk katalog.

Tambahkan test yang mengharapkan rincian order dan invoice tetap dapat dibaca setelah tindakan katalog yang diizinkan. Test saat ini justru mengunci keadaan kehilangan detail sebagai hasil yang diharapkan.

### 2. Mode variasi dapat dimatikan sementara variasi masih aktif

Checkbox “Produk memakai pilihan motif” pada modal admin selalu dapat diubah ([Modal.jsx](../resources/js/Pages/admin/form/modal/Modal.jsx)). Request menerima `has_variants` sebagai boolean/null ([UpdateProductRequest.php](../app/Http/Requests/Admin/Product/UpdateProductRequest.php)), lalu [ProductService.php](../app/Services/ProductService.php) menyimpan nilai itu langsung. Jika nilainya `false`, service menjalankan sinkronisasi `products_sizes`, tetapi tidak menonaktifkan atau menghapus variasi aktif.

Akibatnya, produk masih menyimpan variasi yang dapat diakses lewat backend, tetapi katalog menganggap produk bukan produk bermotif dan menggunakan ukuran lama. Untuk produk hasil migrasi, ini juga dapat mengalihkan jalur pembelian dari stok variasi ke `products_sizes`, sehingga pemisahan dua sumber stok yang sebelumnya diperbaiki dapat muncul kembali setelah perubahan admin.

**Tindak lanjut:** cegah perubahan `has_variants: true → false` selama masih ada variasi aktif/terpakai, atau sediakan alur transisi eksplisit yang memetakan variasi, keranjang, dan stok dengan aman. Tambahkan test untuk produk yang sudah punya variasi aktif dan histori order.

### 3. Status P5/P6 dan langkah berikutnya tidak konsisten

Bagian awal masih menyatakan UI admin/pembeli belum ada, dan bagian “Yang tersisa (P5–P9)” masih mencantumkan UI yang sekarang ditandai selesai. Bagian “Langkah berikutnya” juga masih menyarankan mulai P5, lalu P6/P7/P8, meski judul-judul tersebut kini sudah ditandai selesai. Lihat [planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md).

**Tindak lanjut:** rapikan bagian status historis menjadi “status saat perencanaan awal” atau pindahkan ke catatan riwayat. Perbarui daftar sisa pekerjaan dan langkah berikutnya agar hanya merujuk ke P9 dan keputusan terbuka.

### 4. Verifikasi terbaru belum dapat diulang

Perencanaan mencatat:

- `php artisan test`: 228 passed (1002 assertions);
- `npx playwright test`: 12 passed;
- `npm run build`: sukses;
- `scripts/race-variant-stock.sh`: stok aman, satu order, tanpa HTTP 500.

Pada audit ini:

- `php artisan test --filter='ProductVariantTest|MigrateSizesToVariantsTest'` tidak dapat berjalan karena `vendor/autoload.php` tidak ada.
- `npm run build` gagal saat Rollup menyelesaikan `recharts`: modul `react-is` tidak ditemukan. `npm ls react-is --depth=1` juga menunjukkan paket tersebut tidak terpasang.
- Saya tidak menjalankan ulang Playwright atau skrip race. Test backend membutuhkan instalasi Composer; skrip race memulai dengan me-reset database E2E, sehingga tidak dijalankan tanpa memastikan lingkungan E2E yang tepat.
- `php -l` pada file implementasi/test PHP terkait yang diperiksa lulus.

Kegagalan build yang diamati **belum membuktikan** regresi dari perubahan variasi; bisa merupakan dependency yang tidak terpasang di workspace. Namun, sampai dependency dipulihkan dan build/test diulang pada working tree yang sekarang, klaim hijau hanya dapat diperlakukan sebagai hasil yang dicatat dokumen, bukan hasil audit ini.

### 5. Referensi laporan audit sebelumnya salah nama

Planning merujuk `docs/audit-update-planning-motif-batik.md`, sedangkan file yang tersedia adalah [audit-update-planning-variasi-motif-batik.md](./audit-update-planning-variasi-motif-batik.md). Perbaiki nama tautan di planning agar rujukan dapat dibuka.

## Yang terkonfirmasi baik

- Model variasi menyimpan harga, stok, foto opsional, dan status aktif secara terpisah.
- Keranjang dan checkout mendukung ID variasi, mengunci stok variasi, menggunakan harga variasi, dan menyimpan snapshot nama motif pada order/payment detail.
- Perbaikan jalur stok lama terlihat di `CartService` dan `CheckoutService`: produk bermotif dipetakan ke motif bernama sama dan tidak memakai `products_sizes` sebagai sumber stok untuk checkout.
- Webhook kini memproses payment di dalam transaksi dengan `lockForUpdate()` sebelum mengecek status terminal dan memulihkan stok.
- Relasi variasi dimuat pada query produk publik yang diperiksa, dan query admin memuat motif nonaktif untuk pengelolaan.
- Test P5–P8 mencakup CRUD admin, pemilihan motif pembeli, snapshot transaksi, batas stok, webhook berulang, dan uji checkout konkuren melalui skrip terpisah.
- Command migrasi menyediakan `--dry-run`, pembatas `--product`, dan konfirmasi eksplisit, sehingga lebih aman daripada migrasi massal tanpa konfirmasi.

## Pekerjaan tersisa sebelum menyatakan rilis siap

1. Putuskan dan perbaiki kebijakan penghapusan produk agar rincian transaksi tidak hilang.
2. Kunci alur perubahan `has_variants` agar variasi aktif dan stok tidak terlepas dari mode katalog.
3. Sinkronkan bagian status dan instruksi lama pada planning; betulkan link audit sebelumnya.
4. Pulihkan dependency/build environment, lalu jalankan ulang Pest, Playwright, build, dan uji race terhadap working tree yang sama.
5. Kerjakan P9: masukkan data produk/motif/cover yang disetujui toko dan smoke-test sebagai admin serta pembeli. Jangan mengarang nama motif atau stok.

## Batas audit

Audit ini tidak mengubah implementasi atau file planning yang diaudit. Perubahan variasi/UI yang diperiksa berada di working tree branch `dev`; temuan harus ditinjau ulang setelah perubahan berikutnya dan sebelum perubahan tersebut dipublikasikan.
