<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Executive-summary bullets for the PDF family: plain sentences built only
 * from the numbers already on the page, so a reader gets the meaning without
 * re-deriving it. Every method is pure (data in, strings out) and stays
 * silent instead of guessing when its inputs are empty.
 */
class ReportNarrative
{
    private function plural(int $n, string $one, ?string $many = null): string
    {
        return $n === 1 ? $one : ($many ?? $one.'s');
    }

    public function forProject(array $analytics): array
    {
        $bullets = [
            "{$analytics['sites']['active']} of {$analytics['sites']['total']} sites are active; ".
            "{$analytics['daily']['reported']} reported on {$analytics['to']} ({$analytics['daily']['progress_pct']}%).",
            "Uptime {$analytics['uptime_pct']}% over {$analytics['from']} – {$analytics['to']} ({$analytics['uptime_base']} observed site-days).",
        ];
        $episodes = $analytics['down_episodes'];
        $bullets[] = $episodes->isNotEmpty()
            ? count($episodes).' open DOWN '.$this->plural(count($episodes), 'episode').'; longest at '.$episodes->first()['site'].' ('.$episodes->first()['duration_h'].'h).'
            : 'No open DOWN episodes in scope.';
        $bullets[] = "{$analytics['alerts']['active']} active ".$this->plural($analytics['alerts']['active'], 'alert')." ({$analytics['alerts']['critical']} critical); ".
            "{$analytics['tickets']['open']} open ".$this->plural($analytics['tickets']['open'], 'ticket').'.';

        return $bullets;
    }

    /** @param Collection|array $rollup rows with municipality/sites/up/up_pct */
    public function forProvince($rollup): array
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
        if (($coverage['unattributed_sites'] ?? 0) > 0) {
            $n = $coverage['unattributed_sites'];
            $bullets[] = $n === 1
                ? '1 site has no barangay recorded and is excluded from the figures.'
                : "{$n} sites have no barangay recorded and are excluded from the figures.";
        }

        return $bullets;
    }

    public function forOps(array $comparison): array
    {
        $current = $comparison['current'];
        $sign = fn ($v) => ($v >= 0 ? '+' : '').$v;
        $bullets = [
            "Uptime {$current['uptime_pct']}% ({$sign($comparison['delta_uptime'])} pts vs previous) on {$current['uptime_base']} observed site-days.",
            'Reporting progress '.$current['daily']['progress_pct'].'% at period end.',
        ];
        $bullets[] = $current['down_episodes']->isNotEmpty()
            ? count($current['down_episodes']).' open DOWN '.$this->plural(count($current['down_episodes']), 'episode').', longest '.$current['down_episodes']->first()['duration_h'].'h.'
            : 'No open DOWN episodes — the fleet closed or never opened any this window.';

        return $bullets;
    }

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
}
