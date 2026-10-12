import { execFileSync } from 'node:child_process';

/**
 * Reset database E2E ke fixture E2ESeeder.
 *
 * Semua spec berbagi satu database dan berjalan berurutan (workers: 1), jadi
 * setiap test me-reset sendiri supaya tidak saling bergantung urutan file. Reset
 * dilakukan SEBELUM login (sesi database ikut terhapus bersama reset).
 *
 * Lewati dengan E2E_SKIP_DB_RESET=1 kalau database sudah disiapkan di tempat lain.
 */
const COMPOSE = ['-f', 'docker-compose.yml', '-f', 'docker-compose.e2e.yml'];

export function resetDb() {
    if (process.env.E2E_SKIP_DB_RESET === '1') {
        return;
    }

    execFileSync(
        'docker',
        ['compose', ...COMPOSE, 'exec', '-T', 'app', 'php', 'artisan', 'e2e:reset', '--force'],
        { stdio: 'pipe' },
    );
}