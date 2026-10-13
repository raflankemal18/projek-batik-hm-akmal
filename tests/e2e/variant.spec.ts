import { expect, test } from '@playwright/test';
import { resetDb } from './support/db';
import { E2E_ADMIN, E2E_SHOPPER, login, logout } from './support/helpers';
import { TID } from './support/selectors';

const MOTIF_PRODUCT = 'Batik E2E Motif Parang';
const MOTIF = 'Parang';
const MOTIF_HABIS = 'Kawung';

test.describe('Skenario 8 — Pembeli memilih motif kain', () => {
    test.beforeEach(() => {
        resetDb();
    });

    test('motif harus dipilih dulu, motif habis tidak bisa dipilih, dan motif masuk ke keranjang', async ({ page }) => {
        await login(page, E2E_SHOPPER);

        // Kartu katalog: motif bukan produk terpisah, harga ditulis "mulai dari".
        await page.goto('/products');
        await expect(page.getByTestId(TID.productCardLink).filter({ hasText: MOTIF_PRODUCT })).toBeVisible();
        // Spasi di "Rp 150.000" dari Intl.NumberFormat adalah non-breaking space,
        // jadi pola di sini tidak boleh memakai spasi biasa.
        await expect(page.getByTestId(TID.productCardLink).filter({ hasText: MOTIF_PRODUCT })
            .locator('xpath=ancestor::div[contains(@class,"group")]')
            .filter({ hasText: /Mulai dari/ })).toHaveCount(1);
        await expect(page.getByText('2 motif')).toBeVisible();
        await expect(page.getByTestId(TID.productCardMotifLink)).toHaveCount(1);

        await page.getByTestId(TID.productCardLink).filter({ hasText: MOTIF_PRODUCT }).click();
        await page.waitForURL(/\/products\/\d+/);

        await expect(page.getByTestId(TID.variantPicker)).toBeVisible();
        await expect(page.getByTestId(TID.variantOption)).toHaveCount(2);
        await expect(page.getByText('Ukuran kain: 2 m x 1,1 m')).toBeVisible();

        // Tombol tambah belum aktif sebelum motif dipilih.
        await expect(page.getByTestId(TID.addToCart)).toBeDisabled();
        await expect(page.getByTestId(TID.addToCart)).toHaveText(/Pilih Motif Dulu/);

        // Motif dengan stok 0 tidak bisa dipilih.
        await expect(page.getByTestId(TID.variantOption).filter({ hasText: MOTIF_HABIS })).toBeDisabled();

        await page.getByTestId(TID.variantOption).filter({ hasText: MOTIF }).click();

        // Harga motif terpilih tampil, tombol aktif.
        await expect(page.getByTestId(TID.addToCart)).toBeEnabled();
        await expect(page.getByTestId(TID.addToCart)).toHaveText(/Masukkan Keranjang/);
        await expect(page.getByTestId(TID.variantPicker)).toContainText('Rp 150.000');

        await page.getByTestId(TID.addToCart).click();
        await expect(page.getByText(/berhasil ditambahkan ke keranjang/i)).toBeVisible();

        // Motif ikut tersimpan di keranjang. Label "Motif:" dipakai produk
        // bermotif; produk tanpa motif tetap pakai "Ukuran:".
        await page.goto('/cart');
        await expect(page.getByText(MOTIF_PRODUCT)).toBeVisible();
        await expect(page.getByText(`Motif: ${MOTIF}`)).toBeVisible();
    });

    test('checkout memakai harga motif yang dipilih dan menghasilkan order bermotif', async ({ page }) => {
        await login(page, E2E_SHOPPER);

        await page.goto('/products');
        await page.getByTestId(TID.productCardLink).filter({ hasText: MOTIF_PRODUCT }).click();
        await page.waitForURL(/\/products\/\d+/);
        await page.getByTestId(TID.variantOption).filter({ hasText: MOTIF }).click();
        await page.getByTestId(TID.addToCart).click();
        await expect(page.getByText(/berhasil ditambahkan ke keranjang/i)).toBeVisible();

        await page.goto('/cart');
        await page.getByTestId(TID.cartCheckout).click();
        await page.waitForURL(/\/co$/);
        await page.getByTestId(TID.checkoutPhone).fill('081234567890');
        await page.getByTestId(TID.checkoutAddress).fill('Jl. Motif No. 1, Bandung');
        await page.getByTestId(TID.checkoutSubmit).click();
        await page.waitForURL(/\/pembayaran/);

        await expect(page.getByTestId(TID.unpaidOrder)).toBeVisible();
        await expect(page.getByTestId(TID.unpaidOrder)).toContainText(MOTIF);
    });
});

test.describe('Skenario 9 — Admin menambah motif, pembeli langsung melihatnya', () => {
    test.beforeEach(() => {
        resetDb();
    });

    test('motif yang baru ditambahkan admin muncul di katalog & detail pembeli', async ({ page }) => {
        await login(page, E2E_ADMIN);
        await page.goto('/admin/products');

        const row = page.getByTestId(TID.adminProductRow).filter({ hasText: MOTIF_PRODUCT });
        await row.locator('button[title*="Edit" i]').click();

        await page.getByTestId(TID.variantName).fill('Lereng');
        await page.getByTestId(TID.variantPrice).fill('165000');
        await page.getByTestId(TID.variantStock).fill('3');
        await page.getByTestId(TID.variantSave).click();

        await expect(page.getByText('Motif ditambahkan')).toBeVisible();
        await expect(page.getByTestId(TID.variantRow).filter({ hasText: 'Lereng' })).toHaveCount(1);

        // Modal produk masih terbuka (menambah motif tidak menutupnya) — tutup dulu,
        // kalau tidak backdrop-nya menghalangi klik tombol logout.
        await page.keyboard.press('Escape');
        await logout(page, E2E_ADMIN);
        await login(page, E2E_SHOPPER);

        // Motif baru langsung tampil di kartu katalog (jumlah motif naik).
        await page.goto('/products');
        await expect(page.getByText('3 motif')).toBeVisible();

        await page.getByTestId(TID.productCardLink).filter({ hasText: MOTIF_PRODUCT }).click();
        await page.waitForURL(/\/products\/\d+/);
        await expect(page.getByTestId(TID.variantOption).filter({ hasText: 'Lereng' })).toHaveCount(1);

        // Dan bisa dipilih.
        await page.getByTestId(TID.variantOption).filter({ hasText: 'Lereng' }).click();
        await expect(page.getByTestId(TID.variantPicker)).toContainText('Rp 165.000');
        await expect(page.getByTestId(TID.addToCart)).toBeEnabled();
    });
});
