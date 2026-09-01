<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * DOWN-alert engine: for each site whose latest daily status is DOWN and whose
 * episode hasn't been alerted yet, notify users holding daily.approve on the
 * owning project. Episodes dedupe via sites.last_alerted_at.
 */
class DispatchDownAlerts extends Command
{
    protected $signature = 'alerts:down {--email= : Extra recipient (falls back to WATCHDOG_EMAIL env)}';

    protected $description = 'Notify stakeholders about sites currently DOWN (once per DOWN episode)';

    public function handle(): int
    {
        $downSites = Site::query()
            ->with(['project:id,name,code', 'latestDailyStatus'])
            ->whereHas('latestDailyStatus', fn ($q) => $q->where('status', 'DOWN'))
            ->get(['id', 'location_name', 'municipality', 'province', 'project_id', 'last_alerted_at']);

        // Only fresh episodes: never alerted, or alerted before this DOWN started.
        $fresh = $downSites->filter(function ($site) {
            $downSince = $site->latestDailyStatus?->date;
            if (! $downSince) {
                return false;
            }

            return $site->last_alerted_at === null || $site->last_alerted_at->lt($downSince);
        });

        if ($fresh->isEmpty()) {
            $this->info('No new DOWN episodes.');

            return self::SUCCESS;
        }

        $recipients = User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', 'daily.approve'))
            ->pluck('email')
            ->unique()
            ->values();
        if ($extra = ($this->option('email') ?: config('monitoring.watchdog_email'))) {
            $recipients->push($extra);
        }

        foreach ($fresh as $site) {
            if ($recipients->isNotEmpty()) {
                Mail::raw(
                    sprintf(
                        "SITE DOWN\n\nSite: %s\nLocation: %s\nProject: %s\nSince: %s\n\nManage: %s",
                        $site->location_name,
                        trim(($site->municipality ?? '').', '.($site->province ?? ''), ', '),
                        $site->project->name ?? '-',
                        optional($site->latestDailyStatus->date)->toDateString(),
                        route('sites.show', $site),
                    ),
                    fn ($m) => $m->to($recipients->all())->subject("[ALERT] {$site->location_name} is DOWN"),
                );
            }
            $site->update(['last_alerted_at' => now()]);
        }

        $this->info("Dispatched alerts for {$fresh->count()} site(s) to {$recipients->count()} recipient(s).");

        return self::SUCCESS;
    }
}
