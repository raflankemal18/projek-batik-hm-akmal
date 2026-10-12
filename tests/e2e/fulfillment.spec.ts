import { expect, test } from '@playwright/test';
import { resetDb } from './support/db';
import { E2E_ADMIN, E2E_USER, login, logout } from './support/helpers';
import { TID } from './support/selectors';

const RESI = 'JNE123456789';

test.describe('Skenario 5 — Admin ubah status pengiriman, user melihat perubahannya', () => {
    test.beforeEach(() => {
        resetDb();
    });

    test('fulfillment shipped + resi dari admin tampil di halaman pembayaran user', async ({ page }) => {
        await login(page, E2E_ADMIN);
        await page.goto('/admin/orders');

        await page.getByTestId(TID.fulfillmentSelect).first().selectOption('shipped');
        await page.getByTestId(TID.fulfillmentCourier).first().fill('JNE');
        await page.getByTestId(TID.fulfillmentResi).first().fill(RESI);
        await page.getByRole('button', { name: /Update Status/i }).first().click();
        await expect(page.getByText(/Status pengiriman diperbarui/i)).toBeVisible();

        await logout(page, E2E_ADMIN);
        await login(page, E2E_USER);

        await page.goto('/pembayaran');
        await expect(page.getByText('LUNAS / DIPROSES')).toBeVisible();
        await expect(page.getByText('Dikirim')).toBeVisible();
        await expect(page.getByText(`Resi: ${RESI}`)).toBeVisible();
        await expect(page.getByText('Kurir: JNE')).toBeVisible();
    });
});