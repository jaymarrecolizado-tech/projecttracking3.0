<?php

namespace App\Console\Commands;

use App\Models\DeviceMetric;
use Illuminate\Console\Command;

/**
 * Retention for the device_metrics time-series (docs §4.2): raw points older
 * than the window are deleted. High-frequency probes every 5 min ≈ 288 rows
 * per device per day, so pruning is what keeps the table bounded.
 */
class PruneDeviceMetrics extends Command
{
    protected $signature = 'metrics:prune {--days=90 : Retention window in days}';

    protected $description = 'Delete device_metrics rows older than the retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));

        $deleted = DeviceMetric::where('ts', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} device_metrics row(s) older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
