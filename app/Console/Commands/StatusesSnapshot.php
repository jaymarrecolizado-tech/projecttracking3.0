<?php

namespace App\Console\Commands;

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

            foreach ($sites as $site) {
                SiteDailyStatus::create([
                    'site_id' => $site->id,
                    'date' => $date->toDateString(),
                    'status' => 'NO_DATA',
                    'entry_status' => 'DRAFT',
                ]);
            }

            return $sites->count();
        });

        $this->info("Snapshot: {$created} NO_DATA record(s) inserted for ".$date->toDateString().'.');

        return self::SUCCESS;
    }
}
