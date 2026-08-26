<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Services\Nms\NmsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pull-based telemetry ingest. No-op until an NmsClient implementation is
 * bound (see App\Services\Nms\NmsClient) — schedule it every 5–15 minutes
 * once a real client exists.
 */
class NmsPull extends Command
{
    protected $signature = 'nms:pull {--site= : Limit to a single AP site code}';

    protected $description = 'Poll the NMS/CMS for live site statuses and upsert today\'s records';

    public function handle(NmsClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->warn('No NMS client configured — implement App\\Services\\Nms\\NmsClient and bind it to enable polling.');

            return self::SUCCESS;
        }

        $query = Site::where('status', 'active')->whereNotNull('ap_site_code');
        if ($site = $this->option('site')) {
            $query->where('ap_site_code', $site);
        }
        $sites = $query->get(['id', 'ap_site_code']);
        if ($sites->isEmpty()) {
            $this->info('No sites to poll.');

            return self::SUCCESS;
        }

        $statuses = $client->currentStatuses($sites->pluck('ap_site_code')->all());
        $upserted = 0;

        DB::transaction(function () use ($statuses, $sites, &$upserted) {
            $byCode = $sites->keyBy('ap_site_code');

            foreach ($statuses as $report) {
                $site = $byCode->get($report['site_code']);
                if (! $site) {
                    continue;
                }

                $existing = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', today())->first();
                // LOCKED/APPROVED records are authoritative over poll data.
                if ($existing && in_array($existing->entry_status, ['LOCKED', 'APPROVED'], true)) {
                    continue;
                }

                $attributes = [
                    'status' => $report['status'],
                    'bandwidth_utilization_mbps' => $report['bandwidth_mbps'],
                    'total_unique_users' => $report['users'],
                ];

                if ($existing) {
                    $existing->fill($attributes)->save();
                } else {
                    SiteDailyStatus::create($attributes + ['site_id' => $site->id, 'date' => today()->toDateString()]);
                }
                $upserted++;
            }
        });

        $this->info("NMS pull: {$upserted} record(s) upserted.");

        return self::SUCCESS;
    }
}
