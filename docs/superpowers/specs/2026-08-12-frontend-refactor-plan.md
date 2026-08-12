# Frontend Refactor Plan

**Kode Dokumen:** D2
**Tanggal:** 2026-08-12
**Status:** Approved
**Dependency:** F2/F3 butuh route `/checkout` & `/payment` dari D1 sudah stabil sebelum di-test end-to-end (boleh mulai coding paralel dengan D1, tapi verifikasi akhir setelah D1 selesai). Lihat [00-index.md](./00-index.md).

## Tujuan

Amankan FE: hilangkan kredensial di localStorage/JWT, unifikasi akses HTTP, rapikan struktur, bersihkan halaman mati.

## F1. HTTP Layer Baru

Buat `resources/js/lib/http.js`:

```javascript
import axios from 'axios';

const http = axios.create({
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
  withCredentials: true,
});

// Otomatis kirim XSRF-TOKEN dari cookie
http.interceptors.request.use((config) => {
  const token = document.cookie
    .split('; ')
    .find(row => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];
  if (token) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(token);
  }
  return config;
});

// Handle 401 globally
http.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default http;
```

### Hapus dari bootstrap.js

Hapus baris 11-21 (JWT interceptor):

```javascript
// SEBELUM (HAPUS):
window.axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
}, ...);
```

Ganti seluruh bootstrap.js jadi:

```javascript
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

## F1b. WelcomeLayout.jsx & Header.jsx (sumber token — wajib, sering kelewatan)

`bootstrap.js` dan `Checkout.jsx` cuma *konsumen* token. Token-nya sendiri **ditulis** di `resources/js/Layouts/WelcomeLayout.jsx:27`:

```javascript
// HAPUS baris ini dan logic di sekitarnya (baris ~20-30) yang membaca cookie
// lalu localStorage.setItem("auth_token", tokenFromCookie):
localStorage.setItem("auth_token", tokenFromCookie);
```

Dan dibersihkan saat logout di `resources/js/Components/Header.jsx:22`:

```javascript
// HAPUS:
localStorage.removeItem("auth_token");
```

Tanpa dua perubahan ini, checklist "Tidak ada `localStorage`/`auth_token` di seluruh `resources/js/`" di bagian bawah dokumen ini **tidak akan pernah tercapai** walau F1+F2 sudah dikerjakan — `WelcomeLayout.jsx` akan terus menulis token baru ke localStorage setiap kali komponennya mount.

## F2. Checkout.jsx

### Hapus JWT + localStorage

Hapus blok ini (baris 43-60):

```javascript
// HAPUS:
const token = localStorage.getItem('auth_token');
if (!token) {
    toast.error("Token hilang...");
    router.visit('/login');
    return;
}
try {
    const response = await axios.post('/api/payment/process', {
        ...values, totalHarga, orders,
    }, {
        headers: { Authorization: `Bearer ${token}` }
    });
    // ...
```

Ganti jadi Inertia router POST (session + CSRF otomatis):

```javascript
const handleSubmit = (e) => {
    e.preventDefault();
    setLoading(true);

    if (!values.alamat || !values.phone) {
        toast.error("Mohon lengkapi alamat dan nomor telepon.");
        setLoading(false);
        return;
    }

    router.post('/checkout', {
        name: values.name,
        email: values.email,
        phone: values.phone,
        alamat: values.alamat,
        message: values.message,
    }, {
        onStart: () => setLoading(true),
        onFinish: () => setLoading(false),
        onSuccess: () => {
            toast.success("Pesanan berhasil dibuat!");
        },
        onError: (errors) => {
            toast.error("Gagal memproses checkout.", { description: errors.message });
        },
    });
};
```

**Catatan penting:** Tidak perlu kirim `orders`/`totalHarga` dari client. Server hitung ulang dari cart.

### Hapus error handler bocor

Baris 71: `console.error("API Payment Error:", error)` -> hapus atau ganti ke util `logError` yang tidak expose detail ke user.

### Ekstrak InputGroup

Pindahkan `InputGroup` (baris 255-272) ke `resources/js/Components/ui/Field.jsx`. Import dari sana.

## F3. Payment.jsx

### Hapus updateStatus client-driven

Hapus fungsi `updateStatus` (baris 53-60) dan semua panggilan `router.post('/update-payment-status')`.

Status pembayaran hanya dibaca dari server (webhook). `onSuccess/onPending` hanya untuk UI toast + reload.

```javascript
const handlePay = (snapToken, paymentId) => {
    if (window.snap) {
        window.snap.pay(snapToken, {
            onSuccess: () => {
                toast.success("Pembayaran Berhasil!");
                router.reload(); // reload dari server, status dari webhook
            },
            onPending: () => {
                toast.info("Menunggu pembayaran...");
                router.reload();
            },
            onError: () => {
                toast.error("Pembayaran Gagal.");
                router.reload();
            },
            onClose: () => {
                toast.warning("Pop-up ditutup.");
            }
        });
    }
};
```

### Snap URL dari env

Baris 19: `const snapSrcUrl = "https://app.sandbox.midtrans.com/snap/snap.js"` -> ganti:

```javascript
const snapSrcUrl = import.meta.env.VITE_MIDTRANS_SNAP_URL || "https://app.sandbox.midtrans.com/snap/snap.js";
```

Client key dari env sudah benar (publik).

## F4. Image URL Helper

Buat `resources/js/lib/image.js`:

```javascript
export function storageUrl(path) {
    if (!path) return 'https://placehold.co/400';
    return `/storage/${path.replace('public/', '')}`;
}
```

Ganti semua penggunaan di:
- `resources/js/Pages/admin/Products.jsx` baris 80
- `resources/js/Pages/Checkout.jsx` baris 192
- `resources/js/Pages/Keranjang.jsx` baris 157

```javascript
// SEBELUM:
src={product.images?.[0] ? `/storage/${product.images[0].image_path.replace("public/", "")}` : "https://placehold.co/100"}

// SESUDAH:
import { storageUrl } from '@/lib/image';
src={storageUrl(product.images?.[0]?.image_path)}
```

## F5. Bersihkan Halaman Mati & Route

### Hapus file:
- `resources/js/Pages/Test.jsx`
- `resources/js/Pages/Coba.jsx`

### Hapus route di routes/web.php:
- `Route::get('/test', ...)`
- `Route::get('/invoice', ...)` (bila tidak dipakai)

## F6. Keranjang.jsx

Pindahkan flatten logic (baris 21-36) ke `resources/js/lib/cart.js`:

```javascript
export function flattenCartItems(keranjangs) {
    return keranjangs
        .flatMap(k => k.details.map(d => ({
            id: d.id,
            product_id: d.product.id,
            name: d.product.name,
            harga: d.product.harga,
            image_url: d.product.images,
            size: d.size,
            quantity: d.quantity,
        })));
}
```

Tampilkan error dari Inertia `usePage().props.errors` secara konsisten.

## F7. XSS Audit

- Grep `dangerouslySetInnerHTML` -> pastikan tidak ada (React default aman).
- Grep `eval(`, `innerHTML`, `document.write` -> hapus jika ada.
- Pastikan semua render text otomatis escaped oleh React.

## F8. lib/ Struktur Final

```
resources/js/lib/
  http.js      - axios instance + CSRF + 401 handler
  image.js     - storageUrl()
  cart.js      - flattenCartItems()
  format.js    - formatRupiah() (existing, pindah dari helper/)
```

Hapus `resources/js/helper/formatRupiah.js` setelah dipindah.

## Checklist Done

- [x] Tidak ada `localStorage` / `auth_token` di seluruh `resources/js/`
- [x] Tidak ada `/api/payment/process` di seluruh `resources/js/`
- [x] Tidak ada `/update-payment-status` di seluruh `resources/js/`
- [x] Semua URL gambar via `storageUrl()`
- [x] `npm run build` sukses tanpa error
- [x] Tidak ada `console.error` yang bocor detail ke user
- [x] Semua POST via Inertia `router.post()` (session + CSRF)
