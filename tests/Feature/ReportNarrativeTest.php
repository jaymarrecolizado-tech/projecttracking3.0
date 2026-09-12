<?php

namespace Tests\Feature;

use App\Services\ReportNarrative;
use Tests\TestCase;

/**
 * Executive-summary bullets: plain sentences from the numbers on the page.
 * Pure functions — no database, every branch pinned.
 */
class ReportNarrativeTest extends TestCase
{
    public function test_barangay_full_coverage_reads_as_done(): void
    {
        $bullets = app(ReportNarrative::class)->forBarangay([
            'totals' => ['covered' => 6, 'barangays' => 6, 'deployed' => 6, 'remaining' => 0, 'coverage_pct' => 100.0],
            'unattributed_sites' => 0,
        ]);

        $this->assertSame('6 of 6 barangays have Free WiFi (100%) — 0 remain.', $bullets[0]);
        $this->assertStringContainsString('Full coverage', $bullets[1]);
        $this->assertCount(2, $bullets);
    }

    public function test_barangay_gaps_name_remaining_and_unlisted_sites(): void
    {
        $bullets = app(ReportNarrative::class)->forBarangay([
            'totals' => ['covered' => 4, 'barangays' => 10, 'deployed' => 2, 'remaining' => 6, 'coverage_pct' => 40.0],
            'unattributed_sites' => 1,
        ]);

        $this->assertSame('4 of 10 barangays have Free WiFi (40%) — 6 remain.', $bullets[0]);
        $this->assertStringContainsString('2 of the covered barangays', $bullets[1]);
        $this->assertStringContainsString('1 site has no barangay recorded and is excluded', $bullets[2]);
    }

    public function test_project_names_longest_episode_or_clean_state(): void
    {
        $narrative = app(ReportNarrative::class);
        $base = [
            'sites' => ['active' => 5, 'total' => 6],
            'daily' => ['reported' => 4, 'progress_pct' => 80.0],
            'to' => '2026-09-11', 'from' => '2026-09-05',
            'uptime_pct' => 90.0, 'uptime_base' => 10,
            'alerts' => ['active' => 1, 'critical' => 0],
            'tickets' => ['open' => 2],
        ];

        $with = $narrative->forProject($base + ['down_episodes' => collect([['site' => 'Alpha', 'duration_h' => 30]])]);
        $this->assertStringContainsString('1 open DOWN episode; longest at Alpha (30h).', $with[2]);

        $clean = $narrative->forProject($base + ['down_episodes' => collect()]);
        $this->assertSame('No open DOWN episodes in scope.', $clean[2]);
    }

    public function test_province_compares_municipalities_only_when_plural(): void
    {
        $narrative = app(ReportNarrative::class);

        $single = $narrative->forProvince([['municipality' => 'Basco', 'sites' => 6, 'up' => 6, 'up_pct' => 100.0]]);
        $this->assertCount(1, $single);

        $multi = $narrative->forProvince([
            ['municipality' => 'Basco', 'sites' => 6, 'up' => 6, 'up_pct' => 100.0],
            ['municipality' => 'Uyugan', 'sites' => 4, 'up' => 1, 'up_pct' => 25.0],
        ]);
        $this->assertStringContainsString('Highest UP share: Basco (100%).', $multi[1]);
        $this->assertStringContainsString('Lowest: Uyugan (25%).', $multi[1]);
    }

    public function test_ops_signs_deltas_and_fleet_flags_firmware(): void
    {
        $narrative = app(ReportNarrative::class);

        $ops = $narrative->forOps([
            'current' => ['uptime_pct' => 80.0, 'uptime_base' => 20,
                'daily' => ['progress_pct' => 90.0], 'down_episodes' => collect()],
            'delta_uptime' => -5.5, 'delta_sitedays' => 3, 'delta_down' => 1,
        ]);
        $this->assertStringContainsString('80% (-5.5 pts vs previous)', $ops[0]);

        $noList = $narrative->forFleet([
            'deployed' => 3, 'in_stock' => 1, 'under_repair' => 0,
            'warranty_expiring' => 1, 'approved_firmware' => [], 'outdated' => null,
        ]);
        $this->assertStringContainsString('1 warranty expires within 90 days.', $noList[0]);
        $this->assertStringContainsString('APPROVED_FIRMWARE', $noList[1]);

        $bad = $narrative->forFleet([
            'deployed' => 3, 'in_stock' => 0, 'under_repair' => 0,
            'warranty_expiring' => 0, 'approved_firmware' => ['v2.0'], 'outdated' => 2,
        ]);
        $this->assertStringContainsString('2 deployed units run firmware outside the approved list.', $bad[1]);
    }

    public function test_incidents_and_progress_handle_empty_states(): void
    {
        $narrative = app(ReportNarrative::class);

        $incidents = $narrative->forIncidents([
            'alerts_triggered' => 0, 'mtta_h' => null, 'mttr_alerts_h' => null,
            'tickets_open' => 0, 'mttr_tickets_h' => null, 'tickets_by_priority' => [],
        ]);
        $this->assertStringContainsString('MTTA —', $incidents[0]);
        $this->assertCount(2, $incidents);

        $progress = $narrative->forProgress(['overall_pct' => 0.0, 'projects' => [], 'overdue' => []]);
        $this->assertSame('Nothing overdue in scope.', $progress[1]);
    }
}
