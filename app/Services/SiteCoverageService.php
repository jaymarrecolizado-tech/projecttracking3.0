<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Site;
use App\Support\CoverageCache;

/**
 * Actual-vs-registered coverage by Site Type (Plan §Map 4.5). "Actual" = the
 * site has at least one active device deployment. Shared by the map stats
 * panel (/map/coverage) and the queued PDF report.
 */
class SiteCoverageService
{
    private const GEO_FILTERS = ['region', 'province', 'district', 'municipality', 'barangay'];

    public function coverage(array $filters = []): array
    {
        return CoverageCache::remember('site-type', $filters, fn () => $this->computeCoverage($filters));
    }

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
        ];
    }

    /** Human-readable filter set so every PDF is self-describing (§Phase 5.5). */
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

    private function applyFilters($query, array $filters): void
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

    /** GROUP BY null/blank site_type as one bucket so totals match Site::count(). */
    private function countsByType($query)
    {
        return $query->selectRaw('site_type, COUNT(*) as n')
            ->groupBy('site_type')
            ->get()
            ->reduce(function ($counts, $row) {
                $key = $this->typeKey($row->site_type);
                $counts[$key] = ($counts[$key] ?? 0) + (int) $row->n;

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
