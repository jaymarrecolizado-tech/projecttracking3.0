<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=90 : Delete audit rows older than this many days}';

    protected $description = 'Prune audit log rows past the retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("audit:prune — deleted {$deleted} row(s) older than {$this->option('days')} days.");

        return self::SUCCESS;
    }
}
