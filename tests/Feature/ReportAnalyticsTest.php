<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\DeviceModel;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Site;
use App\Models\SiteAccomplishment;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use App\Models\User;
use App\Services\ReportAnalytics;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Plan.md analytics reports Phase 1 — one scope in, every KPI out.
 */
class ReportAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Site $siteA;

    private Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'code' => 'RPT', 'name' => 'Report Project', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
        $this->siteA = Site::create([
            'project_id' => $this->project->id, 'location_name' => 'Alpha',
            'province' => 'Cagayan', 'municipality' => 'Aparri', 'barangay' => 'Alpha',
            'latitude' => 18.3, 'longitude' => 121.6, 'status' => 'active',
        ]);
        $this->siteB = Site::create([
            'project_id' => $this->project->id, 'location_name' => 'Beta',
            'province' => 'Isabela', 'municipality' => 'Ilagan', 'barangay' => 'Beta',
            'latitude' => 17.1, 'longitude' => 122.0, 'status' => 'active',
        ]);
    }

    private function recordStatus(Site $site, string $date, string $status): void
    {
        SiteDailyStatus::create([
            'site_id' => $site->id, 'date' => $date, 'status' => $status,
            'entry_status' => 'DRAFT', 'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_kpis_follow_the_scope_and_the_uptime_formula(): void
    {
        $to = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        // Site A reports both days; site B only yesterday (NO_DATA today).
        $this->recordStatus($this->siteA, $yesterday, 'UP');
        $this->recordStatus($this->siteA, $to, 'DOWN');
        $this->recordStatus($this->siteB, $yesterday, 'NO_NMS');

        $analytics = app(ReportAnalytics::class)->for([
            'project_id' => $this->project->id,
            'from' => $yesterday, 'to' => $to,
        ]);

        $this->assertSame(2, $analytics['sites']['total']);
        $this->assertSame(2, $analytics['sites']['active']);
        $this->assertSame(1, $analytics['daily']['down']);
        $this->assertSame(1, $analytics['daily']['no_data']);
        $this->assertSame(50.0, $analytics['daily']['progress_pct']);

        // Uptime = UP / (UP + DOWN + NO_NMS + DOWN_SERVER) = 1/3.
        $this->assertSame(33.3, $analytics['uptime_pct']);
        $this->assertSame(3, $analytics['uptime_base']);
        $this->assertCount(2, $analytics['trend']);
    }

    public function test_geo_filter_narrows_every_kpi(): void
    {
        $to = today()->toDateString();
        $this->recordStatus($this->siteA, $to, 'UP');
        $this->recordStatus($this->siteB, $to, 'DOWN');

        $analytics = app(ReportAnalytics::class)->for([
            'project_id' => $this->project->id, 'province' => 'Cagayan',
        ]);

        $this->assertSame(1, $analytics['sites']['total']);
        $this->assertSame(1, $analytics['daily']['up']);
        $this->assertSame(0, $analytics['daily']['down']);
        $this->assertSame(100.0, $analytics['uptime_pct']);
        $this->assertStringContainsString('Cagayan', $analytics['scope']);
    }

    public function test_fleet_episodes_alerts_and_tickets_resolve_through_the_scope(): void
    {
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1',
            'type' => 'router', 'is_active' => true,
        ]);
        $inScope = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-RPT-1',
            'serial_number' => 'SN-RPT-1', 'status' => 'deployed',
        ]);
        DeviceDeployment::create([
            'device_id' => $inScope->id, 'site_id' => $this->siteA->id,
            'role_at_site' => 'primary_ap', 'installed_at' => now(),
        ]);
        Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-RPT-2',
            'serial_number' => 'SN-RPT-2', 'status' => 'in_stock',
        ]);

        SiteStatusEvent::create([
            'site_id' => $this->siteA->id, 'from_status' => 'UP', 'to_status' => 'DOWN',
            'started_at' => now()->subHours(5), 'cause' => 'heartbeat_lost',
        ]);
        $rule = AlertRule::create([
            'name' => 'Offline', 'metric' => 'offline_minutes', 'operator' => '>',
            'threshold' => 10, 'duration_minutes' => 0, 'severity' => 'critical',
            'notify_roles' => [], 'is_active' => true,
        ]);
        Alert::create([
            'rule_id' => $rule->id, 'site_id' => $this->siteA->id, 'triggered_at' => now(),
        ]);
        MaintenanceTicket::create([
            'site_id' => $this->siteA->id, 'title' => 'PoE injector down',
            'priority' => 'high', 'category' => 'power', 'status' => 'OPEN',
            'reported_by' => User::factory()->create()->id,
        ]);

        $analytics = app(ReportAnalytics::class)->for(['project_id' => $this->project->id]);

        $this->assertSame(1, $analytics['fleet']['deployed']);
        $this->assertSame(1, $analytics['fleet']['in_stock']);
        $this->assertCount(1, $analytics['down_episodes']);
        $this->assertSame(5, $analytics['down_episodes'][0]['duration_h']);
        $this->assertSame(1, $analytics['alerts']['active']);
        $this->assertSame(1, $analytics['alerts']['critical']);
        $this->assertSame(1, $analytics['tickets']['open']);
        $this->assertSame('PoE injector down', $analytics['tickets']['latest'][0]['title']);
    }

    public function test_project_pdf_generates_with_exec_annexes(): void
    {
        $user = User::factory()->create();
        $this->recordStatus($this->siteA, today()->toDateString(), 'UP');

        $pdf = app(ReportingService::class)->generateProjectSummaryPdf(
            $this->project, ['from' => today()->subDays(6)->toDateString()], $user->name,
        );

        $content = $pdf->output();
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_remaining_pdfs_generate_with_filters(): void
    {
        $this->recordStatus($this->siteA, today()->toDateString(), 'UP');
        $reporting = app(ReportingService::class);

        foreach ([
            $reporting->generateProvinceReport('Cagayan', $this->project->id, [], 'tester'),
            $reporting->generateSiteTypeCoverageReport(['province' => 'Cagayan', 'site_type' => 'PES', 'status' => 'active'], 'tester'),
            $reporting->generateBarangayCoverageReport(['province' => 'Cagayan', 'municipality' => 'Aparri'], 'tester'),
        ] as $pdf) {
            $this->assertStringStartsWith('%PDF', $pdf->output());
        }
    }

    public function test_ops_comparison_deltas_across_windows(): void
    {
        $curr = today()->toDateString();
        $this->recordStatus($this->siteA, today()->subDays(10)->toDateString(), 'UP');
        $this->recordStatus($this->siteA, $curr, 'DOWN');

        $comparison = app(ReportingService::class)->opsPeriodComparison([
            'project_id' => $this->project->id,
            'from' => today()->subDays(6)->toDateString(), 'to' => $curr,
        ]);

        $this->assertSame(0.0, $comparison['current']['uptime_pct']);
        $this->assertSame(100.0, $comparison['previous']['uptime_pct']);
        $this->assertSame(-100.0, $comparison['delta_uptime']);
        $this->assertSame(1, $comparison['delta_down']);
    }

    public function test_fleet_inventory_flags_outdated_firmware(): void
    {
        config()->set('monitoring.approved_firmware', ['v2.0']);
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1',
            'type' => 'router', 'is_active' => true,
        ]);
        foreach ([['t1', 'v2.0', 'deployed'], ['t2', 'v2.0', 'deployed'], ['t3', 'v1.0', 'deployed'], ['t4', null, 'in_stock']] as [$tag, $fw, $status]) {
            Device::create([
                'device_model_id' => $model->id, 'asset_tag' => "DEV-FW-{$tag}",
                'serial_number' => "SN-FW-{$tag}", 'firmware_version' => $fw, 'status' => $status,
            ]);
        }

        $inventory = app(ReportingService::class)->fleetInventory([]);

        $this->assertSame(3, $inventory['deployed']);
        $this->assertSame(1, $inventory['in_stock']);
        $this->assertSame(1, $inventory['outdated']);
        $this->assertCount(4, $inventory['register']);
    }

    public function test_site_type_appendix_is_not_capped(): void
    {
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1',
            'type' => 'router', 'is_active' => true,
        ]);
        for ($i = 0; $i < 205; $i++) {
            $site = Site::create([
                'project_id' => $this->project->id, 'location_name' => "Cap Site {$i}",
                'ap_site_code' => "CAP-{$i}", 'province' => 'Cagayan',
                'latitude' => 18.3, 'longitude' => 121.6, 'status' => 'active',
            ]);
            $device = Device::create([
                'device_model_id' => $model->id, 'asset_tag' => "DEV-CAP-{$i}",
                'serial_number' => "SN-CAP-{$i}", 'status' => 'deployed',
            ]);
            DeviceDeployment::create([
                'device_id' => $device->id, 'site_id' => $site->id,
                'role_at_site' => 'primary_ap', 'installed_at' => now(),
            ]);
        }

        // The old 200-row gate would have returned an empty appendix here.
        $this->assertCount(205, app(ReportingService::class)->siteTypeAppendix([]));
    }

    public function test_incidents_pack_computes_mtta_mttr_and_backlog(): void
    {
        $now = now();
        $rule = AlertRule::create([
            'name' => 'Offline', 'metric' => 'offline_minutes', 'operator' => '>',
            'threshold' => 10, 'duration_minutes' => 0, 'severity' => 'critical',
            'notify_roles' => [], 'is_active' => true,
        ]);
        Alert::create([
            'rule_id' => $rule->id, 'site_id' => $this->siteA->id,
            'triggered_at' => $now->copy()->subHours(10),
            'acknowledged_at' => $now->copy()->subHours(8),
            'resolved_at' => $now->copy()->subHours(5),
        ]);
        Alert::create([
            'rule_id' => $rule->id, 'site_id' => $this->siteA->id,
            'triggered_at' => $now->copy()->subHours(2),
        ]);

        $reporter = User::factory()->create()->id;
        $done = MaintenanceTicket::create([
            'site_id' => $this->siteA->id, 'title' => 'Fixed link',
            'priority' => 'medium', 'category' => 'connectivity', 'status' => 'RESOLVED',
            'reported_by' => $reporter, 'resolved_at' => $now->copy()->subHour(),
        ]);
        $done->created_at = $now->copy()->subHours(6);
        $done->save();
        MaintenanceTicket::create([
            'site_id' => $this->siteA->id, 'title' => 'Open fault',
            'priority' => 'high', 'category' => 'power', 'status' => 'OPEN',
            'reported_by' => $reporter,
        ]);

        $data = app(ReportingService::class)->incidentsData(['project_id' => $this->project->id]);

        $this->assertSame(2, $data['alerts_triggered']);
        $this->assertSame(['critical' => 2], $data['by_severity']);
        $this->assertSame(2.0, $data['mtta_h']);
        $this->assertSame(5.0, $data['mttr_alerts_h']);
        $this->assertSame(1, $data['tickets_open']);
        $this->assertSame(['high' => 1], $data['tickets_by_priority']);
        $this->assertSame(5.0, $data['mttr_tickets_h']);
        $this->assertCount(1, $data['open_alerts']);
        $this->assertCount(1, $data['open_tickets']);
    }

    public function test_progress_pack_weights_milestones_and_lists_overdue(): void
    {
        $user = User::factory()->create()->id;
        $civil = ProjectMilestone::create([
            'project_id' => $this->project->id, 'milestone_name' => 'Civil works',
            'milestone_order' => 1, 'weight_pct' => 60,
        ]);
        $install = ProjectMilestone::create([
            'project_id' => $this->project->id, 'milestone_name' => 'Installation',
            'milestone_order' => 2, 'weight_pct' => 40,
        ]);
        foreach ([[$this->siteA, $civil, 100, 'IN_PROGRESS', today()->addWeek()],
            [$this->siteB, $civil, 50, 'IN_PROGRESS', today()->addWeek()],
            [$this->siteA, $install, 50, 'IN_PROGRESS', today()->subDay()],
        ] as [$site, $milestone, $pct, $status, $target]) {
            SiteAccomplishment::create([
                'site_id' => $site->id, 'milestone_id' => $milestone->id,
                'status' => $status, 'pct_complete' => $pct,
                'target_date' => $target, 'created_by' => $user,
            ]);
        }

        $data = app(ReportingService::class)->progressData(['project_id' => $this->project->id]);

        // (60×75 + 40×50) / 100 = 65.
        $this->assertSame(65.0, $data['overall_pct']);
        $this->assertCount(1, $data['projects']);
        $this->assertSame(75.0, $data['projects'][0]['milestones'][0]['avg_pct']);
        $this->assertCount(1, $data['overdue']);
        $this->assertSame('Installation', $data['overdue'][0]['milestone']);
        $this->assertSame(1, $data['overdue'][0]['days_overdue']);
    }

    public function test_ops_and_fleet_pdfs_generate(): void
    {
        $this->recordStatus($this->siteA, today()->toDateString(), 'UP');
        $reporting = app(ReportingService::class);

        $this->assertStringStartsWith('%PDF', $reporting->generateOpsPeriodReport(
            ['project_id' => $this->project->id], 'tester')->output());
        $this->assertStringStartsWith('%PDF', $reporting->generateFleetReport([], 'tester')->output());
        $this->assertStringStartsWith('%PDF', $reporting->generateIncidentsReport(
            ['project_id' => $this->project->id], 'tester')->output());
        $this->assertStringStartsWith('%PDF', $reporting->generateProgressReport(
            ['project_id' => $this->project->id], 'tester')->output());
    }

    public function test_combined_pack_renders_selected_sections(): void
    {
        $this->recordStatus($this->siteA, today()->toDateString(), 'UP');
        $pdf = app(ReportingService::class)->generateCombinedReport(
            ['project_id' => $this->project->id], ['ops_period', 'progress'], 'tester');

        $this->assertStringStartsWith('%PDF', $pdf->output());
    }

    public function test_combined_rejects_an_empty_section_set(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ReportingService::class)->generateCombinedReport([], ['nope']);
    }
}
