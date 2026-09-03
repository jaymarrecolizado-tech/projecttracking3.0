<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\NameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merges duplicate site rows that describe the same physical location
 * (same coordinates + same normalized name — the workbook lists one row per
 * AP unit, so one school can exist as 14 "sites"). Everything attached to a
 * duplicate — deployments, daily statuses, accomplishments, tickets — moves
 * onto the canonical row; the duplicate is soft-deleted with a
 * metadata.merged_into pointer so heartbeats can still resolve its AP code.
 *
 * Default is a dry-run report. Pass --apply to write.
 */
class DedupeSites extends Command
{
    protected $signature = 'sites:dedupe {--apply : Execute the merge (default: dry-run report)}';

    protected $description = 'Merge duplicate site rows (same coordinates + name) into canonical sites';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $sites = Site::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->get(['id', 'location_name', 'latitude', 'longitude', 'ap_site_code', 'metadata']);

        $groups = $sites
            ->groupBy(fn ($site) => $site->latitude.'|'.$site->longitude.'|'.NameNormalizer::normalize($site->location_name))
            ->filter(fn ($group) => $group->count() > 1);

        $plan = [];
        foreach ($groups as $group) {
            // Canonical: first row that carries an AP code, else the oldest.
            $canonical = $group->sortBy(fn ($site) => [$site->ap_site_code ? 0 : 1, $site->id])->first();
            $plan[] = ['canonical' => $canonical, 'duplicates' => $group->reject(fn ($site) => $site->id === $canonical->id)->values()];
        }

        $this->report($plan);

        if (! $apply) {
            $this->info('Dry run — nothing written. Re-run with --apply to execute.');

            return self::SUCCESS;
        }

        $stats = ['moved_deployments' => 0, 'moved_daily' => 0, 'dropped_daily' => 0,
            'moved_accomplishments' => 0, 'moved_tickets' => 0, 'moved_events' => 0,
            'moved_alerts' => 0, 'moved_metrics' => 0, 'merged' => 0];

        DB::transaction(function () use ($plan, &$stats) {
            foreach ($plan as $entry) {
                $canonical = $entry['canonical'];

                foreach ($entry['duplicates'] as $duplicate) {
                    $stats['moved_deployments'] += DB::table('device_deployments')->where('site_id', $duplicate->id)->update(['site_id' => $canonical->id]);
                    $stats['moved_tickets'] += DB::table('maintenance_tickets')->where('site_id', $duplicate->id)->update(['site_id' => $canonical->id]);
                    $stats['moved_events'] += DB::table('site_status_events')->where('site_id', $duplicate->id)->update(['site_id' => $canonical->id]);
                    $stats['moved_alerts'] += DB::table('alerts')->where('site_id', $duplicate->id)->update(['site_id' => $canonical->id]);
                    $stats['moved_metrics'] += DB::table('device_metrics')->where('site_id', $duplicate->id)->update(['site_id' => $canonical->id]);

                    // Daily statuses: unique(site_id, date) — keep the
                    // canonical row when both recorded the same day.
                    $dates = DB::table('site_daily_statuses')->where('site_id', $duplicate->id)->pluck('date');
                    foreach ($dates as $date) {
                        $exists = DB::table('site_daily_statuses')->where('site_id', $canonical->id)->where('date', $date)->exists();
                        if ($exists) {
                            $stats['dropped_daily'] += DB::table('site_daily_statuses')->where('site_id', $duplicate->id)->where('date', $date)->delete();
                        } else {
                            $stats['moved_daily'] += DB::table('site_daily_statuses')->where('site_id', $duplicate->id)->where('date', $date)->update(['site_id' => $canonical->id]);
                        }
                    }

                    // Accomplishments: unique(site_id, milestone_id) — drop the
                    // duplicate's row when the canonical already has it.
                    $milestones = DB::table('site_accomplishments')->where('site_id', $duplicate->id)->pluck('milestone_id');
                    foreach ($milestones as $milestoneId) {
                        $exists = DB::table('site_accomplishments')->where('site_id', $canonical->id)->where('milestone_id', $milestoneId)->exists();
                        if ($exists) {
                            $stats['moved_accomplishments'] += 0;
                            DB::table('site_accomplishments')->where('site_id', $duplicate->id)->where('milestone_id', $milestoneId)->delete();
                        } else {
                            $stats['moved_accomplishments'] += DB::table('site_accomplishments')->where('site_id', $duplicate->id)->where('milestone_id', $milestoneId)->update(['site_id' => $canonical->id]);
                        }
                    }

                    $metadata = $duplicate->metadata ?? [];
                    $metadata['merged_into'] = $canonical->id;
                    DB::table('sites')->where('id', $duplicate->id)->update([
                        'metadata' => json_encode($metadata),
                        'deleted_at' => now(),
                    ]);
                    $stats['merged']++;
                }
            }
        });

        $remaining = Site::count();
        $this->info("Merged {$stats['merged']} duplicate row(s): {$stats['moved_deployments']} deployment(s), {$stats['moved_daily']} daily row(s) moved, {$stats['dropped_daily']} overlapping day row(s) dropped, {$stats['moved_events']} status event(s), {$stats['moved_alerts']} alert(s), {$stats['moved_metrics']} metric row(s), {$stats['moved_accomplishments']} accomplishment(s), {$stats['moved_tickets']} ticket(s).");
        $this->info("Live sites: {$remaining} (soft-deleted duplicates are recoverable).");

        return self::SUCCESS;
    }

    private function report(array $plan): void
    {
        $this->info('Duplicate groups: '.count($plan));
        foreach (array_slice($plan, 0, 15) as $entry) {
            $canonical = $entry['canonical'];
            $this->line("KEEP #{$canonical->id} {$canonical->location_name} [{$canonical->ap_site_code}]  ←  merge: "
                .$entry['duplicates']->map(fn ($s) => "#{$s->id}".($s->ap_site_code ? "[{$s->ap_site_code}]" : ''))->implode(' '));
        }
        if (count($plan) > 15) {
            $this->line('… and '.(count($plan) - 15).' more group(s)');
        }
    }
}
