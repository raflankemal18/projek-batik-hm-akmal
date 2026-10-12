import { defineConfig, devices } from '@playwright/test';

/**
 * E2E untuk marketplace BatikNesia (Fase C — docs/planning-fase-c-playwright-e2e-2026-08-22.md).
 *
 * Stack (nginx :8000 + php-fpm + MySQL) dikelola oleh docker compose lewat
 * scripts/e2e.sh, BUKAN oleh Playwright — makanya `webServer` di bawah sengaja
 * tidak dipakai. Jalankan suite dengan `bash scripts/e2e.sh`.
 */
const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8000';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',

    // Semua spec berbagi satu database E2E → harus serial.
    workers: 1,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,

    timeout: 60_000,
    expect: { timeout: 10_000 },

    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }], ['list']]
        : [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        // app.blade.php mendaftarkan /sw.js di SEMUA halaman dan sw.js melakukan
        // caching response. Tanpa ini, spec bisa membaca HTML basi dari cache.
        serviceWorkers: 'block',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});