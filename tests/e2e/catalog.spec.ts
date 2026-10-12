import { expect, test } from '@playwright/test';
import { resetDb } from './support/db';
import { E2E_ADMIN, E2E_USER, login, logout, SEARCH_KEYWORD } from './support/helpers';
import { TID } from './support/selectors';

// AddProductRequest membatasi nama produk maksimal 25 karakter.
const PRODUK_BARU = 'Batik Premium E2E Baru';

test.describe('Skenario 6 & 7 — Admin membuat produk, user melihat lewat filter & pencarian', () => {
    test.beforeEach(() => {
        resetDb();
    });

    test('skenario 6: produk baru dari admin langsung muncul di filter kategori user', async ({ page }) => {
        await login(page, E2E_ADMIN);
        await page.goto('/admin/products');

        await page.getByTestId(TID.adminProductCreate).click();
        await page.getByTestId(TID.adminProductName).fill(PRODUK_BARU);
        await page.getByTestId(TID.adminProductPrice).fill('499000');
        await page.getByTestId(TID.adminProductDescription).fill('Produk yang dibuat lewat form admin saat E2E.');
        await page.getByTestId(TID.adminProductCategory).selectOption('wanita');
        await page.getByTestId(TID.adminProductSize).fill('XL');
        await page.getByTestId(TID.adminProductStock).fill('4');
        await page.getByTestId(TID.adminProductSubmit).click();

        // Server mengarahkan admin ke dashboard setelah produk tersimpan
        // (ProductController::store -> redirect route admin.dashboard), jadi
        // assertion dilakukan di halaman daftar produk, bukan di toast.
        await page.waitForURL(/\/admin\/dashboard/);
        await page.goto('/admin/products');
        await expect(
            page.getByTestId(TID.adminProductRow).filter({ hasText: PRODUK_BARU }),
        ).toHaveCount(1);

        await logout(page, E2E_ADMIN);
        await login(page, E2E_USER);

        await page.goto('/products');
        await page.getByTestId(TID.categoryTab).filter({ hasText: 'Batik Wanita' }).click();
        await expect(page).toHaveURL(/category=wanita/);
        await expect(page.getByText(PRODUK_BARU).first()).toBeVisible();
    });

    test('skenario 7: pencarian global menampilkan hasil yang relevan', async ({ page }) => {
        await login(page, E2E_USER);

        await page.getByTestId(TID.headerSearch).fill(SEARCH_KEYWORD);
        await page.getByTestId(TID.headerSearch).press('Enter');

        await page.waitForURL(/\/search\?q=/);
        await expect(page.getByText(`Menampilkan hasil untuk: "${SEARCH_KEYWORD}"`)).toBeVisible();
        await expect(page.getByText('1 produk ditemukan')).toBeVisible();
        await expect(page.getByText(/Motifwali/).first()).toBeVisible();
    });
});