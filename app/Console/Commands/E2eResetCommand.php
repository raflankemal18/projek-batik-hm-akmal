<?php

namespace App\Console\Commands;

use Database\Seeders\E2ESeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset cepat database E2E: truncate semua tabel lalu jalankan E2ESeeder.
 *
 * Kenapa bukan `migrate:fresh --seed`: migrasi penuh butuh ~100 detik, sehingga
 * timeout per-test Playwright (60 detik) jadi selalu habis sebelum test body
 * berjalan. Truncate + seed selesai dalam hitungan detik.
 *
 * Perintah ini menolak jalan kalau database aktif bukan database E2E, supaya
 * tidak bisa tidak sengaja menghapus data pengembangan/produksi.
 */
class E2eResetCommand extends Command
{
    protected $signature = 'e2e:reset {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Truncate semua tabel database E2E lalu seed dengan E2ESeeder';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! str_contains((string) $database, 'e2e')) {
            $this->error("Dibatalkan: database aktif '{$database}' tidak mengandung 'e2e'.");
            $this->error('Command ini hanya boleh dipakai pada database E2E (lihat .env.e2e).');

            return self::FAILURE;
        }

        $tables = array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select('SHOW TABLES'),
        );

        $truncated = 0;

        // DELETE FROM (bukan TRUNCATE): TRUNCATE di lingkungan virtual/container
        // ini butuh ~100 detik untuk 22 tabel, sedangkan DELETE cukup 0,03
        // detik. FK checking dimatikan selama loop karena tabel-tabel saling
        // mere referensi.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                if ($table === 'migrations') {
                    continue;
                }

                DB::statement("DELETE FROM `{$table}`");
                $truncated++;
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info("Mengosongkan {$truncated} tabel di database '{$database}'.");

        $this->call('db:seed', ['--class' => E2ESeeder::class, '--force' => true]);

        $this->info('Database E2E siap dipakai.');

        return self::SUCCESS;
    }
}