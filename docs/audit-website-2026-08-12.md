# Audit Website — Marketplace Batik HM Akmal

**Tanggal:** 2026-08-12
**Scope:** Seluruh aplikasi — frontend (user & admin), backend, dependency, fitur yang ada vs yang belum ada.
**Metode:** Baca kode sumber langsung (bukan asumsi) — semua controller, service, halaman React, dan `package.json` di-cross-check dengan `grep` untuk memastikan klaim "dipakai/tidak dipakai" akurat.

Dokumen ini dibagi 4 bagian:
1. Pemborosan/inefisiensi yang sudah ada
2. Fitur yang sudah ada tapi setengah jadi / ada bug
3. Fitur yang belum ada sama sekali (user & admin)
4. Rekomendasi prioritas

---

## 1. Pemborosan / Inefisiensi

### 1.1 Dependency frontend — bloat SANGAT signifikan (temuan terbesar)

`package.json` install **5 UI framework/library besar sekaligus** (Chakra UI, NextUI, Mantine, CoreUI, Bootstrap) di atas Tailwind + Radix yang sebenarnya jadi design system utama (`resources/js/Components/ui/*`, dipakai konsisten di banyak halaman). Saya cek pemakaian nyata tiap package via `grep -rl` ke seluruh `resources/js/`:

| Package | File yang pakai | Status |
|---|---|---|
| `@mantine/core`, `@mantine/hooks`, `@mantinex/mantine-logo` | 0 | **Mati total** — tidak ada import sama sekali |
| `postcss-preset-mantine` | 0 (bahkan tidak terdaftar di `postcss.config.js`) | **Mati total** |
| `@coreui/coreui`, `@coreui/react` | 0 | **Mati total** |
| `bootstrap` | 0 | **Mati total** (yang di-import di `app.jsx` adalah file lokal `./bootstrap.js`, bukan package CSS ini) |
| `@inertiajs/inertia-react` (versi lama/deprecated) | 0 | **Mati total** — yang dipakai project adalah `@inertiajs/react` (14 file) |
| `tabler-icons-react` + `@tabler/icons-react` (dua nama utk 1 icon set, versi lama & baru) | 0 keduanya | **Mati total** — icon yang benar-benar dipakai cuma `lucide-react` (27 file) |
| `react-loading-icons` | 0 | **Mati total** |
| `react-rating-stars-component` | 0 | **Mati total** — sinyal fitur rating produk pernah direncanakan tapi tidak pernah dibangun |
| `@nivo/line` (chart library) | 0 | **Mati total** — sinyal grafik analytics admin pernah direncanakan tapi tidak pernah dibangun |
| `@chakra-ui/icons` | 0 | **Mati total** |
| `@chakra-ui/react`, `@emotion/react`, `@emotion/styled` | 1 file (`app.jsx`, cuma untuk `ColorModeProvider`/`ThemeProvider`) | Nyaris tidak dipakai |
| `@nextui-org/react` (+ `/button`, `/system`, `/theme`) | 1 file (`footer.jsx`, cuma untuk komponen `Link`) | Nyaris tidak dipakai — tapi plugin Tailwind-nya (`nextui()`) tetap aktif di `tailwind.config.js`, jadi tetap menambah beban build CSS |

**Dampak nyata:** ini bukan cuma soal "kode rapi" — install 5 UI kit sekaligus berarti bundle JS jauh lebih besar dari yang perlu (tiap kit punya runtime & CSS-in-JS overhead sendiri), waktu `npm install` & `npm run build` lebih lama, dan risiko konflik style (Tailwind vs Chakra vs NextUI bisa saling override CSS reset satu sama lain) makin tinggi tiap kali ada developer baru asal pilih komponen dari kit yang salah.

**Rekomendasi:** hapus 11 package yang 100% mati (`@mantine/*`, `postcss-preset-mantine`, `@coreui/*`, `bootstrap`, `@inertiajs/inertia-react`, `tabler-icons-react`, `@tabler/icons-react`, `react-loading-icons`). Untuk Chakra & NextUI yang cuma dipakai di 1 file masing-masing: pindahkan `ColorModeProvider`/`ThemeProvider` dan `Link` footer ke komponen native Tailwind/Radix, lalu hapus juga.

### 1.2 Backend — query & caching sebenarnya sudah cukup baik

Untuk adil: bagian backend (hasil refactor D1-D3) sudah cukup efisien, bukan area boros:
- `ProductRepository::getAllPaginated` eager-load `sizes` + `images` (limit 1) dengan `select()` kolom terbatas — tidak N+1
- `ProductService::getAllPaginated` di-cache 5 menit untuk view default (`Cache::remember`)
- `getTopSelling` pakai SQL aggregate (`SUM`+`groupBy` di level DB), bukan `Collection::groupBy` yang narik semua baris ke PHP
- `CartService::getCartWithProducts` eager-load `details.product.images` — tidak N+1

Tidak ada perubahan yang saya rekomendasikan di sisi ini.

### 1.3 Data palsu di Dashboard Admin

`resources/js/Pages/admin/Dashboard.jsx` menampilkan angka **hardcoded, bukan dari data asli**:
- `desc="+20.1%"`, `desc="+12 Order"`, `desc="+5 User"` di kartu statistik — string tetap, tidak dihitung dari data real
- Panel "Target Bulan Ini" menampilkan `2%` progress dan "Rp 200.000.000 lagi menuju target" — angka ngarang, tidak terhubung ke perhitungan apapun

Ini berbahaya kalau tidak disadari: admin bisa salah ambil keputusan bisnis karena mengira itu data pertumbuhan asli.

### 1.4 Kode mubazir kecil
- `ProductService::delete()` manggil `$this->imageService->delete(null)` sebelum loop hapus gambar satu-satu — `ImageService::delete()` cuma no-op kalau path `null`, jadi baris ini tidak melakukan apapun. Sisa kode dari draft sebelumnya yang tidak dibersihkan.

---

## 2. Fitur yang sudah ada tapi setengah jadi / ada bug

| # | Temuan | Detail |
|---|---|---|
| 1 | **Filter kategori produk tidak bisa diisi admin** | `HomeController::products` & `ProductRepository` sudah support filter `?category=`, tapi form tambah/edit produk admin (`admin/form/Form.jsx`) **tidak punya field kategori sama sekali**. Artinya kolom `products.category` kemungkinan besar selalu `null` untuk semua produk — fitur filter kategori di frontend user secara praktis tidak akan pernah menghasilkan apa-apa. |
| 2 | **Halaman riwayat pesanan (`Payment.jsx`) tidak ada pagination UI** | `PaymentController::index()` sudah `->paginate(10)` di backend, tapi `Payment.jsx` cuma ambil `payments?.data` dan render list — tidak ada tombol/link ke halaman berikutnya. User dengan >10 transaksi tidak bisa lihat riwayat lama. |
| 3 | **`Invoice.jsx` yatim (orphaned)** | File masih ada dan sempat di-edit saat refactor, tapi tidak ada route (`routes/web.php`) maupun link manapun yang mengarah ke sana. User tidak bisa lihat/cetak invoice per transaksi dari mana pun di UI. |
| 4 | **`user/Dashboard.jsx` kosong total** | Isinya cuma: `return <div>ini halaman user dashboard</div>;`. Route `/user/dashboard` ada dan bisa diakses, tapi tidak menampilkan apa-apa yang berguna (ringkasan pesanan, produk favorit, dll). |
| 5 | **Status order di admin cuma bisa dilihat, tidak bisa diubah** | `admin/Order.jsx` menampilkan `StatusBadge` (read-only). Status yang ada di sistem (`draft/pending_payment/paid/cancelled`) murni berdasar hasil pembayaran Midtrans — tidak ada tahap fulfillment (diproses/dikirim/selesai) dan tidak ada UI bagi admin untuk update status pengiriman. |
| 6 | **Bug kecil di menu mobile Header** | Link "pesanan saya" di menu desktop dibungkus `{isUser && (...)}` (cuma muncul utk role user), tapi versi mobile (`MobileNavLink href="/pembayaran"`) **tidak dibungkus kondisi apapun** — admin/guest yang buka menu mobile akan melihat link ke halaman yang route-nya di-guard `role:user`, berpotensi kena 403. |
| 7 | **Icon keranjang di Header tanpa badge jumlah item** | `<ShoppingBag />` di navbar statis, tidak menunjukkan berapa item ada di keranjang — user harus buka halaman cart dulu untuk tahu. |

---

## 3. Fitur yang belum ada sama sekali

### 3.1 Sisi User

| Fitur | Kenapa penting |
|---|---|
| **Profil user lengkap** | Saat ini cuma bawaan Laravel Breeze (`Profile/Edit.jsx`): ganti nama/email/password/hapus akun. Tidak ada: foto profil, buku alamat (multi-alamat pengiriman tersimpan), nomor telepon default, riwayat aktivitas. |
| **Wishlist / simpan produk** | Tidak ada tabel, tidak ada tombol "simpan" di manapun. |
| **Review & rating produk** | `react-rating-stars-component` sudah ter-install tapi 0% dipakai — fitur ini kelihatan pernah direncanakan. Tidak ada tabel `reviews`, tidak ada UI di `ProductDetail.jsx`. |
| **Search bar di header/navbar global** | Pencarian produk cuma bisa lewat query param di halaman `/products`; tidak ada search box langsung di navbar yang bisa diakses dari halaman manapun. |
| **Filter lanjutan** | Filter yang ada cuma: search teks, kategori (yang belum fungsional — lihat §2.1), sort. Tidak ada filter rentang harga, ukuran tersedia, atau stok tersedia. |
| **Tracking pengiriman / resi** | Tidak ada field nomor resi/kurir di `orders`/`payments`, tidak ada status "dikirim"/"diterima" (lihat §2.5). |
| **Notifikasi** | Tidak ada sistem notifikasi apapun (in-app maupun email) untuk update status pesanan, promo, dsb. Grep ke seluruh `app/` & `resources/js/` untuk kata "notification" cuma nemu bawaan Laravel (verifikasi email), bukan sistem notifikasi produk. |
| **Invoice/cetak struk** | Ada halaman (`Invoice.jsx`) tapi orphan, tidak reachable (lihat §2.3). |
| **Live chat / kontak CS** | Tidak ada. |

### 3.2 Sisi Admin

| Fitur | Kenapa penting |
|---|---|
| **Manajemen kategori produk** | Tidak ada CRUD kategori sama sekali — lihat §2.1, ini blocker langsung untuk fitur filter kategori yang sudah "setengah dibangun". |
| **Update status fulfillment order** | Tidak ada cara mengubah status "diproses/dikirim/selesai" dari admin — lihat §2.5. |
| **Grafik/analytics beneran** | `@nivo/line` ter-install tapi 0% dipakai — dashboard cuma tabel "Produk Paling Laris" + angka statis palsu (§1.3). Tidak ada grafik tren penjualan harian/mingguan/bulanan. |
| **Export laporan (CSV/Excel/PDF)** | Tidak ada fitur export data order/produk/user untuk laporan. |
| **Notifikasi order baru masuk** | Admin harus buka dashboard manual untuk tahu ada order baru — tidak ada badge/alert real-time. |
| **Bulk actions** | Tidak ada checkbox pilih-banyak untuk hapus/update massal di `admin/User.jsx` atau `admin/Order.jsx`. |
| **Manajemen banner/promo homepage** | `Welcome.jsx`/`ModernHero.jsx` isinya statis di kode — admin tidak bisa ganti banner/promo tanpa deploy ulang. |
| **Role & permission granular** | Sistem role cuma biner `admin`/`user` (`RoleMiddleware`). Tidak ada peran menengah (misal: staff gudang yang cuma boleh update status kirim, tanpa akses hapus produk/user). |
| **Log aktivitas admin** | Sempat direncanakan di spec D4 (`docs/superpowers/specs/2026-08-12-activity-logging-plan.md`) tapi **belum dieksekusi** — jadi saat ini tidak ada jejak audit siapa mengubah apa. |

---

## 4. Rekomendasi Prioritas

| Prioritas | Item | Alasan |
|---|---|---|
| 🔴 Tinggi | Hapus 11 dependency mati (§1.1) | Efek langsung ke ukuran bundle & waktu build, risiko rendah (tidak dipakai sama sekali) |
| 🔴 Tinggi | Ganti angka palsu di admin Dashboard jadi data asli / hapus dulu sampai datanya siap (§1.3) | Bisa menyesatkan keputusan bisnis |
| 🔴 Tinggi | Tambah field kategori di form admin produk (§2.1) | Fitur filter yang sudah setengah jadi jadi langsung berfungsi |
| 🟡 Sedang | Tambah pagination UI di `Payment.jsx`, sambungkan/hapus `Invoice.jsx` (§2.2, §2.3) | User-facing, tapi tidak blocking transaksi |
| 🟡 Sedang | Isi `user/Dashboard.jsx` dengan ringkasan pesanan asli (§2.4) | Halaman ada tapi percuma kalau kosong |
| 🟡 Sedang | Status fulfillment order + UI admin untuk ubah status (§2.5, §3.2) | Kebutuhan operasional toko fisik |
| 🟡 Sedang | Fix bug link mobile nav (§2.6) | Kecil tapi bisa bikin admin/guest kena 403 di HP |
| 🟢 Rendah | Wishlist, review & rating, search bar global, notifikasi, live chat (§3.1) | Nice-to-have, tidak menghambat transaksi inti |
| 🟢 Rendah | Bulk actions, export laporan, manajemen banner, role granular (§3.2) | Nice-to-have untuk skala operasional lebih besar |
| ⏳ Belum jalan | D4 — Activity logging (`docs/superpowers/specs/2026-08-12-activity-logging-plan.md`) | Spec sudah siap dieksekusi, tinggal jalanin |

---

## Catatan metodologi

Semua klaim "dipakai" / "tidak dipakai" di atas diverifikasi lewat `grep -rl` ke seluruh `resources/js/` dan `app/`, bukan tebakan dari nama file. Semua temuan fitur backend (query, cache, service) dibaca langsung dari kode `app/Services/`, `app/Repositories/`, dan Controller terkait per 2026-08-12, setelah D1-D3 (backend refactor, frontend refactor, checkout/Midtrans) selesai dieksekusi dan lolos test suite (49/49 test, lihat riwayat audit sebelumnya).
