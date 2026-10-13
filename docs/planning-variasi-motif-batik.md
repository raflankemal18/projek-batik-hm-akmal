# Planning — Variasi Motif Kain Batik

**Status:** **P0–P8 selesai (terakhir 2026-10-13)**; **audit #1 dan audit #2 sudah diperbaiki** — penghapusan produk sekarang soft delete (arsip) + bisa dipulihkan, mode motif dikunci saat masih ada variasi, dokumen & link sudah dirapikan. Verifikasi setelah perbaikan ada di § Hasil audit #2. Sisa: **P9** (seed produk template & smoke).  
**Konteks:** Permintaan pemilik toko agar pembeli bisa memilih motif kain seperti pilihan variasi di marketplace.  
**Dasar teknis:** Struktur aplikasi pada branch `dev` dan catatan implementasi Fase A/B di `docs/planning-fix-p0-dan-automation-testing-2026-08-22.md`.

## Tujuan

Pembeli memilih motif kain yang tersedia pada satu produk sebelum memasukkannya ke keranjang. Halaman produk menampilkan nama, harga, stok, dan—ketika foto variasi sudah tersedia—foto untuk setiap motif. Untuk batch awal, cover produk dapat dipakai sebagai gambar utama; foto motif dapat ditambahkan kemudian tanpa mengubah konsep pilihan.

Data produk awal yang disampaikan:

- **Nama template:** `Batik Cap [jumlah warna] Warna [Warna Batiknya]`
- **Harga yang disampaikan:**
  - 1 Warna: Rp75.000
  - 2 Warna: Rp85.000
  - 1 Warna Mix Motif: Rp105.000
- **Ukuran kain:** 2 m × 1,1 m
- **Gambar:** cover menyusul; foto motif akan tersedia kemudian melalui Google Drive.

## Kondisi aplikasi saat ini

- Produk memiliki satu harga dan beberapa gambar.
- `products_sizes` dipakai untuk menyimpan pilihan bernama `size` dan stok. Di halaman produk, pilihan tersebut ditampilkan sebagai ukuran.
- Keranjang saat ini menyimpan `product_id`, `size`, dan jumlah. Baris keranjang yang sama dibedakan berdasarkan produk dan teks `size`.
- Saat checkout, stok dikunci/dikurangi berdasarkan `ProductSize`; order menyimpan harga saat pembelian dan snapshot `size`. Pembayaran dan invoice bergantung pada detail order tersebut.
- Karena harga saat ini berada di produk dan pilihan keranjang tidak memiliki ID motif, sekadar menambahkan tombol pilihan motif di frontend tidak cukup: pilihan berbeda dapat tergabung di keranjang, stoknya tidak terpisah, dan riwayat pesanan tidak dapat menyimpan motif yang dipilih.
- Fase A dan B backend selesai, dan **Fase C (Playwright E2E, 9 skenario) juga sudah selesai** — lihat `docs/planning-fase-c-playwright-e2e-2026-08-22.md`. Fase D (GitHub Actions CI) belum selesai, dan tidak menjadi prasyarat_features variasi.
- *Status saat perencanaan (2026-08-13, sudah usang):* backend variasi baru ada (P1–P4) dan UI belum ada.
  **Kondisi sekarang: P0–P8 selesai** — admin sudah bisa mengelola motif, pembeli sudah bisa memilihnya.
  Lihat § Hasil eksekusi P5–P8.

## Rencana implementasi

### Tahap 0 — Konfirmasi data dan aturan toko

Sebelum perubahan database/kode, pastikan:

1. Apakah tiga harga di atas adalah **jenis produk/paket yang berbeda**, atau variasi harga dalam satu produk?
2. Apakah pilihan motif dalam setiap produk memiliki harga sama, atau motif tertentu bisa memiliki harga berbeda?
3. Apakah stok dicatat per motif? Jika ya, minta jumlah stok tiap motif. Jangan mengasumsikan semua motif selalu tersedia.
4. Apakah nama motif sudah ditentukan? Untuk sementara nama dapat diisi admin dan fotonya boleh kosong.
5. Konfirmasi apakah Rp75.000/Rp85.000/Rp105.000 harga per lembar kain ukuran 2 m × 1,1 m, serta apakah harga tersebut masih final.

**Asumsi sementara untuk perencanaan, bukan keputusan final:** setiap produk mewakili satu jenis/warna kain; variasi di dalamnya adalah motif. Harga disimpan pada tiap pilihan motif agar harga checkout selalu berasal dari variasi yang dipilih. Tarif pada daftar di atas dipetakan ke produknya berdasarkan jawaban toko. Ukuran 2 m × 1,1 m adalah informasi produk, bukan pilihan ukuran/stok.

### Tahap 1 — Rancangan data variasi dan kompatibilitas

- Tambahkan entitas variasi produk yang terhubung ke produk induk, dengan sekurangnya nama/kode motif, harga, stok, dan foto opsional.
- Gunakan ID variasi saat memilih motif; jangan menggunakan kolom ukuran lama sebagai ID motif.
- Simpan referensi variasi yang dipilih pada item keranjang dan detail order, serta snapshot nama motif dan harga pada order. Simpan snapshot yang diperlukan pada detail pembayaran/invoice agar transaksi lama tetap terbaca meskipun data katalog berubah.
- Pastikan identitas baris keranjang mencakup produk **dan** variasi. Dua motif dari produk yang sama harus menjadi pilihan/baris terpisah; menambah motif yang sama dapat menambah kuantitasnya.
- Integrasikan pengurangan/pemulihan stok dan penguncian transaksi yang sudah digunakan checkout. Validasi stok harus dilakukan di server, bukan hanya dengan menonaktifkan tombol di browser.
- Buat migrasi yang tidak menghapus data lama. Produk lama yang masih menggunakan pilihan ukuran tetap berjalan; variasi motif hanya diwajibkan untuk produk yang ditandai memakai model variasi baru.
- Audit penghapusan produk/variasi dan foreign key supaya riwayat order tidak kehilangan nama motif/harga yang telah dibeli.

### Tahap 2 — Pengelolaan produk dan motif oleh admin

- Perluas form produk admin untuk data nama, deskripsi, kategori, harga/jenis harga, dan ukuran kain; pertahankan cover sebagai gambar produk.
- Sediakan pengelolaan beberapa variasi motif dalam satu produk: tambah, ubah, hapus, atur nama, harga bila diizinkan, stok, dan unggah foto opsional.
- Validasi server untuk nama motif, harga positif, stok tidak negatif, tipe/ukuran file gambar, serta hak akses admin.
- Perubahan variasi tidak boleh diam-diam mengubah harga pada order yang sudah dibuat.
- Admin dapat menyimpan produk dengan cover tetapi tanpa foto motif untuk tahap awal; motif tetap dapat dipilih dari nama/tombol variasi.

### Tahap 3 — Katalog dan halaman detail pembeli

- Kartu katalog tetap menunjukkan nama produk, cover, harga yang sesuai aturan harga yang telah dikonfirmasi, dan ringkasan jumlah/pilihan motif.
- Halaman detail menampilkan ukuran kain `2 m × 1,1 m` sebagai informasi spesifikasi, bukan pilihan ukuran.
- Tampilkan pilihan motif sebagai tombol/kartu variasi yang mudah dipahami. Setelah foto motif tersedia, gunakan thumbnail; sebelum tersedia, tampilkan nama motif dengan placeholder yang konsisten.
- Saat motif dipilih, tampilkan foto motif (jika ada), harga, dan stok motif tersebut; tombol tambah ke keranjang hanya aktif bila variasi valid dan stok tersedia.
- Wishlist dan pencarian tetap bekerja pada produk induk, bukan membuat tiap motif tampil sebagai produk terpisah.
- Semua jalur tampilan produk yang memakai komponen kartu/detail harus memakai aturan yang sama, termasuk beranda, katalog, pencarian, wishlist, dan produk terkait.

### Tahap 4 — Keranjang, checkout, pembayaran, dan pesanan

- Kirim ID variasi terpilih saat tambah ke keranjang; server memverifikasi bahwa variasi aktif milik produk tersebut.
- Tampilkan nama/foto motif, jumlah, harga satuan, subtotal, dan ketersediaan pada keranjang.
- Izinkan pembeli memilih dua motif berbeda dari produk yang sama tanpa menggabungkannya secara keliru.
- Pada checkout, hitung harga dan validasi/kurangi stok dari variasi yang dipilih dalam transaksi yang sama dengan pembuatan order.
- Pastikan penanganan order batal/kedaluwarsa mengembalikan stok variasi yang benar.
- Sertakan snapshot nama motif dan harga di detail order, halaman pembayaran, invoice, notifikasi atau export terkait yang menampilkan item, dan halaman admin order. Jangan bergantung hanya pada nama variasi katalog yang bisa diedit.

### Tahap 5 — Pengujian dan peluncuran bertahap

Tambahkan test feature untuk:

- Admin dapat membuat/mengubah variasi; non-admin tidak bisa mengelolanya.
- Validasi motif/harga/stok dan penolakan ID variasi milik produk lain.
- Motif berbeda menghasilkan item keranjang terpisah; motif sama menambah kuantitas.
- Harga subtotal dan total berasal dari variasi yang dipilih.
- Stok motif habis mencegah checkout; stok motif lain tetap dapat dibeli.
- Checkout konkuren tidak membuat stok variasi negatif; pembatalan/kedaluwarsa mengembalikan stok yang tepat.
- Detail pilihan motif dan harga tetap tampak pada order/pembayaran/invoice setelah data katalog diedit.
- Produk ukuran lama masih dapat dilihat dan dibeli.
- Tambahkan/ubah/hapus foto variasi, termasuk tampilan placeholder ketika foto belum diunggah.

Setelah test dan build lulus, admin dapat memasukkan produk template dan cover terlebih dahulu. Tambahkan foto motif dan stok aktual setelah datanya dikirim toko, lalu smoke-test pemilihan sampai invoice sebelum produk dipublikasikan.

## Kriteria selesai

- Pembeli dapat memilih motif tertentu dan melihat pilihan itu kembali di keranjang serta seluruh tampilan pesanan.
- Harga dan stok yang digunakan adalah milik motif/aturan harga yang benar dan tervalidasi backend.
- Foto motif boleh belum tersedia; ketiadaan foto tidak menghalangi produk dipilih/dibeli.
- Data transaksi yang sudah dibuat tidak berubah ketika nama, foto, harga, atau status variasi katalog diperbarui.
- Produk lama tetap berfungsi.
- Test backend terkait dan `npm run build` berhasil; skenario manual ujung-ke-ujung tervalidasi sebelum rilis.

## Dependensi dari toko

- Logo toko dan cover batik: dapat dipasang pada tahap pengisian konten/UI sesuai penempatannya.
- Daftar nama motif, foto motif, dan stok tiap motif: diperlukan untuk mengisi katalog lengkap. Pengembangan struktur pilihan dan placeholder dapat dilakukan lebih dulu.
- Konfirmasi arti tiga kelompok harga dan apakah tiap motif punya harga sama: diperlukan sebelum mengunci validasi harga dan menginput produk final.

## Pertanyaan untuk toko (menunggu jawaban — kirim ke pemilik toko)

Aturan harga dan stok mengunci implementasi, jadi mohon konfirmasi hal berikut. Jawaban menentukan
bagian mana yang boleh dikerjakan dan mana yang harus ditahan.

1. **Nama motif sudah final?** Mohon daftar nama motif per produk (foto menyusul lewat Google Drive). Untuk
   tahap awal nama boleh diisi admin sendiri dan fotonya boleh kosong.
2. **Stok per motif?** Berapa lembar untuk tiap motif? Stok dicatat **per motif** supaya motif yang laku
   habis tidak ikut menghabiskan stok motif lain. Mohon angka stok tiap motif, jangan diasumsikan semua
   motif selalu tersedia.
3. **Harga: boleh berbeda per motif?** Kami merancang harga tersimpan **di tiap motif**. Konfirmasi: apakah
   semua motif dalam satu produk (mis. semua motif "2 warna") boleh memakai harga yang sama (Rp85.000),
   atau ada motif yang harganya lebih tinggi?
4. **Harga final?** Apakah Rp75.000 / Rp85.000 / Rp105.000 benar-benar harga per lembar 2 m × 1,1 m, dan
   apakah ada biaya tambahan (kirim, packing, minimum pembelian)?
5. **Cover & logo.** Apakah cover produk dan logo toko sudah tersedia? Nama template persisnya apa
   (contoh: "Batik Cap 2 Warna Biru")?
6. **Produk lama (sudah diputuskan, mohon konfirmasi).** Keputusan sementara: produk lama memakai
   ukuran S/M/L **dimigrasikan** ke motif. Yang perlu dikonfirmasi: apakah produk yang ukuran S/M/L-nya
   memang kain (mis. "Batik Pria" → motif "M") atau ada produk yang ukuran itu harus tetap menjadi
   ukuran (mis. produk kaos). Kalau ada yang seperti itu, sebutkan — command migrasi bisa dibatasi per
   produk lewat `--product=<id>` dan menolak jalan tanpa konfirmasi eksplisit.

Sisa dokumen ini memakai asumsi sementara sampai jawaban masuk; jawaban di atas tidak memblokir
P1–P4 (kerangka backend dibuat netral terhadap isi harga/nama motif).

---

## K. Keputusan yang sudah diambil (2026-08-13)

| Topik | Keputusan | Konsekuensi teknis |
|---|---|---|
| Harga | **Harga per motif** (`product_variants.price`), bukan per produk | Checkout mengambil harga dari variasi terpilih; produk tanpa motif tetap memakai `products.harga` |
| Stok | **Stok per motif** (`product_variants.stock`) | Pengurangan/pemulihan stok memakai baris variasi, pesan error menyebut nama motif |
| Produk lama | **Dimigrasikan ke motif** | `php artisan products:migrate-sizes-to-variants` mengubah tiap `products_sizes` menjadi variasi bernama ukuran itu, lalu `products.has_variants = true` |
| Foto motif | Boleh kosong | UI menampilkan placeholder konsisten; pemilihan tidak bergantung foto |
| Cakupan putaran ini | **P1–P4 (backend)** | P5–P9 menunggu; setelah backend stabil baru dikerjakan UI admin & pembeli |

Konsekuensi yang perlu diketahui: setelah migrasi, **produk lama ikut memakai model motif**, sehingga
ukuran S/M/L akan tampil sebagai motif bernama "S"/"M"/"L" sampai admin menggantinya dengan nama motif
asli (berserta foto saat datastore dikirim).

---

## P0 — Dokumentasi

- [x] Catat keputusan di atas dan 6 pertanyaan untuk toko di dokumen ini.

## P1 — Skema DB + model (tanpa mengubah perilaku) ✅ **SELESAI**

- Tabel `product_variants`: `id`, `product_id` (FK `products`, cascade), `name`, `price` (decimal 12,2),
  `stock` (unsigned int), `image_path` (nullable), `is_active` (bool default true), timestamps,
  unique `(product_id, name)`.
- `products.has_variants` (bool, default false) sebagai penanda produk yang wajib memakai model baru.
- `cart_details.product_variant_id` (nullable, FK restrict), `order_details.product_variant_id`
  (nullable) + `motif_name` (snapshot), `payment_details.product_variant_id` + `motif_name` (snapshot).
- Model `ProductVariant` + relasi `Product::variants()`, `CartDetail`, `OrderDetail`, `PaymentDetail`.
- Migrasi tidak menghapus kolom/kolom lama, `down()` hanya membalik yang ditambahkan.
- **Verifikasi:** `migrate` naik/turun bersih + `php artisan test` tetap hijau.

## P1b — Migrasi data produk lama ke motif ✅ **SELESAI**

Command idempotent (bukan schema migration, supaya bisa dijalankan ulang & diaudit):
`php artisan products:migrate-sizes-to-variants [--dry-run]`

- Tiap baris `products_sizes` → `product_variants` (`name` = nilai `size` lama, `price` = `products.harga`,
  `stock` disalin), lalu `products.has_variants = true`.
- `cart_details.product_variant_id` diisi dari pasangan (product, size) yang cocok. Baris keranjang yang
  size-nya sudah tidak ada di katalog **dibiarkan** (tidak dihapus) supaya user tidak kehilangan isi
  keranjang secara diam-diam; baris itu tidak bisa di-checkout sampai motifnya dicocokkan admin.
- `products_sizes` **tidak dihapus**: `order_details.product_size_id` dari order lama masih merujuknya.
- **Verifikasi:** test command — produk contoh, jalan dua kali tetap sama, cart terpetakan, order lama utuh.

## P2 — Repository & lapisan stok ✅ **SELESAI**

- `ProductVariantRepositoryInterface` + `ProductVariantRepository`: `findOrNull()`, `findForUpdate()`,
  `findActiveByName()`, `decrementIfAvailable()`, `increment()`; didaftarkan di `AppServiceProvider`.
- `InventoryManager::decrementVariant()` / `restoreVariant()` dengan pesan menyebut **nama motif**
  (bukan "product size #12" seperti sekarang).
- Semua mutasi variation wajib memanggil `ProductService::forgetCatalogCache()` — katalog di-cache 300
  detik, harga/stok motif yang basi akan tampil salah di katalog.
- **Verifikasi:** unit test decrement/restore + suite tetap hijau.

## P3 — Keranjang & checkout memakai variasi ✅ **SELESAI**

- `CartService::add()` menerima `product_variant_id`: untuk produk `has_variants` field ini wajib dan
  variasinya harus **aktif serta milik produk tersebut**; identitas baris keranjang menjadi
  (produk + variasi) sehingga motif berbeda jadi baris terpisah dan motif sama menambah kuantitas.
- `CartAddRequest` / `CartQuantityRequest` aturan baru + pesan error yang jelas; jalur lama untuk produk
  non-varian tetap diterima.
- `CheckoutService::createDraftOrder()`: kunci baris variasi (`lockForUpdate`, retry deadlock 3×),
  harga dari variasi, snapshot `motif_name`, kurangi stok variasi.
- `PaymentController::store()` menyalin `motif_name` ke `payment_details` supaya invoice lama tetap terbaca.
- **Verifikasi:** feature test — motif berbeda = baris terpisah, motif sama = qty bertambah, subtotal dari
  harga variasi, motif habis ditolak, variasi milik produk lain ditolak, order user lain/expired ditolak.

## P4 — CRUD motif oleh admin ✅ **SELESAI**

- Route `POST|PUT|DELETE /admin/products/{product}/variants` (middleware `role:admin`),
  `ProductPolicy::manageVariants()`.
- `StoreVariantRequest` / `UpdateVariantRequest`: `name` required max 60, `price` numeric > 0,
  `stock` integer ≥ 0, `image` nullable mimes jpg/jpeg/png/webp ≤ 2MB.
- `ProductVariantService`: create / update / delete (hapus file foto), audit log
  `product.variant_created|updated|deleted`, dan selalu `forgetCatalogCache()`.
- Variasi yang pernah dipakai order **tidak dihapus fisik** → `is_active = false`.
- **Verifikasi:** feature test CRUD admin, non-admin 403, validasi, variasi aktif/tidak aktif, audit log.

### Hasil eksekusi P0–P4 (2026-08-13)

| Verifikasi | Hasil |
|---|---|
| `php artisan test` | **197 passed (803 assertions)** — 173 lama + 18 `ProductVariantTest` + 6 `MigrateSizesToVariantsTest` |
| `npx playwright test` | **9 passed** — nol regresi di alur lama |
| `npm run build` | sukses |
| `migrate` naik/turun | `migrate:fresh` → rollback 2 migration → migrate lagi, struktur persis kembali |

**File baru:** `app/Models/ProductVariant.php`, `app/Console/Commands/MigrateSizesToVariantsCommand.php`,
`app/Services/ProductVariantService.php`, `app/Contracts/Repositories/ProductVariantRepositoryInterface.php`,
`app/Repositories/Eloquent/ProductVariantRepository.php`, `app/Http/Controllers/Admin/ProductVariantController.php`,
`app/Http/Requests/Admin/Product/{Store,Update}VariantRequest.php`,
2 migrasi `2026_08_13_*`, `tests/Feature/ProductVariantTest.php`, `tests/Feature/MigrateSizesToVariantsTest.php`.

**Perilaku yang dijamin oleh test:**
- Dua motif berbeda dari produk yang sama = dua baris keranjang; motif sama menambah kuantitas.
- Motif harus milik produk yang dipilih **dan aktif** (divalidasi di FormRequest, bukan exception).
- Checkout memakai harga motif, mengunci baris variasi, dan mengurangi stok variasi.
- Pesan stok habis menyebut **nama motif** (`Stok tidak cukup untuk … motif Kawung`).
- `motif_name` tersimpan di `order_details` **dan** `payment_details`: mengganti nama motif di katalog
  tidak mengubah transaksi lama.
- Order lama (yang masih `product_size_id`) tetap utuh dan bisa diproses jalur lama.
- Motif yang pernah dipakai order hanya dinonaktifkan, tidak dihapus fisik.
- Migrasi produk lama idempotent dan Dry-run tidak menulis apa pun.

**Catatan teknis yang ditemukan saat pengerjaan (bukan tebisan):**
1. **Index gabungan di `cart_details` dibatalkan.** Versi pertama menambah index
   `(cart_id, product_variant_id)`; begitu migration itu di-rollback, MySQL *mendekati* index itu jadi
   `(cart_id)` dan tidak bisa dihapus lagi (error 1553, jadi satu-satunya penopang FK `cart_id`) — migrate
   kedua gagal. Index dibiarkan ke default Laravel; tabel keranjang kecil, tidak masalah performa.
2. **`UploadedFile::fake()->image()` tidak bisa dipakai di test container ini** — GD di-built tanpa
   dukungan JPEG (`imagejpeg` undefined). Test motif memakai `fake()->create(..., 'image/jpeg')`,
   sama seperti `BannerTest` yang sudah lebih dulu memakai pola itu.
3. **`actingAs()` bertahan antar-request dalam satu test** — karena itu kasus "tamu ditolak" ditulis
   sebagai test terpisah; jika digabung, request kedua masih memakai sesi user biasa dan hasilnya 403,
   bukan redirect `/login`.
4. **Produk bermotif tetap bisa dibeli lewat jalur ukuran selama P5/P6 belum selesai.** `products_sizes`
   tidak dihapus dan `CartService` bercabang: `product_variant_id` diisi → jalur motif; kosong → jalur
   ukuran lama. Inilah alasan suite Playwright tetap hijau setelah command migrasi dijalankan.

**Cara menjalankan migrasi data di lingkungan nyata:**
```bash
php artisan products:migrate-sizes-to-variants --dry-run   # lihat rencana dulu
php artisan products:migrate-sizes-to-variants            # eksekusi (aman diulang)
```

**Yang tersisa saat ini:** hanya **P9** (seed produk template & smoke akhir). P5–P8 sudah selesai — rinciannya
di § Hasil eksekusi P5, P6 & P7, dan P8.

## P5 — UI admin: kelola motif ✅ **SELESAI**

Tujuannya: admin bisa menambah/mengubah motif tanpa menyentuh database, dan bisa melihat foto motif yang
sudah diunggah.

- `resources/js/Pages/admin/form/modal/Modal.jsx`: blok baru "Motif kain" — daftar motif (nama, harga,
  stok, foto), tombol tambah/ubah/hapus, upload foto opsional. Kirim ke route yang sudah ada:
  `POST /admin/products/{product}/variants`, `PUT …/variants/{variant}`, `DELETE …/variants/{variant}`.
- Dua mode dalam modal yang sama: motif dikirim sebagai FormData terpisah per motif (bukan `variants[]`
  di produk), supaya satu request gagal tidak damaging seluruh produk.
- Foto motif kosong → pakai `Components/ui/FallbackImage.jsx` supaya placeholder konsisten dengan kartu
  produk (belum ada foto dari toko).
- `resources/js/Pages/admin/Products.jsx`: kolom "Varian & Stok" membedakan motif (nama + stok) dari
  ukuran lama (S: 3, M: 5).
- Switch "produk memakai pilihan motif" di form produk (menulis `products.has_variants`), plus kolom
  ukuran kain `2 m × 1,1 m` sebagai spesifikasi.
- **Verifikasi:** `npm run build` + smoke manual admin (tambah motif → foto → ubah → hapus → motif
  nonaktif tidak muncul lagi).

### Hasil eksekusi P5 (2026-10-13)

**Verifikasi:** `php artisan test` **214 passed (890 assertions)**, Playwright **9/9**, `npm run build` sukses, dan
smoke manual lewat browser (admin → tambah produk bermotif → tambah 2 motif → ubah → hapus) berhasil.

**Yang ditambahkan**
- `resources/js/Pages/admin/form/VariantManager.jsx` — daftar motif (foto / placeholder `ImageOff`), tambah,
  ubah, hapus, upload foto opsional, plus keterangan jumlah motif aktif. Foto kosong tidak menghalangi.
- `resources/js/Pages/admin/form/modal/Modal.jsx` — blok motif (mode edit), kolom **Ukuran Kain**
  (`products.fabric_size`, migrasi `2026_08_13_000002`), dan switch **"Produk memakai pilihan motif"**.
  Blok ukuran S/M/L disembunyikan & tidak dikirim kalau produk bermotif.
- `resources/js/Pages/admin/Products.jsx` — kolom varian menampilkan motif (nama + stok, nonaktif dicoret)
  untuk produk bermotif, ukuran untuk produk lama, dan "Belum ada motif" bila kosong.
- Backend: `ProductRepository::getAllPaginatedForAdmin()` (semua motif, tanpa cache katalog), dipakai
  `ProductController::index`; `AddProductRequest`/`UpdateProductRequest` menerima `fabric_size` +
  `has_variants` dan membuat `sizes` opsional saat produk bermotif.
- Test: `tests/Feature/Admin/ProductVariantAdminUITest.php` (5 test).

**Tiga bug UI yang ketahuan & diperbaiki saat smoke (semua ketahuan karena probe browser, bukan kode):**
1. **Tombol "Tambah Motif" diam-diam men-submit form produk.** `VariantManager` memakai `<form>` di dalam
   `<form>` modal — HTML tidak boleh punya form bersarang, browser menutup form dalam sehingga tombolnya
   mengaktifkan form produk. Fix: `div` + tombol `type="button"`.
2. **Input file menutup seluruh area form.** `input[type=file]` memakai `absolute inset-0` tanpa parent
   ber-`position: relative`, jadi ia menghalangi klik tombol Ubah/Hapus motif. Fix: `relative` pada label.
3. **Perubahan motif tidak pernah terkirim.** `router.post(url, { ...buildFormData(), _method: 'PUT' })` —
   FormData tidak bisa di-spread (`Object.entries(FormData)` kosong), jadi requestnya tanpa nama/harga/stok
   dan gagal validasi. Fix: `fd.append('_method', 'PUT')`.
4. **Daftar motif di dalam modal tidak ter-update.** State `editingProduct` menyimpan objek produk, jadi
   setelah motif disimpan & list di-refresh, objek itu basi. Fix: simpan `editingProductId` dan turunkan
   objeknya dari `list` saat render.

## P6 — Katalog & detail pembeli ✅ **SELESAI**

Tujuannya: pembeli memilih motif, bukan ukuran. Ini phase yang membuat fitur benar-benar terasa.

- Data: `HomeController::show()` memakai `findWithRelations` → tambahkan relasi `variants` (hanya yang
  aktif) supaya tidak eager-load motif nonaktif ke publik.
- `resources/js/Components/ModernDetail.jsx`: jika `product.has_variants`, ganti blok "Pilih Ukuran"
  dengan tombol motif (nama + thumbnail bila ada). Motif terpilih menampilkan foto, harga, dan stok
  motif itu; tombol "Masukkan Keranjang" mengirim `product_variant_id` (bukan `size`) dan hanya aktif
  bila motif valid + stok > 0.
- `ProductCards.jsx` / `ProductCollection.jsx` / `Welcome.jsx` / `Search.jsx`: harga "mulai dari Rp…"
  bila motif punya harga berbeda; motif **tidak** menjadi produk terpisah (wishlist & pencarian tetap
  di produk induk).
-Ukuran kain `2 m × 1,1 m` tampil sebagai spesifikasi di detail produk, bukan sebagai pilihan.
- **Verifikasi:** `npm run build`, smoke katalog, dan tidak ada `LazyLoadingViolationException` —
  `Model::preventLazyLoading` aktif di luar production, jadi relasi yang diakses wajib eager-load.

## P7 — Keranjang, pembayaran, invoice, admin order ✅ **SELESAI**

- `Keranjang.jsx`: nama motif + harga satuan dari variasi (`detail.variant?.price ?? product.harga`);
  dua motif dari produk yang sama tampil sebagai dua baris (backend sudah benar, ini cuma tampilan).
- `Payment.jsx`, `Invoice.jsx`, `Pages/admin/Order.jsx`: tampilkan `motif_name` dari snapshot, bukan nama
  katalog — kalau motif diganti namanya, transaksi lama tidak boleh ikut berubah.
- **Verifikasi:** E2E alur motif ujung-ke-ujung (pilih motif → keranjang → checkout → pembayaran).

### Hasil eksekusi P6 & P7 (2026-10-13)

**Verifikasi:** `php artisan test` **219 passed (945 assertions)**, Playwright **11 passed (2,3 menit)**, `npm run build` sukses,
plus smoke manual di browser untuk seluruh alur pembeli.

**P6 — katalog & detail pembeli**
- `ModernDetail.jsx`: produk bermotif menampilkan grid motif (foto motif, atau placeholder lewat `FallbackImage`
  sampai foto dikirim dari toko). Motif stok 0 otomatis nonaktif & bertanda "Habis". Motif terpilih menampilkan
  harga & stok motif itu, dan tombol tambah berubah jadi "Pilih Motif Dulu" (disabled) sampai motif dipilih.
  Kuantitas dibatasi oleh stok motif. Produk tanpa motif tetap memakai blok ukuran — kini plus info **ukuran kain**.
- `ProductCards.jsx`: harga "**Mulai dari Rp…**" bila motif punya harga berbeda, badge jumlah motif, dan tombol
  keranjang diganti ikon palet yang mengarahkan ke detail (motif wajib dipilih dulu). Motif tidak pernah
  menjadi produk terpisah — pencarian/wishlist tetap di produk induk.
- `ProductRepository::getAllPaginated()` kini ikut mengambil `has_variants` & `fabric_size` (terlihat di §Bug).

**P7 — motif di seluruh tampilan pesanan**
- `lib/cart.js`: baris keranjang membawa motif, harga dari variasi, dan foto motif bila ada.
- `Keranjang.jsx`: label "Motif: …" untuk produk bermotif (tetap "Ukuran: …" untuk produk lama).
- `Checkout.jsx`, `Payment.jsx` (tagihan pending + riwayat + kartu "Pesanan Belum Dibayar"), `Invoice.jsx`,
  `admin/Order.jsx`: menampilkan **snapshot `motif_name`**, bukan nama motif katalog — mengganti nama motif
  tidak mengubah transaksi lama.
- `HomeController::checkout()` mengirim `motif_name`, `is_variant`, dan harga dari variasi supaya ringkasan
  di halaman checkout sama dengan yang ditagihkan.

**Test baru:** `tests/Feature/ProductVariantBuyerUITest.php` (5 test) + `tests/e2e/variant.spec.ts`
(2 skenario: pilih motif → keranjang, dan motif → checkout → order bermotif).

**Tiga bug yang ketahuan saat pengerjaan P6/P7 (semua ditemukan lewat probe/assertion, bukan dari baca kode):**
1. **Katalog diam-diam kehilangan informasi motif.** `ProductRepository::getAllPaginated()` memakai
   `select([...])` eksplisit yang **tidak** menyertakan `has_variants`/`fabric_size`, jadi setiap produk terlihat
   sebagai produk tanpa motif di seluruh katalog publik. Fix: kedua kolom ikut di-select.
2. **Cache katalog mencemari E2E.** `php artisan e2e:reset` mengosongkan tabel tapi tidak membersihkan cache
   (katalog bertahan 300 detik dan tidak relating ke baris DB), sehingga spec sempat melihat produk & motif
   dari run sebelumnya. Fix: `e2e:reset` sekarang juga `cache:clear`.
3. **`HomeController::checkout()` ternyata belum pernah ikut ter-patch** dari sesi backend — ringkasan checkout
   masih memakai harga produk, bukan harga motif. Ketahuan karena test assert `motif_name` tidak ada. Fix:
   baris keranjang bermotif memakai harga variasi + `motif_name`.

## P8 — Pengujian & penguatan ✅ **SELESAI**

- Feature test tersisa dari daftar Tahap 5: admin ubah motif tidak mengubah harga order lama saat
  produk dihapus, stok motif habis di satu motif tidak memblokir motif lain, foto motif dihapus ikut
  menghapus file, `updateQuantity` menolak melebihi stok motif.
- **Uji race konkuren stok variasi**: 2 request `POST /checkout` paralil untuk motif yang sama
  (pola yang pernah dipakai di A.5 untuk `products_sizes`) — pastikan stok tidak negatif, tidak ada
  500 mentah, dan pesan "Stok tidak cukup untuk motif …" tetap ramah.
- 2 skenario Playwright baru: pilih motif → keranjang → checkout; admin tambah motif → motif muncul
  di halaman produk pembeli.
- **Verifikasi:** `php artisan test` + `npx playwright test` + `npm run build` hijau.

### Hasil eksekusi P8 (2026-10-13)

**Verifikasi lengkap (semua dijalankan ulang, bukan taken dari catatan sebelumnya):**

| Yang dijalankan | Hasil |
|---|---|
| `php artisan test` | **228 passed (1002 assertions)** |
| `npx playwright test` | **12 passed (1,8 menit)** |
| `npm run build` | sukses |
| `bash scripts/race-variant-stock.sh` | ✅ stok aman, satu order, tidak ada 500 |

**Test backend baru — `tests/Feature/ProductVariantStockTest.php` (9 test)** menutup sisa daftar Tahap 5:
motif habis tidak memblokir motif lain · `updateQuantity` menolak melebihi stok motif · hapus motif ikut menghapus
berkas fotonya · histori order tetap punya snapshot motif meski produk dihapus · motif yang dibeli tetap punya
snapshot nama & harga meski nama motif diganti & dinonaktifkan · harga motif dipakai walaupun harga produk
berbeda · motif nonaktif tersembunyi dari katalog tapi tetap terlihat admin · produk bermotif tanpa motif sama
sekali tidak bisa dibeli · produk bermotif tidak bisa dibeli lewat ukuran lama yang tidak punya motifnya (dan stok
`products_sizes` yang bernilai 50 sama sekali tidak tersentuh).

**Skenario Playwright baru — `tests/e2e/variant.spec.ts` skenario 9:** admin menambah motif "Lereng" →
motif langsung muncul di katalog (jumlah motif naik) → bisa dipilih pembeli dengan harga yang benar.

### 🔬 Uji race stok motif (bukti nyata, bukan test Pest)

Pest berjalan berurutan di dalam satu transaksi, jadi **tidak pernah benar-benar menguji kompetisi**.
Karena itu race diuji lewat HTTP sungguhan: `scripts/race-variant-stock.sh`.

Cara kerjanya: menyiapkan motif dengan stok 1, dua user asli (login sungguhan + cookie jar + token CSRF,
sesi diverifikasi lewat `/cart` supaya request yang "kalah" tidak diam-diam tidak terautentikasi), lalu dua
`POST /checkout` ditembak **paralel** ke motif yang sama.

Hasil yang tercatat (run terakhir):

```
==> Menyiapkan data uji (motif dengan stok 1, dua user dengan keranjang)
    produk #316 motif 'Parang' (#110) stok = 1
==> Menembak 2 checkout paralel
    A: HTTP 302 -> http://localhost:8000/pembayaran
    B: HTTP 302 -> http://localhost:8000/cart
==> Hasil
    stok motif sekarang : 0 (harus 0, tidak boleh negatif)
    order draft dibuat  : 1 (harus 1)
    redirect ke pembayaran: 1 (harus 1)
    pesan untuk yang kalah: Stok tidak cukup untuk Batik E2E Motif Parang motif Parang
    ✓ stok aman, satu order, tidak ada 500
```

Yang diverifikasi: stok tidak pernah negatif (0, bukan -1), hanya satu order terbentuk, pemenang diarahkan ke
`/pembayaran`, yang kalah mendapat **302 (bukan 500 mentah)** dengan pesan ramah yang menyebut nama motif,
dan keranjang yang kalah tetap utuh (transaksi rollback). Script mengembalikan exit code non-nol kalau salah
satu syarat dilanggar, jadi bisa dipakai sebagai gerbang CI nanti.

### ⚠️ Temuan terbuka dari P8

1. ~~**🔴 Menghapus produk menghilangkan rincian item order-nya.**~~ — **SUDAH DIPERBAIKI** di § Hasil audit #2
   (produk sekarang diarsipkan dengan soft delete, rincian order & invoice tetap utuh, dan produk bisa
   dipulihkan).
2. **🟠 Foto motif belum ada sama sekali** untuk produk bermotif hasil migrasi (motifnya masih bernama
   S/M/L). Perlu daftar nama motif & foto dari toko — lihat § Pertanyaan untuk toko.

## P9 — Seed konten & smoke (menunggu)

- `database/seeders/DatabaseSeeder.php` (atau seeder terpisah): produk template
  `Batik Cap [jumlah warna] Warna [warna]` dengan 2–3 motif placeholder (nama saja, tanpa foto) supaya
  katalog tidak kosong saat UI motif aktif.
- Smoke manual ujung-ke-ujung dengan akun admin + user sampai invoice, memakai data contoh.
- **Verifikasi:** checklist "Kriteria selesai" di atas dicek satu per satu.


---

## Hasil audit 2026-10-13 & perbaikannya

Audit ([docs/audit-update-planning-variasi-motif-batik.md](./audit-update-planning-variasi-motif-batik.md), 6 temuan) menolak aktivasi motif sebelum dua risiko
diselesaikan. Keduanya sudah diperbaiki dan diverifikasi, jadi command migrasi aman dijalankan di produksi
langkah demi langkah.

| # | Temuan audit | Status | Yang diperbaiki |
|---|---|---|---|
| 1 | **Tinggi** — produk bermotif masih bisa dibeli lewat jalur ukuran; `products_sizes` & `product_variants` jadi dua sumber stok | ✅ Diperbaiki | `CartService::add()`/`updateQuantity()` dan `CheckoutService::prepareSizeDetail()` kini: produk `has_variants` **diarahkan** ke motif dengan nama yang sama, dan ditolak kalau motifnya tidak ada. `products_sizes` tidak pernah lagi jadi sumber stok untuk produk bermotif. `ProductService::update()` juga berhenti menyentuh `products_sizes` untuk produk bermotif (sebelumnya `sizes()->delete()` bisa memutus rujukan `order_details.product_size_id`). |
| 2 | **Sedang** — webhook gagal/expire yang datang bersamaan bisa mengembalikan stok dua kali | ✅ Diperbaiki | `PaymentWebhookService::handle()` sekarang mengunci baris payment dengan `lockForUpdate()` **di dalam** transaksi, mengecek status terminal setelah terkunci, dan hanya melakukan transisi sekali (juga untuk status sukses). |
| 3 | **Sedang** — P6 belum merinci eager loading variasi di semua sumber kartu produk | ✅ Diperbaiki (ahead of P6) | `activeVariants` (hanya yang aktif) sudah di-eager-load di `ProductRepository::getAllPaginated()`, `ProductService::findWithRelations()` (default), `SearchController`, dan query produk terkait `HomeController`. Tidak ada lazy-load yang bisa memicu `LazyLoadingViolationException`. |
| 4 | **Sedang** — keputusan migrasi semua produk lama bertentangan dengan pertanyaan toko yang masih terbuka | ✅ Diperbaiki | Command kini minta konfirmasi eksplisit, menerima `--product=<id>` (boleh diulang) untuk membatasi migrasi per produk, dan menjelaskan akibatnya. Pertanyaan toko #6 ditulis ulang menjadi "konfirmasi dulu, sebagian produk mungkin tetap ukuran". |
| 5 | **Rendah** — soft-delete variasi tidak memeriksa `payment_details` padahal komentarnya bilang iya | ✅ Diperbaiki | Relasi `ProductVariant::paymentDetails()` ditambahkan dan ikut dipakai di keputusan soft-delete. |
| 6 | **Rendah** — checklist master Fase C masih menyebut 8/8 padahal 9/9 | ✅ Diperbaiki | `docs/planning-fix-p0-dan-automation-testing-2026-08-22.md` diselaraskan ke 9/9. |

**Catatan audit yang tidak bisa diverifikasi di sana:** audit berjalan tanpa `vendor/`, jadi tidak bisa
menjalankan test. Klaim "197 passed" diverifikasi ulang di sesi ini setelah perbaikan:
`php artisan test` → **209 passed (859 assertions)**, Playwright **9/9**, `npm run build` sukses.

**Test baru — `tests/Feature/ProductVariantAuditFixTest.php` (10 test)** menutup setiap temuan:
produk bermotif tidak bisa memakai stok ukuran (termasuk kasus "stok ukuran 99, stok motif 2"), checkout
baris lama memakai stok motif, baris tanpa motif ditolak dengan pesan jelas, `updateQuantity` divalidasi
terhadap stok motif, produk non-varian tetap memakai `products_sizes`, admin mengedit produk bermotif tidak
memutus histori order, notifikasi gagal berulang hanya mengembalikan stok sekali, notifikasi sukses
berulang tidak mengulang efek, dan motif yang hanya dipakai `payment_details` ikut dinonaktifkan.

**Urutan aktivasi produksi yang sekarang aman:**
```bash
php artisan products:migrate-sizes-to-variants --dry-run          # 1. lihat rencana
php artisan products:migrate-sizes-to-variants --product=<id>     # 2. satu produk, konfirmasi interaktif
php artisan products:migrate-sizes-to-variants --force            # 3. sisanya, setelah dicek
# 4. baru P5/P6 — sampai UI motif aktif, frontend lama tetap jalan karena
#    permintaan `size`-nya diarahkan ke motif dengan nama yang sama.
```



---

## Hasil audit #2 (2026-10-13) & perbaikannya

Audit kedua ([audit-planning-variasi-motif-sampai-p8-2026-10-13.md](./audit-planning-variasi-motif-sampai-p8-2026-10-13.md))
menemukan 5 hal. Semuanya sudah ditangani:

| # | Temuan audit #2 | Status | Yang diperbaiki |
|---|---|---|---|
| 1 | **Tinggi** — hard delete produk menghapus baris rincian order | ✅ Diperbaiki | Produk sekarang memakai **soft delete (arsip)**: migrasi `deleted_at`, trait `SoftDeletes`, dan `ProductService::delete()` tidak lagi menghapus baris. `OrderDetail::product()` & `PaymentDetail::product()` memakai `withTrashed()` sehingga nama produk pada histori tetap terbaca. Berkas gambar/ukuran/motif sengaja disimpan agar produk bisa dipulihkan. Test lama yang mengunci perilaku kehilangan detail **diubah** menjadi menguji perilaku baru. |
| 2 | **Sedang** — admin bisa mematikan `has_variants` saat masih ada variasi | ✅ Diperbaiki | `UpdateProductRequest::after()` menolak `has_variants` kosong bila produk masih punya variasi (aktif maupun nonaktif), dengan pesan yang menjelaskan harus menghapus motif dulu. Dipakai `after()`, bukan rule `prohibited`, karena `prohibited` justru mengizinkan nilai kosong — persis nilai yang harus ditolak. |
| 3 | **Sedang** — status & langkah berikutnya di dokumen bertentangan | ✅ Diperbaiki | Bagian kondisi aplikasi ditandai sebagai *status saat perencanaan*; daftar "Yang tersisa" dipangkas jadi P9; bagian "Langkah berikutnya" ditulis ulang (P9 → konfirmasi toko → Fase D). |
| 4 | **Sedang** — verifikasi tidak bisa direproduksi (build gagal `react-is`, tanpa `vendor/`) | ✅ Diperbaiki | Penyebabnya ditemukan: `recharts@3.10.1` mendeklarasikan **`react-is` sebagai peerDependency**, sehingga `npm install` biasa tidak memasangnya dan build gagal saat Rollup me-resolve `recharts`. Fix: `react-is` dideklarasikan eksplisit di devDependencies (terverifikasi: `rm -rf node_modules/react-is && npm install` → build sukses). Untuk bagian PHP: `composer install` di host memang **gagal** karena dependency dev Pest (`brianium/paratest`) mensyaratkan PHP ~8.2–8.4 sementara host memakai PHP 8.5.7 — jadi verifikasi backend memang harus jalan di container `php:8.2`. Semua langkah sekarang dibundel di `bash scripts/verify.sh`. |
| 5 | **Rendah** — link audit sebelumnya salah nama | ✅ Diperbaiki | Link sekarang menuju `audit-update-planning-variasi-motif-batik.md`. |

### Perilaku arsip produk (kebijakan baru)

- `DELETE /admin/products/{id}` dan bulk delete sekarang berarti **arsip**: produk hilang dari katalog publik,
  dari daftar admin aktif, dan tidak bisa dibeli lagi, tapi barisnya tetap ada sehingga `order_details` dan
  `payment_details` tidak ikut hilang.
- Produk terarsip yang masih ada di keranjang **ditolak saat checkout** dengan pesan "Produk ini sudah tidak
  tersedia" (`CheckoutService::guardArchivedProduct()`).
- Halaman **Arsip Produk** (`/admin/products-archived`) menampilkan produk terarsip beserta tombol **Pulihkan**
  (`POST /admin/products/{id}/restore`).
- Test baru `tests/Feature/Admin/ProductModeAndArchiveTest.php` (5 test) mengunci seluruh perilaku ini:
  mode motif tidak bisa dimatikan selama ada variasi, produk bermotif tetap bisa diperbarui, arsip hilang dari
  katalog tapi invoice tetap terbaca, dan produk bisa dipulihkan.

### Verifikasi setelah perbaikan (semua lewat `bash scripts/verify.sh`)

| Yang dijalankan | Hasil |
|---|---|
| `php artisan test` | **234 passed (1068 assertions)** |
| `npm run build` (container) | sukses |
| `npx playwright test` | **12 passed (1,6 menit)** |
| `bash scripts/race-variant-stock.sh` | ✅ stok aman, satu order, tidak ada 500 |

Tambahan: `npm run build` juga sudah dicoba langsung di host setelah `npm install` + `react-is` eksplisit —
berhasil, jadi temuan audit #4 soal build tidak bisa direproduksi sudah tertutup dari sisi dependency.
---

## Langkah berikutnya (per 2026-10-13, setelah P0–P8 selesai)

1. **P9** — masukkan data produk/motif/cover yang disetujui toko (jangan mengarang nama motif atau stok),
   lalu smoke manual sebagai admin & pembeli sampai invoice.
2. **Dua keputusan yang masih terbuka** (lihat § Temuan terbuka P8 & audit 2026-10-13):
   - Kebijakan penghapusan produk — sekarang sudah **arsip (soft delete)** + bisa dipulihkan, tapi masih
     perlu konfirmasi toko apakah kebijakan ini sudah sesuai.
   - Foto motif — butuh daftar nama motif & foto asli dari toko.
3. Setelah P9: **Fase D (CI)** belum dikerjakan — `php artisan test`, `npm run build`, dan
   `scripts/race-variant-stock.sh` sudah siap dijadikan gerbang CI.

---

## Di luar cakupan rencana ini

- Implementasi Playwright dan CI yang masih tercatat sebagai Fase C/D pada planning automation sebelumnya. Keduanya tetap perlu direncanakan/dikerjakan terpisah, kecuali diputuskan menjadi bagian dari jadwal implementasi fitur ini.
