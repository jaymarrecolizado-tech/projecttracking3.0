<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Administrative-boundary polygons for the map (Plan §Map 4.2). Files live in
 * storage/app/geo/{level}.geojson (Region II subset — never the public web
 * root). Missing files degrade to an empty FeatureCollection so the map stays
 * useful while boundaries lag; see docs/DEPLOY.md for sourcing the files.
 */
class GeoBoundaryService
{
    public const LEVELS = ['province', 'district', 'municipality', 'barangay'];

    public function forLevel(string $level, array $filters = []): array
    {
        $level = in_array($level, self::LEVELS, true) ? $level : 'province';

        $cacheKey = 'map.boundaries.'.md5($level.serialize($filters));
        $ttl = now()->addHours(12);

        return Cache::remember($cacheKey, $ttl, fn () => $this->load($level, $filters));
    }

    private function load(string $level, array $filters): array
    {
        // Files are pluralized (provinces.geojson, municipalities.geojson).
        $file = ['municipality' => 'municipalities'][$level] ?? $level.'s';
        $path = storage_path("app/geo/{$file}.geojson");
        if (! File::exists($path)) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded) || ($decoded['type'] ?? '') !== 'FeatureCollection') {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $features = array_values(array_filter($decoded['features'] ?? [], function ($feature) use ($filters) {
            $props = $feature['properties'] ?? [];

            foreach (['province' => 'province', 'district' => 'district', 'municipality' => 'municipality'] as $filter => $prop) {
                if (! empty($filters[$filter]) && ($props[$prop] ?? null) !== $filters[$filter]) {
                    return false;
                }
            }

            return true;
        }));

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
