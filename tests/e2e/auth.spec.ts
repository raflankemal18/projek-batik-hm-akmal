import { expect, test } from '@playwright/test';
import { resetDb } from './support/db';
import { E2E_PASSWORD, E2E_USER, login, logout, openProfileMenu, uniqueEmail } from './support/helpers';
import { TID } from './support/selectors';

test.describe('Skenario 1 — Registrasi, sesi, dan logout', () => {
    test.beforeEach(() => {
        resetDb();
    });

    test('user baru daftar mendarat di beranda tanpa error dan bisa logout lalu login lagi', async ({ page }) => {
        const email = uniqueEmail('pbaru');
        const nama = 'Pengguna E2E Baru';

        await page.goto('/register');
        await page.getByTestId(TID.registerName).fill(nama);
        await page.getByTestId(TID.registerEmail).fill(email);
        await page.getByTestId(TID.registerPassword).fill(E2E_PASSWORD);
        await page.getByTestId(TID.registerPasswordConfirmation).fill(E2E_PASSWORD);
        await page.getByTestId(TID.registerSubmit).click();

        // Guard regresi A.1/P0-1: user yang baru dibuat tanpa role eksplisit dulu
        // bisa meledak di halaman berikutnya karena HandleInertiaRequests
        // membaca $user->role.
        await page.waitForURL((url) => url.pathname === '/');
        await expect(page.getByText(nama).first()).toBeVisible();
        await expect(page.getByText(/Server Error|Internal Server Error/i)).toHaveCount(0);

        // Sesi benar-benar terbit: nama user muncul di header dan dropdown
        // profilnya memuat tombol keluar.
        await openProfileMenu(page, nama);
        await page.getByTestId(TID.logout).click();
        await expect(page.getByRole('link', { name: 'Masuk', exact: true })).toBeVisible();

        // Masuk lagi dengan akun hasil registrasi tadi.
        await page.goto('/login');
        await page.getByTestId(TID.loginEmail).fill(email);
        await page.getByTestId(TID.loginPassword).fill(E2E_PASSWORD);
        await page.getByTestId(TID.loginSubmit).click();
        await page.waitForURL((url) => url.pathname === '/');
        await expect(page.getByText(nama).first()).toBeVisible();
    });

    test('akun hasil seed (user E2E) bisa login', async ({ page }) => {
        await login(page, E2E_USER);
        await expect(page.getByText(E2E_USER.name).first()).toBeVisible();
        await logout(page, E2E_USER);
    });
});