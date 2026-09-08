<?php

namespace App\Services;

use App\Models\BarangayReference;
use App\Models\Project;
use App\Models\Site;
use App\Support\CoverageCache;
use App\Support\NameNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Barangay coverage (Plan: free WiFi installed/existing vs total barangays).
 *
 * A barangay counts as covered when at least one registered site sits in it
 * ("installed or existing"); `deployed` additionally requires an active
 * device deployment. Totals come from the barangay_references table so PSA
 * corrections flow straight into the percentages.
 */
class BarangayCoverageService
{
    public function coverage(array $filters = []): array
    {
        return CoverageCache::remember('barangay', $filters, fn () => $this->computeCoverage($filters));
    }

    private function computeCoverage(array $filters): array
    {
        // `district` is not a column on barangay_references, so a district filter
        // has to be resolved to the (province, municipality) pairs it covers —
        // otherwise the numerator shrinks while the denominator stays nationwide
        // and every percentage is wrong.
        $districtScope = $this->municipalitiesInDistrict($filters);

        $references = BarangayReference::query()
            ->when($filters['province'] ?? null, fn ($q, $v) => $q->where('province', $v))
            ->when($filters['municipality'] ?? null, fn ($q, $v) => $q->where('municipality', $v))
            ->when($districtScope !== null, function ($q) use ($districtScope) {
                if ($districtScope === []) {
                    // District selected but unknown to the lookup: match nothing
                    // rather than silently reporting nationwide totals.
                    return $q->whereRaw('1 = 0');
                }

                return $q->where(function ($q) use ($districtScope) {
                    foreach ($districtScope as $province => $municipalities) {
                        $q->orWhere(fn ($q2) => $q2
                            ->where('province', $province)
                            ->whereIn('municipality', $municipalities));
                    }
                });
            })
            ->get(['province', 'municipality', 'name_normalized']);

        // Site-attributed barangays, keyed by normalized name per municipality.
        $sites = Site::query()
            ->when($filters['province'] ?? null, fn ($q, $v) => $q->where('province', $v))
            ->when($filters['district'] ?? null, fn ($q, $v) => $q->where('district', $v))
            ->when($filters['municipality'] ?? null, fn ($q, $v) => $q->where('municipality', $v))
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->where('project_id', $v))
            ->with(['activeDeployments:id,site_id'])
            ->get(['id', 'province', 'district', 'municipality', 'barangay', 'project_id']);

        // (province, municipality, normalized barangay) => [sites, deployed].
        // Province is part of the key because municipality names repeat across
        // provinces (e.g. Quezon in Isabela and in Nueva Vizcaya) — keying on
        // municipality alone credited one province's sites to another.
        $siteIndex = [];
        foreach ($sites->filter(fn ($site) => trim((string) $site->barangay) !== '') as $site) {
            $key = ((string) $site->province).'|'.((string) $site->municipality).'|'.NameNormalizer::normalize($site->barangay);
            $siteIndex[$key] ??= ['sites' => 0, 'deployed' => false];
            $siteIndex[$key]['sites']++;
            $siteIndex[$key]['deployed'] = $siteIndex[$key]['deployed'] || $site->activeDeployments->isNotEmpty();
        }

        $rows = $references
            ->groupBy(fn ($r) => $r->province.'|'.$r->municipality)
            ->map(function ($group, $key) use ($siteIndex) {
                [$province, $municipality] = explode('|', $key);
                $covered = 0;
                $deployed = 0;
                $siteCount = 0;
                foreach ($group as $reference) {
                    $entry = $siteIndex[$province.'|'.$municipality.'|'.$reference->name_normalized] ?? null;
                    if ($entry) {
                        $covered++;
                        $siteCount += $entry['sites'];
                        $deployed += $entry['deployed'] ? 1 : 0;
                    }
                }
                $total = $group->count();

                return [
                    'province' => $province,
                    'municipality' => $municipality,
                    'total_barangays' => $total,
                    'covered' => $covered,
                    'deployed' => $deployed,
                    'remaining' => max(0, $total - $covered),
                    'sites' => $siteCount,
                    'coverage_pct' => $total > 0 ? round($covered / $total * 100, 1) : 0.0,
                ];
            })
            ->sortBy([['province', 'asc'], ['municipality', 'asc']])
            ->values()->all();

        $sum = fn (array $rows, string $key) => array_sum(array_column($rows, $key));
        $totalBarangays = $sum($rows, 'total_barangays');
        $totalCovered = $sum($rows, 'covered');
        $unattributed = $sites->filter(fn ($site) => trim((string) $site->barangay) === '')->count();

        // A district filter matches sites on sites.district, so sites whose
        // district is blank are invisible to it — report them so a low figure
        // can never masquerade as complete (Plan_revision §Phase 1.4).
        $districtBlank = 0;
        if ($districtScope !== null) {
            $districtBlank = Site::query()
                ->whereIn('province', array_keys($districtScope))
                ->where(fn ($q) => $q->whereNull('district')->orWhere('district', ''))
                ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->where('project_id', $v))
                ->count();
        }

        return [
            'filters' => collect($filters)->only(['project_id', 'province', 'district', 'municipality'])->filter()->all(),
            'rows' => $rows,
            'totals' => [
                'barangays' => $totalBarangays,
                'covered' => $totalCovered,
                'deployed' => $sum($rows, 'deployed'),
                'remaining' => max(0, $totalBarangays - $totalCovered),
                'sites' => $sum($rows, 'sites'),
                'coverage_pct' => $totalBarangays > 0 ? round($totalCovered / $totalBarangays * 100, 1) : 0.0,
            ],
            'unattributed_sites' => $unattributed,
            'scope' => $this->describeScope($filters),
            'district_blank_sites' => $districtBlank,
        ];
    }

    /**
     * Resolve a district filter to the municipalities it covers.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array<int, string>>|null null when no district filter
     */

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

    private function municipalitiesInDistrict(array $filters): ?array
    {
        if (empty($filters['district'])) {
            return null;
        }

        $map = [];
        foreach (DB::table('legislative_districts')
            ->when($filters['province'] ?? null, fn ($q, $v) => $q->where('province', $v))
            ->where('district', $filters['district'])
            ->get(['province', 'municipality']) as $row) {
            $map[(string) $row->province][] = (string) $row->municipality;
        }

        return $map;
    }
}
