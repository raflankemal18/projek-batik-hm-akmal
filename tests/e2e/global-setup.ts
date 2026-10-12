/**
 * Reset database E2E sebelum suite dijalankan.
 *
 * DB E2E = database terpisah (marketplace_batik_e2e) yang hanya dipakai stack
 * docker-compose.e2e.yml, jadi `migrate:fresh` di sini tidak menyentuh data
 * pengembanganmu. Untuk fade: specs berjalan berurutan (workers: 1) dan semua
 * membaca fixture yang sama, jadi reset cukup dilakukan sekali di awal.
 *
 * Set E2E_SKIP_DB_RESET=1 untuk melewati reset (mis. saat DB sudah disiapkan
 * di luar, atau ketika app tidak jalan di Docker).
 */
import { execFileSync } from 'node:child_process';

const COMPOSE = ['-f', 'docker-compose.yml', '-f', 'docker-compose.e2e.yml'];

function artisan(args: string[]) {
    execFileSync('docker', ['compose', ...COMPOSE, 'exec', '-T', 'app', 'php', 'artisan', ...args], {
        stdio: 'inherit',
    });
}

export default async function globalSetup() {
    if (process.env.E2E_SKIP_DB_RESET === '1') {
        console.log('[e2e] E2E_SKIP_DB_RESET=1 → reset database dilewati.');
        return;
    }

    console.log('[e2e] Reset database E2E (e2e:reset)…');
    artisan(['e2e:reset', '--force']);
    console.log('[e2e] Database siap.');
}