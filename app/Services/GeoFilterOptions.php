<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cascading geo filter options (Plan §Map 4.4) — distinct values from sites
 * that have data, narrowed by the chosen parents. Shared by Map View and
 * the Reports page.
 *
 * @phpstan-type Options array{provinces: Collection<int, string>, districts: Collection<int, string>, municipalities: Collection<int, string>, barangays: Collection<int, string>}
 * @phpstan-type SiteTypeOption array{code: string, label: string}
 */
class GeoFilterOptions
{
    /**
     * @param  array{project_id?: mixed, province?: mixed, district?: mixed, municipality?: mixed}  $parents
     * @return Options
     */
    public function for(array $parents = []): array
    {
        $projectId = $parents['project_id'] ?? null;
        $province = $parents['province'] ?? null;
        $district = $parents['district'] ?? null;
        $municipality = $parents['municipality'] ?? null;

        // Every list is narrowed by the chosen project so the Reports/Map areas
        // only offer places that actually exist in that project's data.
        $scoped = fn (Builder $query) => $query->when($projectId, fn ($q) => $q->where('project_id', $projectId));

        return [
            'provinces' => $scoped(Site::whereNotNull('province'))->distinct()->orderBy('province')->pluck('province'),
            'districts' => $scoped(Site::whereNotNull('district'))
                ->when($province, fn ($q) => $q->where('province', $province))
                ->distinct()->orderBy('district')->pluck('district'),
            'municipalities' => $scoped(Site::whereNotNull('municipality'))
                ->when($province, fn ($q) => $q->where('province', $province))
                ->when($district, fn ($q) => $q->where('district', $district))
                ->distinct()->orderBy('municipality')->pluck('municipality'),
            'barangays' => $scoped(Site::whereNotNull('barangay'))
                ->when($province, fn ($q) => $q->where('province', $province))
                ->when($district, fn ($q) => $q->where('district', $district))
                ->when($municipality, fn ($q) => $q->where('municipality', $municipality))
                ->distinct()->orderBy('barangay')->pluck('barangay'),
        ];
    }

    /** @return list<SiteTypeOption>  */
    public function siteTypes(): array
    {
        return Site::whereNotNull('site_type')->distinct()->orderBy('site_type')->pluck('site_type')
            ->map(fn ($code) => ['code' => $code, 'label' => config('site_types')[$code] ?? $code])
            ->values()->all();
    }
}
