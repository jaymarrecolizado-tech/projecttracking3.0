<?php

namespace App\Services;

use App\Models\Site;

/**
 * Cascading geo filter options (Plan §Map 4.4) — distinct values from sites
 * that have data, narrowed by the chosen parents. Shared by Map View and
 * the Reports page.
 */
class GeoFilterOptions
{
    public function for(array $parents = []): array
    {
        $province = $parents['province'] ?? null;
        $district = $parents['district'] ?? null;
        $municipality = $parents['municipality'] ?? null;

        return [
            'provinces' => Site::whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
            'districts' => Site::whereNotNull('district')
                ->when($province, fn ($q) => $q->where('province', $province))
                ->distinct()->orderBy('district')->pluck('district'),
            'municipalities' => Site::whereNotNull('municipality')
                ->when($province, fn ($q) => $q->where('province', $province))
                ->when($district, fn ($q) => $q->where('district', $district))
                ->distinct()->orderBy('municipality')->pluck('municipality'),
            'barangays' => Site::whereNotNull('barangay')
                ->when($province, fn ($q) => $q->where('province', $province))
                ->when($district, fn ($q) => $q->where('district', $district))
                ->when($municipality, fn ($q) => $q->where('municipality', $municipality))
                ->distinct()->orderBy('barangay')->pluck('barangay'),
        ];
    }

    public function siteTypes(): array
    {
        return Site::whereNotNull('site_type')->distinct()->orderBy('site_type')->pluck('site_type')
            ->map(fn ($code) => ['code' => $code, 'label' => config('site_types')[$code] ?? $code])
            ->values()->all();
    }
}
