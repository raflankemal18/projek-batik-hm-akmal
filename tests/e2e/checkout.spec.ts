import { expect, test } from '@playwright/test';
import { resetDb } from './support/db';
import { E2E_USER, login, LOW_STOCK_PRODUCT, WANITA_PRODUCT } from './support/helpers';
import { TID } from './support/selectors';

test.describe('Skenario 2 & 3 — Browse, keranjang, checkout, dan kontrak error stok', () => {
    test.beforeEach(() => {
        resetDb();
    });

    test('skenario 2: browse + filter kategori + keranjang + checkout → tagihan pending di /pembayaran', async ({ page }) => {
        await login(page, E2E_USER);

        // Hapus dulu isi keranjang dari fixture (skenario 3) agar alur ini bersih.
        await page.goto('/cart');
        const removeButtons = page.getByRole('button', { name: /Hapus/i });
        for (let i = (await removeButtons.count()) - 1; i >= 0; i -= 1) {
            await removeButtons.nth(i).first().click();
            await page.getByRole('button', { name: /Ya, Hapus/i }).click();
            await page.waitForTimeout(300);
        }

        // Browse + filter kategori (request Inertia ke server).
        await page.goto('/products');
        await page.getByTestId(TID.categoryTab).filter({ hasText: 'Batik Wanita' }).click();
        await expect(page).toHaveURL(/category=wanita/);
        await expect(page.getByText(WANITA_PRODUCT).first()).toBeVisible();

        // Detail produk → pilih size → masukkan keranjang.
        await page.getByText(WANITA_PRODUCT).first().click();
        await page.waitForURL(/\/products\/\d+/);
        await page.getByTestId(TID.sizeOption).click();
        await page.getByTestId(TID.addToCart).click();
        await expect(page.getByText(/berhasil ditambahkan ke keranjang/i)).toBeVisible();

        // Keranjang → checkout.
        await page.goto('/cart');
        await expect(page.getByText(WANITA_PRODUCT).first()).toBeVisible();
        await page.getByTestId(TID.cartCheckout).click();
        await page.waitForURL(/\/co$/);

        // Isi form pengiriman.
        await page.getByTestId(TID.checkoutPhone).fill('081234567890');
        await page.getByTestId(TID.checkoutAddress).fill('Jl. E2E No. 9, Bandung');
        await page.getByTestId(TID.checkoutSubmit).click();

        await page.waitForURL(/\/pembayaran/);
        await expect(page.getByRole('heading', { name: /Pesanan Saya/i })).toBeVisible();

        // Draft order harus offer jalur pembayaran: tanpa kartu "Pesanan Belum
        // Dibayar" ini, user tidak punya tombol apa pun untuk membayar karena
        // POST /payment hanya dipanggil dari halaman ini.
        await expect(page.getByText('Pesanan Belum Dibayar')).toBeVisible();
        await expect(page.getByTestId(TID.startPayment)).toBeVisible();
    });

    test('skenario 2b: tombol initiate payment benar-benar memanggil POST /payment', async ({ page }) => {
        await login(page, E2E_USER);

        await page.goto('/cart');
        const removeButtons = page.getByRole('button', { name: /Hapus/i });
        for (let i = (await removeButtons.count()) - 1; i >= 0; i -= 1) {
            await removeButtons.nth(i).first().click();
            await page.getByRole('button', { name: /Ya, Hapus/i }).click();
            await page.waitForTimeout(300);
        }

        await page.goto('/products');
        await page.getByText(WANITA_PRODUCT).first().click();
        await page.waitForURL(/\/products\/\d+/);
        await page.getByTestId(TID.sizeOption).click();
        await page.getByTestId(TID.addToCart).click();
        await expect(page.getByText(/berhasil ditambahkan ke keranjang/i)).toBeVisible();
        await page.goto('/cart');
        await expect(page.getByText(WANITA_PRODUCT).first()).toBeVisible();
        await page.getByTestId(TID.cartCheckout).click();
        await page.waitForURL(/\/co$/);
        await page.getByTestId(TID.checkoutPhone).fill('081234567890');
        await page.getByTestId(TID.checkoutAddress).fill('Jl. E2E No. 9, Bandung');
        await page.getByTestId(TID.checkoutSubmit).click();
        await page.waitForURL(/\/pembayaran/);

        await expect(page.getByTestId(TID.unpaidOrder)).toBeVisible();
        await expect(page.getByText(WANITA_PRODUCT).first()).toBeVisible();

        // POST /payment memanggil API Midtrans sungguhan, jadi request-nya
        // di-intercept di level browser: yang diverifikasi adalah wiring UI
        // (tombol -> POST /payment), bukan integrasi Midtrans-nya. Perilaku
        // server (payment, payment_details, gagal tanpa payment yatim) diuji
        // di tests/Feature/PaymentInitiationTest.php.
        const paymentRequest = page.waitForRequest(
            (req) => req.url().endsWith('/payment') && req.method() === 'POST',
        );
        await page.route('**/payment', (route) =>
            route.fulfill({ status: 303, headers: { Location: '/pembayaran' }, body: '' }),
        );
        await page.getByTestId(TID.startPayment).click();

        expect(await paymentRequest).toBeTruthy();
    });

    test('skenario 3: checkout dengan stok habis menampilkan pesan dari kontrak errors.stock', async ({ page }) => {
        await login(page, E2E_USER);

        // Keranjang dari fixture: 5 pcs, sedangkan stok size itu cuma 1.
        await page.goto('/co');
        await expect(page.getByText(LOW_STOCK_PRODUCT).first()).toBeVisible();

        await page.getByTestId(TID.checkoutPhone).fill('081234567890');
        await page.getByTestId(TID.checkoutAddress).fill('Jl. E2E No. 9, Bandung');
        await page.getByTestId(TID.checkoutSubmit).click();

        // Pesan harus berasal dari errors.stock (kontrak D3/A.2) — kalau frontend
        // hanya membaca errors.message, yang tampil cuma "Gagal memproses checkout."
        await expect(page.getByText(/Stok tidak cukup untuk/i).first()).toBeVisible();

        // Tidak boleh pindah halaman dan tidak boleh order terbentuk diam-diam.
        await expect(page).toHaveURL(/\/co$/);
        await expect(page.getByText(/Pesanan berhasil dibuat/i)).toHaveCount(0);
    });
});