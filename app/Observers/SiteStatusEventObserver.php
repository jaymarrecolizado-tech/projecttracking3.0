<?php

namespace App\Observers;

use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use Illuminate\Support\Facades\DB;

/**
 * Records UP/DOWN episodes as site_status_events (docs §4.2). One open event
 * per site: opened when a status row lands in a DOWN state, closed when the
 * site reports anything else.
 */
class SiteStatusEventObserver
{
    public function saved(SiteDailyStatus $status): void
    {
        $isDown = in_array($status->status, SiteStatusEvent::DOWN_STATUSES, true);

        DB::transaction(function () use ($status, $isDown) {
            $open = SiteStatusEvent::where('site_id', $status->site_id)->whereNull('resolved_at')->lockForUpdate()->first();

            if ($isDown) {
                if ($open) {
                    // Episode continues — track the newest down variant.
                    if ($open->to_status !== $status->status) {
                        $open->update(['to_status' => $status->status]);
                    }

                    return;
                }

                SiteStatusEvent::create([
                    'site_id' => $status->site_id,
                    'from_status' => $this->previousStatus($status),
                    'to_status' => $status->status,
                    'started_at' => $status->date->startOfDay(),
                    'cause' => 'manual',
                ]);
            } elseif ($open) {
                $open->update(['resolved_at' => now()]);
            }
        });
    }

    private function previousStatus(SiteDailyStatus $status): ?string
    {
        $previous = SiteDailyStatus::where('site_id', $status->site_id)
            ->where('id', '!=', $status->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['status']);

        return $previous?->status;
    }
}
