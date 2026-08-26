<?php

namespace App\Console\Commands;

use App\Models\FreewifiImportBatch;
use Illuminate\Console\Command;

class CleanupStaleImports extends Command
{
    protected $signature = 'imports:cleanup';

    protected $description = 'Mark imports stuck in PENDING/PROCESSING for over an hour as FAILED';

    public function handle(): int
    {
        $stale = FreewifiImportBatch::whereIn('job_status', ['PENDING', 'PROCESSING'])
            ->where('updated_at', '<', now()->subHour())
            ->get();

        foreach ($stale as $batch) {
            $batch->update([
                'job_status' => 'FAILED',
                'error_log' => [['message' => 'Import worker died or timed out; marked failed by scheduler.']],
                'completed_at' => now(),
            ]);
        }

        $this->info("Marked {$stale->count()} stale import batch(es) as FAILED.");

        return self::SUCCESS;
    }
}
