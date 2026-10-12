import crypto from 'node:crypto';
import { expect, type Page } from '@playwright/test';
import { TID } from './selectors';

/** Email unik supaya re-run tidak bentrok dengan user yang sudah terdaftar. */
export function uniqueEmail(prefix: string): string {
    return `${prefix}-${Date.now()}-${Math.floor(Math.random() * 1000)}@e2e.test`;
}

export const E2E_PASSWORD = 'password';

export const E2E_ADMIN = { name: 'Admin E2E', email: 'admin@e2e.test', password: E2E_PASSWORD };
export const E2E_USER = { name: 'User E2E', email: 'user@e2e.test', password: E2E_PASSWORD };

/** User dengan tagihan pending dari E2ESeeder — dipakai skenario pembayaran. */
export const E2E_PAYER = { name: 'Payer E2E', email: 'bayar@e2e.test', password: E2E_PASSWORD };

export type Account = { name: string; email: string; password: string };

/**
 * Login lewat UI (bukan injeksi session) supaya alur autentikasi ikut
 * ter-cover dan cookie sesi benar-benar terbit di browser context.
 *
 * Berhasilnya login dideteksi dari disappearance of the header's "Masuk" link —
 * tombol logout sendiri berada di dalam dropdown profil yang baru dirender
 * setelah dropdown dibuka.
 */
export async function login(page: Page, account: Account) {
    await page.goto('/login');
    await page.getByTestId(TID.loginEmail).fill(account.email);
    await page.getByTestId(TID.loginPassword).fill(account.password);
    await page.getByTestId(TID.loginSubmit).click();

    await expect(page.getByRole('link', { name: 'Masuk', exact: true })).toHaveCount(0);
    await page.waitForURL((url) => url.pathname === '/' || url.pathname.startsWith('/admin'));
}

/**
 * Logout dari layout mana pun:
 *   - Halaman publik  → dropdown profil di header (WelcomeLayout) berisi nama user.
 *   - Halaman admin   → dropdown akun di sidebar (AdminLayout) tidak menampilkan
 *     nama, hanya avatar, jadi pakai testid sendiri.
 */
export async function logout(page: Page, account: Account) {
    const adminTrigger = page.getByTestId(TID.adminProfileTrigger);

    if (await adminTrigger.isVisible().catch(() => false)) {
        await adminTrigger.click();
        await page.getByTestId(TID.adminLogout).click();
    } else {
        await openProfileMenu(page, account.name);
        await page.getByTestId(TID.logout).click();
    }

    await page.waitForURL((url) => url.pathname === '/' || url.pathname === '/login');
    await expect(page.getByRole('link', { name: 'Masuk', exact: true })).toBeVisible();
}

/** Buka dropdown profil di header (WelcomeLayout) — isinya baru dirender setelah dibuka. */
export async function openProfileMenu(page: Page, name: string) {
    const trigger = page.getByRole('button', { name: new RegExp(name) }).first();
    await trigger.waitFor({ state: 'visible' });
    await trigger.click();
}

// Nilai ini harus sama dengan Database\Seeders\E2ESeeder.
export const LOW_STOCK_PRODUCT = 'Batik E2E Pria Stok Satu';
export const WANITA_PRODUCT = 'Batik E2E Women';
export const SEARCH_KEYWORD = 'Motifwali';

/**
 * Signature webhook Midtrans — cermin dari App\Services\MidtransService::verifySignature():
 * sha512(order_id + status_code + gross_amount + serverKey). Server key sandbox
 * diambil dari .env.e2e / .env.example.
 */
export function midtransSignature(
    orderId: string,
    statusCode: string,
    grossAmount: string,
    serverKey: string,
): string {
    return crypto
        .createHash('sha512')
        .update(`${orderId}${statusCode}${grossAmount}${serverKey}`)
        .digest('hex');
}