<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes sites.region (and sites.island_group) from the PSGC region encoded in
 * barangay_references.psgc (Plan_revision §Phase 1.3).
 *
 * `region` is a live filter (SiteCoverageService::GEO_FILTERS, MapController,
 * GeoJsonService) but 86% of sites had it NULL, so filtering by region silently
 * returned almost nothing. Provinces absent from the PSGC reference — or with a
 * code we don't recognise — are left NULL: never guessed.
 *
 * Idempotent: re-running leaves already-correct rows untouched.
 */
class BackfillSiteRegions extends Command
{
    protected $signature = 'sites:backfill-regions {--dry-run : Report what would change without writing}';

    protected $description = 'Fill sites.region and sites.island_group from the PSGC region of the province';

    public function handle(): int
    {
        $regions = config('psgc.regions');
        $islandGroups = config('psgc.island_groups');

        // province => two-digit PSGC region code, from the app's own reference.
        $provinceCodes = DB::table('barangay_references')
            ->selectRaw('province, MIN(SUBSTR(psgc, 1, 2)) AS region_code')
            ->whereNotNull('province')
            ->where('province', '<>', '')
            ->groupBy('province')
            ->pluck('region_code', 'province');

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $unknown = [];

        DB::table('sites')
            ->whereNotNull('province')
            ->where('province', '<>', '')
            ->orderBy('id')
            ->select('id', 'province', 'region', 'island_group')
            ->chunkById(500, function ($sites) use ($provinceCodes, $regions, $islandGroups, $dryRun, &$updated, &$unknown) {
                foreach ($sites as $site) {
                    $code = $provinceCodes->get($site->province);

                    if ($code === null || ! isset($regions[$code])) {
                        $unknown[$site->province] = ($unknown[$site->province] ?? 0) + 1;

                        continue;
                    }

                    $region = $regions[$code];
                    $islandGroup = $islandGroups[$code] ?? null;

                    $changes = [];
                    if ($site->region !== $region) {
                        $changes['region'] = $region;
                    }
                    // Only fill island_group when empty — a hand-corrected value wins.
                    if ($islandGroup !== null && $site->island_group === null) {
                        $changes['island_group'] = $islandGroup;
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

        $missing = DB::table('sites')->whereNull('deleted_at')->whereNull('region')->count();
        $total = DB::table('sites')->whereNull('deleted_at')->count();
        $coverage = $total > 0 ? round(($total - $missing) / $total * 100, 1) : 0.0;

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} site(s).");
        $this->info("Region coverage: {$coverage}% ({$missing} of {$total} live site(s) still have no region).");

        foreach ($unknown as $province => $count) {
            $this->warn("No PSGC region for province \"{$province}\" — {$count} site(s) left NULL.");
        }

        return self::SUCCESS;
    }
}
