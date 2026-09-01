<?php

namespace App\Console\Commands;

use App\Models\DeviceMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hourly rollups of device_metrics (docs §4.2 "aggregate-and-prune", §6
 * "charts read rollups"). Idempotent per (device, hour) so re-runs and
 * late-arriving points just refresh the bucket.
 */
class AggregateDeviceMetrics extends Command
{
    protected $signature = 'metrics:aggregate {--hours=48 : How far back to (re)build}';

    protected $description = 'Roll device_metrics into hourly averages';

    public function handle(): int
    {
        $since = now()->subHours(max(1, (int) $this->option('hours')))->startOfHour();

        $rows = DeviceMetric::query()
            ->where('ts', '>=', $since)
            ->whereNotNull('device_id')
            ->get(['device_id', 'site_id', 'ts', 'latency_ms', 'clients', 'rx_mbps', 'tx_mbps', 'battery_v']);

        $buckets = [];
        foreach ($rows as $row) {
            $hour = Carbon::parse($row->ts)->startOfHour();
            $key = $row->device_id.'|'.$hour->format('Y-m-d H:i:s');
            $buckets[$key] ??= ['device_id' => $row->device_id, 'site_id' => $row->site_id, 'hour' => $hour, 'rows' => []];
            $buckets[$key]['rows'][] = $row;
        }

        $written = 0;
        foreach ($buckets as $bucket) {
            $values = fn ($column) => collect($bucket['rows'])->pluck($column)->filter()->values();
            $avg = fn ($column) => $values($column)->isEmpty() ? null : round($values($column)->avg(), 2);

            DB::table('device_metric_hourlies')->updateOrInsert(
                ['device_id' => $bucket['device_id'], 'hour' => $bucket['hour']],
                [
                    'site_id' => $bucket['site_id'],
                    'latency_avg' => $avg('latency_ms'),
                    'latency_max' => $values('latency_ms')->max(),
                    'clients_max' => $values('clients')->max(),
                    'rx_avg' => $avg('rx_mbps'),
                    'tx_avg' => $avg('tx_mbps'),
                    'battery_min' => $values('battery_v')->min(),
                    'samples' => count($bucket['rows']),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $written++;
        }

        $this->info("Rolled {$rows->count()} metric row(s) into {$written} hourly bucket(s).");

        return self::SUCCESS;
    }
}
