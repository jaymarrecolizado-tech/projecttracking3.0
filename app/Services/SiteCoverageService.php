<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Site;
use App\Support\CoverageCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Actual-vs-registered coverage by Site Type (Plan §Map 4.5). "Actual" = the
 * site has at least one active device deployment. Shared by the map stats
 * panel (/map/coverage) and the queued PDF report.
 *
 * @phpstan-type Filters array{project_id?: mixed, site_type?: mixed, status?: mixed, region?: mixed, province?: mixed, district?: mixed, municipality?: mixed, barangay?: mixed}
 * @phpstan-type Row array{site_type: string, label: string, registered: int, actual: int, gap: int, devices: int, coverage_pct: float}
 * @phpstan-type SiteTotals array{registered: int, actual: int, gap: int, devices: int, coverage_pct: float}
 * @phpstan-type SiteCoverage array{filters: array<string, mixed>, scope: string, rows: list<Row>, totals: SiteTotals, district_blank_sites: int}
 */
class SiteCoverageService
{
    private const GEO_FILTERS = ['region', 'province', 'district', 'municipality', 'barangay'];

    /**
     * @param  Filters  $filters
     * @return SiteCoverage
     */
    public function coverage(array $filters = []): array
    {
        return CoverageCache::remember('site-type', $filters, fn () => $this->computeCoverage($filters));
    }

    /**
     * @param  Filters  $filters
     * @return SiteCoverage
     */
    private function computeCoverage(array $filters): array
    {
        $registered = Site::query();
        $this->applyFilters($registered, $filters);

        $actual = Site::query()->whereHas('activeDeployments');
        $this->applyFilters($actual, $filters);

        $registeredRows = $this->countsByType($registered);
        $actualRows = $this->countsByType($actual);

        $devices = Site::query()->whereHas('activeDeployments');
        $this->applyFilters($devices, $filters);
        $deviceRows = $devices->withCount('activeDeployments')->get()
            ->groupBy(fn ($site) => $this->typeKey($site->site_type))
            ->map(fn ($group) => (int) $group->sum('active_deployments_count'));

        $types = $registeredRows->keys()
            ->merge($actualRows->keys())
            ->merge($deviceRows->keys())
            ->unique()
            ->sort(function ($a, $b) {
                if ($a === '') {
                    return 1;
                }
                if ($b === '') {
                    return -1;
                }

                return $a <=> $b;
            })
            ->values();

        $rows = $types->map(function ($type) use ($registeredRows, $actualRows, $deviceRows) {
            $registered = (int) $registeredRows->get($type, 0);
            $actual = (int) $actualRows->get($type, 0);

            return [
                'site_type' => $type,
                'label' => $this->typeLabel($type),
                'registered' => $registered,
                'actual' => $actual,
                'gap' => $registered - $actual,
                'devices' => (int) $deviceRows->get($type, 0),
                'coverage_pct' => $registered > 0 ? round($actual / $registered * 100, 1) : 0.0,
            ];
        })->all();

        $totalRegistered = array_sum(array_column($rows, 'registered'));
        $totalActual = array_sum(array_column($rows, 'actual'));

        return [
            'filters' => collect($filters)->only(['project_id', 'site_type', 'status', ...self::GEO_FILTERS])->filter()->all(),
            'scope' => $this->describeScope($filters),
            'rows' => $rows,
            'totals' => [
                'registered' => $totalRegistered,
                'actual' => $totalActual,
                'gap' => $totalRegistered - $totalActual,
                'devices' => array_sum(array_column($rows, 'devices')),
                'coverage_pct' => $totalRegistered > 0 ? round($totalActual / $totalRegistered * 100, 1) : 0.0,
            ],
            // A district filter matches on sites.district, so sites with a blank
            // district drop out of the numerator invisibly. Report them so a low
            // figure can never masquerade as complete (Plan_revision §Phase 1.4).
            'district_blank_sites' => $this->districtBlankSites($filters),
        ];
    }

    /**
     * Live sites in the filtered province(s) that the district filter cannot see.
     *
     * @param  Filters  $filters
     */
    private function districtBlankSites(array $filters): int
    {
        if (empty($filters['district'])) {
            return 0;
        }

        $query = Site::query()
            ->where(fn ($q) => $q->whereNull('district')->orWhere('district', ''))
            ->when($filters['province'] ?? null, fn ($q, $v) => $q->where('province', $v))
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->where('project_id', $v))
            ->when($filters['site_type'] ?? null, fn ($q, $v) => $q->where('site_type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        // No province selected: a district name can span provinces, so scope to
        // the provinces the lookup says that district covers.
        if (empty($filters['province'])) {
            $provinces = DB::table('legislative_districts')
                ->where('district', $filters['district'])
                ->distinct()
                ->pluck('province');

            if ($provinces->isEmpty()) {
                return 0;
            }
            $query->whereIn('province', $provinces);
        }

        return $query->count();
    }

    /**
     * Human-readable filter set so every PDF is self-describing (§Phase 5.5).
     *
     * @param  Filters  $filters
     */
    private function describeScope(array $filters): string
    {
        $parts = [];
        if (! empty($filters['project_id'])) {
            $name = Project::where('id', $filters['project_id'])->value('name');
            $parts[] = 'Project: '.($name ?? "#{$filters['project_id']}");
        }
        foreach (['province' => 'Province', 'district' => 'District', 'municipality' => 'Municipality', 'barangay' => 'Barangay', 'site_type' => 'Site type', 'status' => 'Status', 'region' => 'Region'] as $key => $label) {
            if (! empty($filters[$key])) {
                $parts[] = $label.': '.$filters[$key];
            }
        }

        return $parts === [] ? 'All areas' : implode(' · ', $parts);
    }

    /**
     * @param  Builder<Site>  $query
     * @param  Filters  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (self::GEO_FILTERS as $column) {
            if (! empty($filters[$column])) {
                $query->where('sites.'.$column, $filters[$column]);
            }
        }
        if (! empty($filters['project_id'])) {
            $query->where('sites.project_id', $filters['project_id']);
        }
        if (! empty($filters['site_type'])) {
            $query->where('sites.site_type', $filters['site_type']);
        }
        if (! empty($filters['status'])) {
            $query->where('sites.status', $filters['status']);
        }
    }

    /**
     * GROUP BY null/blank site_type as one bucket so totals match Site::count().
     *
     * @param  Builder<Site>  $query
     * @return Collection<string, int>
     */
    private function countsByType(Builder $query): Collection
    {
        return $query->selectRaw('site_type, COUNT(*) as n')
            ->groupBy('site_type')
            ->get()
            ->reduce(function (Collection $counts, Site $row) {
                $key = $this->typeKey($row->site_type);
                $counts[$key] = ($counts[$key] ?? 0) + (int) $row->getAttribute('n');

                return $counts;
            }, collect());
    }

    private function typeKey(?string $type): string
    {
        return $type === null || $type === '' ? '' : $type;
    }

    private function typeLabel(string $type): string
    {
        if ($type === '') {
            return 'Unspecified';
        }

        return config('site_types')[$type] ?? $type;
    }
}
