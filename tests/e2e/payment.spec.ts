import { expect, test } from '@playwright/test';
import { resetDb } from './support/db';
import { E2E_PAYER, login, midtransSignature } from './support/helpers';

const SERVER_KEY = process.env.E2E_MIDTRANS_SERVER_KEY || 'SB-Mid-server-HU8U4TVyZQ-JlmyW0uTusEMw';
const TRANSACTION_ID = 'E2E-PENDING-0001';
const GROSS_AMOUNT = '275000.00';

test.describe('Skenario 4 — Pembayaran: Snap diklik, webhook Midtrans mengubah status', () => {
    test.beforeEach(() => {
        resetDb();
    });

    test('tagihan pending bisa dibayar dan webhook sandbox membuat status jadi lunas', async ({ page, request }) => {
        await login(page, E2E_PAYER);

        await page.goto('/pembayaran');
        await expect(page.getByText('Menunggu Pembayaran')).toBeVisible();
        const totalTagihan = await page.locator('text=Total Tagihan').locator('..').innerText();
        expect(totalTagihan).toContain('275');

        // Script Snap Midtrans di-stub: popup aslinya iframe pihak ketiga dan
        // tidak bisa dipakai di test. Yang diuji adalah jalur UI (tombol →
        // window.snap.pay(token)), bukan integrasi Midtrans sungguhan.
        await page.route('**/snap/snap.js', (route) =>
            route.fulfill({
                status: 200,
                contentType: 'application/javascript',
                body: 'window.snap = { pay: function () {} };',
            }),
        );

        await page.getByRole('button', { name: /Bayar Sekarang/i }).click();

        // Webhook Midtrans disimulasikan persis seperti yang dikirim Midtrans:
        // signature = sha512(order_id + status_code + gross_amount + serverKey),
        // dicek PaymentWebhookService::verifySignature().
        const response = await request.post('/midtrans/webhook', {
            data: {
                order_id: TRANSACTION_ID,
                status_code: '200',
                transaction_status: 'settlement',
                gross_amount: GROSS_AMOUNT,
                signature_key: midtransSignature(TRANSACTION_ID, '200', GROSS_AMOUNT, SERVER_KEY),
            },
        });
        expect(response.status()).toBe(200);

        // Muat ulang halaman: status harus berubah dari pending menjadi lunas.
        await page.reload();
        await expect(page.getByText('LUNAS / DIPROSES')).toBeVisible();
        await expect(page.getByText('Menunggu Pembayaran')).toHaveCount(0);
    });
});
