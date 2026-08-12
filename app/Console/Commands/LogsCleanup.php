<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class LogsCleanup extends Command
{
    protected $signature = 'logs:cleanup {--months=12}';

    protected $description = 'Delete activity logs older than the given number of months';

    public function handle(): int
    {
        $cutoff = now()->subMonths((int) $this->option('months'));
        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();
        $this->info("Deleted {$deleted} logs older than {$this->option('months')} months.");

        return self::SUCCESS;
    }
}
