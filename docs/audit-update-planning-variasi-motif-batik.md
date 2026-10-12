# Hasil Audit — Update Variasi Motif Batik

**Tanggal audit:** 2026-10-13  
**Branch:** `dev`  
**Dokumen yang diaudit:** [planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md)  
**Cakupan:** konsistensi planning terhadap perubahan backend variasi motif yang ada di working tree, jalur stok/checkout, serta kelengkapan rencana UI.

## Kesimpulan

Update ini sudah memuat fondasi backend yang cukup luas: tabel dan model variasi, migrasi data produk lama, CRUD admin, keranjang, checkout, serta pemulihan stok saat order gagal/kedaluwarsa. Namun fitur **belum siap dipakai pembeli** karena P5–P9—terutama UI admin dan UI pemilihan motif—masih menunggu.

Ada satu risiko stok yang perlu diselesaikan sebelum produk bermotif dibuat/diaktifkan: backend tetap menerima pembelian melalui jalur ukuran lama untuk produk yang sudah ditandai memakai motif. Kedua jalur mengurangi dua catatan stok yang berbeda. Selain itu, ada risiko retry webhook bersamaan yang dapat mengembalikan stok variasi lebih dari sekali.

**Rekomendasi:** jangan jalankan migrasi data atau mengaktifkan katalog motif untuk pembeli sebelum risiko jalur stok diselesaikan dan alur UI/test relevan selesai.

## Temuan

| # | Prioritas | Temuan | Dampak |
|---|---|---|---|
| 1 | **Tinggi** | Produk bermotif masih dapat dibeli lewat jalur ukuran lama; stok variasi dan stok ukuran menjadi dua sumber terpisah. | Stok yang terlihat pada motif dapat tidak sesuai stok sebenarnya dan memungkinkan penjualan melebihi stok fisik. |
| 2 | **Sedang** | Webhook pembayaran gagal/kedaluwarsa tidak mengunci payment sebelum memeriksa status dan mengembalikan stok. | Dua notifikasi gagal yang diproses bersamaan dapat menaikkan stok variasi dua kali. |
| 3 | **Sedang** | Rencana P6 belum mencakup pemuatan relasi variasi pada seluruh sumber kartu produk. | Halaman katalog/pencarian/produk terkait/admin dapat tidak memiliki data motif atau memicu lazy-load saat UI memakai data variasi. |
| 4 | **Sedang** | Keputusan migrasi semua ukuran lama menjadi motif bertentangan dengan pertanyaan terbuka tentang produk S/M/L. | Produk lama bisa berubah makna—misalnya ukuran baju menjadi nama motif—jika command dijalankan tanpa konfirmasi. |
| 5 | **Rendah** | Penghapusan fisik variasi hanya memeriksa relasi order dan cart, tidak memeriksa relasi payment detail seperti yang dijelaskan komentarnya. | Jika payment detail masih menyimpan variasi tetapi order detail sudah tidak ada, FK variasi dapat ter-null saat variasi dihapus. Snapshot `motif_name` masih membantu riwayat, tetapi kontrak retensi ID tidak konsisten. |
| 6 | **Rendah** | Jumlah hasil Fase C belum diselaraskan pada seluruh dokumen planning. | Planning variasi dan laporan Fase C menyebut 9 skenario lulus, sedangkan checklist master planning automation masih mencatat 8/8. |

### 1. Jalur lama masih memproses produk bermotif

`CartAddRequest` menerima `product_variant_id` sebagai nullable dan menerima `size` saat ID variasi tidak dikirim ([CartAddRequest.php](../app/Http/Requests/Cart/CartAddRequest.php#L26)). `CartService::add()` lalu memilih jalur lama `addSize()` bila request tidak berisi ID variasi ([CartService.php](../app/Services/CartService.php#L20), [CartService.php](../app/Services/CartService.php#L90)). Jalur itu menggunakan stok `products_sizes`; sementara jalur motif menggunakan stok `product_variants`.

Di sisi lain, membuat variasi otomatis mengubah `products.has_variants` menjadi `true` ([ProductVariantService.php](../app/Services/ProductVariantService.php#L35)), dan command migrasi menyalin stok ukuran lama ke variasi tanpa menghapus `products_sizes`. Planning juga mencatat bahwa produk bermotif masih dapat dibeli melalui jalur ukuran sebelum UI selesai (lihat [planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md#L253)).

Dengan demikian, sebuah produk dapat memiliki dua stok yang berjalan sendiri-sendiri. Memilih motif tidak mengurangi stok ukuran lama, dan memakai ukuran lama tidak mengurangi stok motif.

**Tindakan yang disarankan:** sebelum mengaktifkan variasi, pastikan backend menolak jalur `size` untuk produk `has_variants = true`, atau sediakan satu sumber stok yang konsisten selama transisi. Jangan menjalankan migrasi produksi lebih awal jika frontend lama masih dapat melakukan pembelian melalui ukuran.

### 2. Pemulihan stok webhook belum idempoten terhadap request bersamaan

Payment dibaca dan status terminal (`success`/`failed`) diperiksa sebelum transaksi dimulai ([PaymentWebhookService.php](../app/Services/PaymentWebhookService.php#L32)). Transaksi berikutnya tidak mengunci ulang payment atau mengondisikan update pada status sebelumnya, lalu memulihkan stok variasi untuk status gagal/kedaluwarsa ([PaymentWebhookService.php](../app/Services/PaymentWebhookService.php#L38), [PaymentWebhookService.php](../app/Services/PaymentWebhookService.php#L81)).

Jika dua notifikasi kegagalan untuk payment yang sama tiba bersamaan, keduanya berpotensi membaca status `pending`, lalu sama-sama menjalankan `restoreVariant()`. Dampaknya stok variasi dapat bertambah dua kali.

**Tindakan yang disarankan:** ambil payment dengan `lockForUpdate()` di dalam transaksi, cek status saat sudah terkunci, dan lakukan perubahan status serta pengembalian stok hanya untuk satu transisi dari status non-terminal ke gagal. Tambahkan test untuk webhook gagal berulang/bersamaan.

### 3. P6 perlu merinci eager loading untuk katalog dan semua kartu produk

P6 berencana memakai variasi pada `ProductCards`, katalog, beranda, pencarian, dan produk terkait ([planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md#L286)). Tetapi saat ini `ProductRepository::getAllPaginated()` hanya eager-load `sizes` dan `images` ([ProductRepository.php](../app/Repositories/Eloquent/ProductRepository.php#L16)); `SearchController` juga hanya memuat `sizes` dan `images` ([SearchController.php](../app/Http/Controllers/SearchController.php#L13)). Query produk terkait di `HomeController` memiliki pola yang sama ([HomeController.php](../app/Http/Controllers/HomeController.php#L48)).

Jika kartu menghitung harga minimum atau jumlah motif dari `variants`, relasi tersebut perlu disediakan oleh semua endpoint terkait—termasuk produk terkait dan daftar admin—dengan variasi aktif saja pada tampilan publik. Tanpa itu, data tidak tersedia atau akan mengandalkan lazy loading, yang berisiko gagal karena konfigurasi strict loading.

**Tindakan yang disarankan:** tambahkan daftar sumber data tersebut ke checklist P5/P6 dan test respons Inertia agar setiap halaman menerima variasi yang diperlukan tanpa lazy loading.

### 4. Pertanyaan produk lama masih terbuka, tetapi keputusan migrasi sudah menetapkan semua dimigrasikan

Bagian pertanyaan toko masih menanyakan apakah pilihan S/M/L tetap ditawarkan atau diganti ([planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md#L129)). Namun tabel keputusan menyatakan semua produk lama dimigrasikan menjadi motif ([planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md#L143)), dan command benar-benar mengubah setiap baris `products_sizes` menjadi variasi dengan nama ukuran asal.

**Tindakan yang disarankan:** konfirmasi dahulu apakah semua produk lama memang kain batik yang ukuran S/M/L-nya salah label, atau ada produk yang ukuran tersebut harus tetap menjadi ukuran. Batasi command berdasarkan produk yang telah disetujui jika jawabannya tidak berlaku untuk semua produk.

### 5. Pemeriksaan penggunaan variasi tidak mencakup payment details

Komentar `ProductVariantService::delete()` menyebut order dan payment details tetap menunjuk variasi, tetapi kondisi penggunaan hanya memeriksa `orderDetails()` dan `cartDetails()` ([ProductVariantService.php](../app/Services/ProductVariantService.php#L73), [ProductVariantService.php](../app/Services/ProductVariantService.php#L79)). Sementara `PaymentDetail` juga memiliki `product_variant_id` ([PaymentDetail.php](../app/Models/PaymentDetail.php#L12)); model `ProductVariant` saat ini belum menyediakan relasi `paymentDetails()`.

**Tindakan yang disarankan:** tambahkan relasi `paymentDetails()` dan sertakan pemakaiannya pada keputusan soft-delete, atau ubah komentar/kontrak bila snapshot payment memang cukup dan ID variasi tidak perlu dipertahankan.

### 6. Checklist Fase C perlu diselaraskan

Planning variasi menyatakan Fase C Playwright selesai dan merujuk dokumen hasilnya ([planning-variasi-motif-batik.md](./planning-variasi-motif-batik.md#L28)). Dokumen Fase C juga mencatat 9 skenario lulus. Namun checklist master di [planning-fix-p0-dan-automation-testing-2026-08-22.md](./planning-fix-p0-dan-automation-testing-2026-08-22.md#L315) masih menyebut 8/8, bukan 9/9. Samakan angka/status historis supaya pembaca tidak mendapat hasil berbeda. Fase D CI tetap belum selesai menurut dokumen-dokumen yang diperiksa.

## Yang sudah baik

- Variasi memiliki ID sendiri, harga dan stok sendiri, foto opsional, serta status aktif.
- Keranjang membedakan motif berbeda dan checkout memvalidasi stok serta mengunci baris variasi.
- Order dan payment detail menyimpan `motif_name` sebagai snapshot, sehingga perubahan nama katalog tidak langsung mengubah nama pada transaksi lama.
- Command migrasi memiliki opsi `--dry-run`, mempertahankan tabel ukuran lama, dan dirancang idempotent.
- P5–P9 ditandai menunggu; dokumen tidak menyatakan UI pembeli/admin variasi sudah tersedia.

## Verifikasi yang dilakukan

- Pemeriksaan kode statis dan hubungan antarlapisan pada branch `dev`.
- `php -l` untuk file PHP yang berubah/ditambahkan: **semua lulus sintaks**.
- `php artisan test --filter='ProductVariantTest|MigrateSizesToVariantsTest'`: **tidak dapat dijalankan** karena `vendor/autoload.php` tidak tersedia di working tree.
- Karena dependency PHP tidak tersedia, klaim `197 passed (803 assertions)` di planning **belum dapat diverifikasi ulang dalam audit ini**. Angka itu dicatat sebagai hasil yang diklaim dokumen, bukan hasil pengujian audit.

Perubahan fitur yang diperiksa masih berada di working tree branch `dev` dan belum seluruhnya tercatat di `origin/dev`. Audit ini tidak mengubah atau memperbaiki implementasi.
