<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\NameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stamps sites with official PSGC codes from the barangay reference list
 * (Plan backlog: "PSGC on sites"). loc_id = barangay PSGC, prov_id =
 * province PSGC, metadata.municipality_psgc = municipality PSGC. These
 * legacy columns were unused by the app, so no schema change is needed.
 * Idempotent: re-running refreshes matched sites in place.
 */
class AttachSitePsgc extends Command
{
    protected $signature = 'sites:attach-psgc';

    protected $description = 'Fill sites with PSGC codes matched from the barangay reference list';

    public function handle(): int
    {
        $refs = DB::table('barangay_references')
            ->get(['province', 'municipality', 'name_normalized', 'psgc']);

        $index = [];
        foreach ($refs as $ref) {
            if (! $ref->psgc) {
                continue;
            }
            $index[NameNormalizer::normalize($ref->province).'|'.NameNormalizer::normalize($ref->municipality).'|'.$ref->name_normalized] = $ref;
        }

        $matched = 0;
        $unmatched = 0;

        Site::whereNotNull('barangay')->where('barangay', '!=', '')
            ->with('project:id,code')
            ->orderBy('id')
            ->chunkById(500, function ($sites) use (&$matched, &$unmatched, $index) {
                foreach ($sites as $site) {
                    $key = NameNormalizer::normalize($site->province).'|'
                        .NameNormalizer::normalize($site->municipality).'|'
                        .NameNormalizer::normalize($site->barangay);

                    $ref = $index[$key] ?? null;
                    if (! $ref) {
                        $unmatched++;

                        continue;
                    }

                    $metadata = $site->metadata ?? [];
                    $metadata['municipality_psgc'] = substr($ref->psgc, 0, 9).'0';

                    $site->forceFill([
                        'loc_id' => $ref->psgc,
                        'prov_id' => substr($ref->psgc, 0, 5).'00000',
                        'metadata' => $metadata,
                    ])->save();
                    $matched++;
                }
            });

        $missingBarangay = Site::where(fn ($q) => $q->whereNull('barangay')->orWhere('barangay', ''))->count();

        $this->info("Matched {$matched} site(s) to PSGC codes. {$unmatched} site(s) had no reference match; {$missingBarangay} site(s) have no barangay recorded.");

        return self::SUCCESS;
    }
}
