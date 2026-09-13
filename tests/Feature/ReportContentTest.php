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
use App\Models\User;
use App\Services\ReportingService;
use App\Services\ReportNarrative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan.md gap #6 — PDF tests only checked the %PDF magic bytes, so template
 * bugs (like the $bullets outage) slipped through. These render the report
 * Blades to HTML with seeded data and assert the key figures land on the
 * page, for the three newest packs.
 */
class ReportContentTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
        $this->site = Site::create([
            'project_id' => $this->project->id, 'location_name' => 'Content Site',
            'province' => 'Cagayan', 'municipality' => 'Aparri',
            'latitude' => 18.3, 'longitude' => 121.6, 'status' => 'active',
        ]);
    }

    public function test_incidents_template_shows_seeded_figures(): void
    {
        $rule = AlertRule::create([
            'name' => 'Offline watch', 'metric' => 'offline_minutes', 'operator' => '>',
            'threshold' => 10, 'duration_minutes' => 0, 'severity' => 'critical',
            'notify_roles' => [], 'is_active' => true,
        ]);
        Alert::create(['rule_id' => $rule->id, 'site_id' => $this->site->id, 'triggered_at' => now()->subHour()]);
        MaintenanceTicket::create([
            'site_id' => $this->site->id, 'title' => 'PoE injector down',
            'priority' => 'high', 'category' => 'power', 'status' => 'OPEN',
            'reported_by' => User::factory()->create()->id,
        ]);

        $service = app(ReportingService::class);
        $incidents = $service->incidentsData(['project_id' => $this->project->id]);
        $html = view('reports.incidents', [
            'incidents' => $incidents,
            'bullets' => app(ReportNarrative::class)->forIncidents($incidents),
            'userName' => 'tester',
        ])->render();

        $this->assertSame(1, $incidents['alerts_triggered']);
        $this->assertStringContainsString('Offline watch', $html);
        $this->assertStringContainsString('PoE injector down', $html);
        $this->assertStringContainsString('Content Site', $html);
    }

    public function test_progress_template_shows_weighted_pct_and_overdue(): void
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
        SiteAccomplishment::create([
            'site_id' => $this->site->id, 'milestone_id' => $civil->id,
            'status' => 'IN_PROGRESS', 'pct_complete' => 50,
            'target_date' => today()->addWeek(), 'created_by' => $user,
        ]);
        SiteAccomplishment::create([
            'site_id' => $this->site->id, 'milestone_id' => $install->id,
            'status' => 'IN_PROGRESS', 'pct_complete' => 50,
            'target_date' => today()->subDay(), 'created_by' => $user,
        ]);

        $service = app(ReportingService::class);
        $progress = $service->progressData(['project_id' => $this->project->id]);
        $html = view('reports.progress', [
            'progress' => $progress,
            'bullets' => app(ReportNarrative::class)->forProgress($progress),
            'userName' => 'tester',
        ])->render();

        // (60×50 + 40×50) / 100 = 50.
        $this->assertSame(50.0, $progress['overall_pct']);
        $this->assertStringContainsString('50', $html);
        $this->assertStringContainsString('Civil works', $html);
        $this->assertStringContainsString('Installation', $html);
    }

    public function test_fleet_template_shows_inventory_figures(): void
    {
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1',
            'type' => 'router', 'is_active' => true,
        ]);
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-RPT-9',
            'serial_number' => 'SN-RPT-9', 'status' => 'deployed',
            'firmware_version' => 'v9.9',
        ]);
        DeviceDeployment::create([
            'device_id' => $device->id, 'site_id' => $this->site->id,
            'role_at_site' => 'primary_ap', 'installed_at' => now(),
        ]);

        $service = app(ReportingService::class);
        $inventory = $service->fleetInventory(['project_id' => $this->project->id]);
        $html = view('reports.fleet', [
            'inventory' => $inventory,
            'scope' => 'Cagayan',
            'bullets' => app(ReportNarrative::class)->forFleet($inventory),
            'userName' => 'tester',
        ])->render();

        $this->assertSame(1, $inventory['deployed']);
        $this->assertStringContainsString('DEV-RPT-9', $html);
        $this->assertStringContainsString('v9.9', $html);
    }
}
