<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stamps sites.region and sites.island_group from Site's province lookups
 * (Plan_revision §Phase 1.3).
 *
 * SiteObserver already fills both on every write, but it cannot reach rows that
 * were imported before the lookup existed — and `region` is a live filter
 * (SiteCoverageService::GEO_FILTERS, MapController, GeoJsonService) that
 * silently returns almost nothing while the column is NULL. On this dataset
 * only 14% of live sites had a region.
 *
 * Provinces missing from the lookup are left NULL: never guessed.
 * Idempotent — re-running leaves already-correct rows untouched.
 */
class BackfillSiteRegions extends Command
{
    protected $signature = 'sites:backfill-regions {--dry-run : Report what would change without writing}';

    protected $description = 'Fill sites.region and sites.island_group from the province lookups';

    public function handle(): int
    {
        $regions = Site::REGIONS_BY_PROVINCE;
        $islandGroups = Site::ISLAND_GROUP_BY_PROVINCE;

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $unknown = [];

        DB::table('sites')
            ->whereNotNull('province')
            ->where('province', '<>', '')
            ->orderBy('id')
            ->select('id', 'province', 'region', 'island_group')
            ->chunkById(500, function ($sites) use ($regions, $islandGroups, $dryRun, &$updated, &$unknown) {
                foreach ($sites as $site) {
                    if (! isset($regions[$site->province])) {
                        $unknown[$site->province] = ($unknown[$site->province] ?? 0) + 1;

                        continue;
                    }

                    $changes = [];
                    if ($site->region !== $regions[$site->province]) {
                        $changes['region'] = $regions[$site->province];
                    }
                    // Only fill island_group when empty — a hand-corrected value wins.
                    // Safe to index directly: both lookups carry the same province
                    // keys, and the guard above proved the province is known.
                    if ($site->island_group === null) {
                        $changes['island_group'] = $islandGroups[$site->province];
                    }

                    if ($changes === []) {
                        continue;
                    }

                    if (! $dryRun) {
                        DB::table('sites')->where('id', $site->id)->update($changes);
                    }
                    $updated++;
                }
            });

        $total = DB::table('sites')->whereNull('deleted_at')->count();
        $blankRegion = DB::table('sites')->whereNull('deleted_at')->whereNull('region')->count();
        $blankIsland = DB::table('sites')->whereNull('deleted_at')->whereNull('island_group')->count();
        $pct = fn (int $blank) => $total > 0 ? round(($total - $blank) / $total * 100, 1) : 0.0;

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} site(s).");
        $this->info("Region coverage: {$pct($blankRegion)}% ({$blankRegion} of {$total} live site(s) still blank).");
        $this->info("Island group coverage: {$pct($blankIsland)}% ({$blankIsland} of {$total} live site(s) still blank).");

        foreach ($unknown as $province => $count) {
            $this->warn("No region lookup for province \"{$province}\" — {$count} site(s) left NULL.");
        }

        return self::SUCCESS;
    }
}
