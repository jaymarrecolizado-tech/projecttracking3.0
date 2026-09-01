<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes sites.district from the legislative_districts lookup (Plan §Map 4.1).
 * Idempotent: re-running leaves already-correct rows untouched. LGUs missing
 * from the lookup are deliberately left NULL — never guessed (see Plan §2).
 */
class BackfillSiteDistricts extends Command
{
    protected $signature = 'sites:backfill-districts';

    protected $description = 'Fill sites.district from the legislative_districts lookup';

    public function handle(): int
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $updated = DB::table('sites')
                ->join('legislative_districts as ld', fn ($join) => $join
                    ->on('ld.municipality', '=', 'sites.municipality')
                    ->where('ld.province', '=', DB::raw('sites.province')))
                ->where(fn ($q) => $q->whereNull('sites.district')->orWhereColumn('sites.district', '!=', 'ld.district'))
                ->update(['sites.district' => DB::raw('ld.district')]);
        } else {
            $lookup = DB::table('legislative_districts')->get(['province', 'municipality', 'district'])
                ->keyBy(fn ($r) => $r->province.'|'.$r->municipality);
            $updated = 0;
            DB::table('sites')->whereNotNull('municipality')->orderBy('id')
                ->select('id', 'province', 'municipality', 'district')
                ->chunkById(500, function ($sites) use ($lookup, &$updated) {
                    foreach ($sites as $site) {
                        $district = $lookup->get(($site->province ?? '').'|'.$site->municipality)?->district;
                        if ($district !== null && $site->district !== $district) {
                            DB::table('sites')->where('id', $site->id)->update(['district' => $district]);
                            $updated++;
                        }
                    }
                });
        }

        $missing = DB::table('sites')->whereNull('district')->whereNotNull('municipality')->count();
        $this->info("Updated {$updated} site(s). {$missing} site(s) still have no district (LGU not in lookup).");

        return self::SUCCESS;
    }
}
