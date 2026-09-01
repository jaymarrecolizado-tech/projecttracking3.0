<?php

namespace App\Console\Commands;

use App\Support\NameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Seeds the barangay reference list from storage/app/geo/barangays.geojson.
 * Upsert-only: barangays added manually (e.g. from the PSA count of 2,311)
 * are never deleted by a re-sync.
 */
class SyncBarangayReference extends Command
{
    protected $signature = 'barangays:sync-reference';

    protected $description = 'Sync the barangay reference list from the boundary layer';

    public function handle(): int
    {
        $path = storage_path('app/geo/barangays.geojson');
        if (! File::exists($path)) {
            $this->error('storage/app/geo/barangays.geojson not found.');

            return self::FAILURE;
        }

        $decoded = json_decode(File::get($path), true);
        $features = $decoded['features'] ?? [];

        $upserted = 0;
        foreach (collect($features)->chunk(500) as $chunk) {
            $rows = $chunk->map(fn ($feature) => [
                'province' => $feature['properties']['province'],
                'municipality' => $feature['properties']['municipality'],
                'name' => $feature['properties']['name'],
                'name_normalized' => NameNormalizer::normalize($feature['properties']['name']),
                'psgc' => $feature['properties']['psgc'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
                ->filter(fn ($row) => $row['name'] !== null && $row['name'] !== '')
                ->unique(fn ($row) => $row['province'].'|'.$row['municipality'].'|'.$row['name_normalized'])
                ->values()->all();

            DB::table('barangay_references')->upsert($rows, ['province', 'municipality', 'name_normalized'], ['name', 'psgc', 'updated_at']);
            $upserted += count($rows);
        }

        $total = DB::table('barangay_references')->count();
        $this->info("Upserted {$upserted} barangay row(s). Reference total: {$total}.");

        return self::SUCCESS;
    }
}
