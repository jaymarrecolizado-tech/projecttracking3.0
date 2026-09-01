<?php

namespace App\Services;

use App\Models\BarangayReference;
use App\Models\Site;
use App\Support\NameNormalizer;

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
        $references = BarangayReference::query()
            ->when(! empty($filters['province']), fn ($q, $v) => $q->where('province', $v))
            ->when(! empty($filters['municipality']), fn ($q, $v) => $q->where('municipality', $v))
            ->get(['province', 'municipality', 'name_normalized']);

        // Site-attributed barangays, keyed by normalized name per municipality.
        $sites = Site::query()
            ->when(! empty($filters['province']), fn ($q, $v) => $q->where('province', $v))
            ->when(! empty($filters['district']), fn ($q, $v) => $q->where('district', $v))
            ->when(! empty($filters['municipality']), fn ($q, $v) => $q->where('municipality', $v))
            ->when(! empty($filters['project_id']), fn ($q, $v) => $q->where('project_id', $v))
            ->with(['activeDeployments:id,site_id'])
            ->get(['id', 'province', 'district', 'municipality', 'barangay', 'project_id']);

        // (municipality, normalized barangay) => [sites, deployed]
        $siteIndex = [];
        foreach ($sites->filter(fn ($site) => trim((string) $site->barangay) !== '') as $site) {
            $key = ((string) $site->municipality).'|'.NameNormalizer::normalize($site->barangay);
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
                    $entry = $siteIndex[$municipality.'|'.$reference->name_normalized] ?? null;
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
        ];
    }
}
