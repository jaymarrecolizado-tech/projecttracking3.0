<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Executive-summary bullets for the PDF family: plain sentences built only
 * from the numbers already on the page, so a reader gets the meaning without
 * re-deriving it. Every method is pure (data in, strings out) and stays
 * silent instead of guessing when its inputs are empty.
 *
 * The inputs are the exact bundles the reporting services build, imported as
 * shapes rather than re-declared — a narrative that reads a key the data no
 * longer produces is exactly how a wrong number gets a confident sentence.
 *
 * @phpstan-import-type Bundle from ReportAnalytics
 * @phpstan-import-type SiteCoverage from SiteCoverageService
 * @phpstan-import-type BarangayCoverage from BarangayCoverageService
 * @phpstan-import-type Comparison from ReportingService
 * @phpstan-import-type Inventory from ReportingService
 * @phpstan-import-type Incidents from ReportingService
 * @phpstan-import-type Progress from ReportingService
 * @phpstan-import-type Satisfaction from ReportingService
 */
class ReportNarrative
{
    private function plural(int $n, string $one, ?string $many = null): string
    {
        return $n === 1 ? $one : ($many ?? $one.'s');
    }

    /**
     * @param  Bundle  $analytics
     * @return list<string>
     */
    public function forProject(array $analytics): array
    {
        $bullets = [
            "{$analytics['sites']['active']} of {$analytics['sites']['total']} sites are active; ".
            "{$analytics['daily']['reported']} reported on {$analytics['to']} ({$analytics['daily']['progress_pct']}%).",
            "Uptime {$analytics['uptime_pct']}% over {$analytics['from']} – {$analytics['to']} ({$analytics['uptime_base']} observed site-days).",
        ];
        $episodes = $analytics['down_episodes'];
        $bullets[] = $episodes !== []
            ? count($episodes).' open DOWN '.$this->plural(count($episodes), 'episode').'; longest at '.$episodes[0]['site'].' ('.$episodes[0]['duration_h'].'h).'
            : 'No open DOWN episodes in scope.';
        $bullets[] = "{$analytics['alerts']['active']} active ".$this->plural($analytics['alerts']['active'], 'alert')." ({$analytics['alerts']['critical']} critical); ".
            "{$analytics['tickets']['open']} open ".$this->plural($analytics['tickets']['open'], 'ticket').'.';

        return $bullets;
    }

    /**
     * @param  Collection<int, array{municipality: string|int, sites: int, up: int, up_pct: float}>|list<array{municipality: string|int, sites: int, up: int, up_pct: float}>  $rollup
     * @return list<string>
     */
    public function forProvince(Collection|array $rollup): array
    {
        $rows = collect($rollup);
        if ($rows->isEmpty()) {
            return ['No sites in scope.'];
        }
        $bullets = [
            "{$rows->sum('sites')} ".$this->plural($rows->sum('sites'), 'site')." across {$rows->count()} ".$this->plural($rows->count(), 'municipality', 'municipalities').'; '.
            "{$rows->sum('up')} reporting UP.",
        ];
        if ($rows->count() > 1) {
            $best = $rows->sortByDesc('up_pct')->first();
            $worst = $rows->sortBy('up_pct')->first();
            $bullets[] = "Highest UP share: {$best['municipality']} ({$best['up_pct']}%). ".
                "Lowest: {$worst['municipality']} ({$worst['up_pct']}%).";
        }

        return $bullets;
    }

    /**
     * @param  SiteCoverage  $coverage
     * @return list<string>
     */
    public function forSiteType(array $coverage): array
    {
        $totals = $coverage['totals'];
        $bullets = [
            "{$totals['actual']} of {$totals['registered']} registered sites have a deployed device ({$totals['coverage_pct']}%).",
        ];
        $gaps = collect($coverage['rows'])->sortByDesc('gap')->first();
        if ($gaps && $gaps['gap'] > 0) {
            $bullets[] = "Largest gap: {$gaps['label']} ({$gaps['gap']} ".$this->plural($gaps['gap'], 'site').' without a deployed device).';
        }

        return $bullets;
    }

    /**
     * @param  BarangayCoverage  $coverage
     * @return list<string>
     */
    public function forBarangay(array $coverage): array
    {
        $totals = $coverage['totals'];
        $bullets = [
            "{$totals['covered']} of {$totals['barangays']} barangays have Free WiFi ({$totals['coverage_pct']}%) — {$totals['remaining']} ".$this->plural($totals['remaining'], 'remains', 'remain').'.',
        ];
        if ($totals['remaining'] === 0 && $totals['barangays'] > 0) {
            $bullets[] = 'Full coverage — every reference barangay in scope has presence.';
        }
        $registeredOnly = $totals['covered'] - $totals['deployed'];
        if ($registeredOnly > 0) {
            $bullets[] = "{$totals['deployed']} of the covered barangays have an actively deployed device; ".
                "{$registeredOnly} ".$this->plural($registeredOnly, 'relies', 'rely').' on registered presence only.';
        }
        if ($coverage['unattributed_sites'] > 0) {
            $n = $coverage['unattributed_sites'];
            $bullets[] = $n === 1
                ? '1 site has no barangay recorded and is excluded from the figures.'
                : "{$n} sites have no barangay recorded and are excluded from the figures.";
        }

        return $bullets;
    }

    /**
     * @param  Comparison  $comparison
     * @return list<string>
     */
    public function forOps(array $comparison): array
    {
        $current = $comparison['current'];
        $sign = fn ($v) => ($v >= 0 ? '+' : '').$v;
        $bullets = [
            "Uptime {$current['uptime_pct']}% ({$sign($comparison['delta_uptime'])} pts vs previous) on {$current['uptime_base']} observed site-days.",
            'Reporting progress '.$current['daily']['progress_pct'].'% at period end.',
        ];
        $bullets[] = $current['down_episodes'] !== []
            ? count($current['down_episodes']).' open DOWN '.$this->plural(count($current['down_episodes']), 'episode').', longest '.$current['down_episodes'][0]['duration_h'].'h.'
            : 'No open DOWN episodes — the fleet closed or never opened any this window.';

        // Only claim a verdict when a target exists. Saying "no SLA is set" is
        // the honest line; printing PASS/FAIL against an invented number would
        // be the kind of figure that gets quoted in a briefing.
        $bullets[] = ($current['sla_target'] ?? null) === null
            ? 'No uptime SLA target is configured, so this period is not marked pass or fail — set SLA_UPTIME_TARGET once DICT confirms the figure.'
            : ($current['sla_met']
                ? "Meets the {$current['sla_target']}% uptime SLA."
                : "Below the {$current['sla_target']}% uptime SLA.");

        return $bullets;
    }

    /**
     * @param  Inventory  $inventory
     * @return list<string>
     */
    public function forFleet(array $inventory): array
    {
        $bullets = [
            "{$inventory['deployed']} deployed, {$inventory['in_stock']} in stock, {$inventory['under_repair']} under repair; ".
            "{$inventory['warranty_expiring']} ".$this->plural($inventory['warranty_expiring'], 'warranty expires', 'warranties expire').' within 90 days.',
        ];
        if (empty($inventory['approved_firmware'])) {
            $bullets[] = 'No approved firmware list is configured, so outdated units cannot be flagged — set APPROVED_FIRMWARE.';
        } elseif (($inventory['outdated'] ?? 0) > 0) {
            $bullets[] = "{$inventory['outdated']} deployed ".$this->plural($inventory['outdated'], 'unit runs', 'units run').' firmware outside the approved list.';
        } else {
            $bullets[] = 'All deployed units run approved firmware.';
        }

        return $bullets;
    }

    /**
     * @param  Incidents  $incidents
     * @return list<string>
     */
    public function forIncidents(array $incidents): array
    {
        $fmt = fn ($v, $unit) => $v === null ? '—' : $v.$unit;
        $bullets = [
            "{$incidents['alerts_triggered']} ".$this->plural($incidents['alerts_triggered'], 'alert').' triggered in window; '.
            "MTTA {$fmt($incidents['mtta_h'], 'h')}, alert MTTR {$fmt($incidents['mttr_alerts_h'], 'h')}.",
            "{$incidents['tickets_open']} open ".$this->plural($incidents['tickets_open'], 'ticket')."; ticket MTTR {$fmt($incidents['mttr_tickets_h'], 'h')}.",
        ];
        if ($incidents['tickets_open'] > 0) {
            $top = collect($incidents['tickets_by_priority'])->sortKeysDesc()->keys()->first();
            $bullets[] = "Highest open priority: {$top}.";
        }

        return $bullets;
    }

    /**
     * @param  Progress  $progress
     * @return list<string>
     */
    public function forProgress(array $progress): array
    {
        $bullets = [
            "{$progress['overall_pct']}% weighted accomplishment across ".count($progress['projects']).' '.$this->plural(count($progress['projects']), 'project').'.',
        ];
        if (! empty($progress['overdue'])) {
            $oldest = collect($progress['overdue'])->sortByDesc('days_overdue')->first();
            $bullets[] = count($progress['overdue']).' '.$this->plural(count($progress['overdue']), 'overdue item', 'overdue items')."; oldest is {$oldest['milestone']} at {$oldest['site']} ({$oldest['days_overdue']}d late).";
        } else {
            $bullets[] = 'Nothing overdue in scope.';
        }

        return $bullets;
    }

    /**
     * Satisfaction: anonymous ratings from people connected at the sites in
     * scope. Stays silent about the fleet average when there is not enough
     * data to mean anything — a score nobody can act on is worse than none.
     *
     * @param  array<string, mixed>  $satisfaction
     * @return list<string>
     */
    public function forSatisfaction(array $satisfaction): array
    {
        $responses = $satisfaction['responses'];

        if ($responses === 0) {
            return ['No survey responses in this scope or period — print the QR placards (Sites → Survey QR sheet) to start collecting.'];
        }

        $bullets = [
            $responses.' anonymous '.$this->plural($responses, 'response').' over '.$satisfaction['window_days'].' '.$this->plural($satisfaction['window_days'], 'day').'.',
        ];

        $bullets[] = $satisfaction['scope_mean'] === null
            ? 'No rating values were submitted — only free text.'
            : sprintf('Mean rating %.1f of 5 across all answers.', $satisfaction['scope_mean']);

        $bullets[] = 'Rated sites: '.$satisfaction['rated_sites'].' (needs '.$satisfaction['min_responses'].'+ responses each); '
            .$satisfaction['unrated_sites'].' below the minimum.';

        if (! empty($satisfaction['low'])) {
            $worst = $satisfaction['low'][0];
            $bullets[] = 'Lowest rated: '.$worst['site'].' ('.$worst['overall'].'/5 from '.$worst['responses'].' '.$this->plural($worst['responses'], 'response').').';
        }

        if (! empty($satisfaction['providers'])) {
            $bullets[] = 'A rating cannot confirm who is connected — see the provider rollup for the split.';
        }

        return $bullets;
    }
}
