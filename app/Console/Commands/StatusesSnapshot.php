<?php

namespace App\Console\Commands;

use App\Models\DeviceMetric;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StatusesSnapshot extends Command
{
    protected $signature = 'statuses:snapshot {--date= : Defaults to today}';

    protected $description = 'Insert NO_DATA records for active sites with no status entry on the given date';

    public function handle(): int
    {
        /** @var CarbonInterface $date */
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();

        $created = DB::transaction(function () use ($date) {
            $sites = Site::where('status', 'active')
                ->whereDoesntHave('dailyStatuses', fn ($q) => $q->whereDate('date', $date))
                ->get(['id']);

            $noData = 0;
            $fromMetrics = 0;
            foreach ($sites as $site) {
                // Sites that heartbeat today but were never keyed in get UP
                // from telemetry instead of a misleading NO_DATA (docs §4.3).
                $heartbeat = DeviceMetric::where('site_id', $site->id)
                    ->whereDate('ts', $date)
                    ->exists();

                SiteDailyStatus::create([
                    'site_id' => $site->id,
                    'date' => $date->toDateString(),
                    'status' => $heartbeat ? 'UP' : 'NO_DATA',
                    'entry_status' => 'DRAFT',
                ]);
                $heartbeat ? $fromMetrics++ : $noData++;
            }

            return "{$fromMetrics} UP (from heartbeats), {$noData} NO_DATA";
        });

        $this->info('Snapshot for '.$date->toDateString().": {$created}.");

        return self::SUCCESS;
    }
}
