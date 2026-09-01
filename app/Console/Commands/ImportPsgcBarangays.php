<?php

namespace App\Console\Commands;

use App\Support\NameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Reconciles the barangay reference list against an official PSGC export
 * (flat array of {psgc_id, name, muni, prov}). Upsert-only: rows add or
 * refresh; nothing is deleted. After a full PSGC import the reference total
 * equals the PSA barangay count (2,311 for Region II).
 */
class ImportPsgcBarangays extends Command
{
    protected $signature = 'barangays:import-psgc {file : Path to the PSGC flat JSON export}';

    protected $description = 'Import/refresh barangay references from an official PSGC export';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);
        if (! is_array($rows)) {
            $this->error('Invalid JSON — expected a flat array of PSGC entries.');

            return self::FAILURE;
        }

        // Our municipality/province spellings, keyed by normalized name.
        $ourMunis = DB::table('barangay_references')
            ->select('province', 'municipality')
            ->distinct()
            ->get()
            ->mapWithKeys(fn ($r) => [NameNormalizer::normalize($r->municipality) => $r]);
        $ourProvinces = [];
        foreach ($ourMunis as $row) {
            $ourProvinces[NameNormalizer::normalize($row->province)] = $row->province;
        }

        $inserted = 0;
        $skipped = 0;
        foreach (collect($rows)->chunk(500) as $chunk) {
            $upserts = [];
            foreach ($chunk as $row) {
                $muniKey = NameNormalizer::normalize($row['muni'] ?? null);
                $provKey = NameNormalizer::normalize($row['prov'] ?? null);

                $municipality = $ourMunis[$muniKey]->municipality ?? ($row['muni'] ?? null);
                $province = $ourProvinces[$provKey] ?? ($row['prov'] ?? null);
                if (! $municipality || ! $province || ! ($row['name'] ?? null)) {
                    $skipped++;

                    continue;
                }

                $upserts[] = [
                    'province' => $province,
                    'municipality' => $municipality,
                    'name' => $row['name'],
                    'name_normalized' => NameNormalizer::normalize($row['name']),
                    'psgc' => $row['psgc_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $upserts = collect($upserts)->unique(fn ($r) => $r['province'].'|'.$r['municipality'].'|'.$r['name_normalized'])->values()->all();
            if ($upserts !== []) {
                DB::table('barangay_references')->upsert($upserts, ['province', 'municipality', 'name_normalized'], ['name', 'psgc', 'updated_at']);
                $inserted += count($upserts);
            }
        }

        $total = DB::table('barangay_references')->count();
        $this->info("Processed {$inserted} PSGC row(s) ({$skipped} skipped). Reference total: {$total}.");

        return self::SUCCESS;
    }
}
