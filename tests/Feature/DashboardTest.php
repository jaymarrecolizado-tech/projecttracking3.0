<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\BarangayReference;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    private function site(array $attributes = []): Site
    {
        if (! isset($this->projectId)) {
            $this->projectId = Project::create([
                'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
                'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
            ])->id;
        }

        return Site::create(array_merge([
            'project_id' => $this->projectId,
            'location_name' => 'Dash Site',
            'province' => 'Cagayan',
            'municipality' => 'Aparri',
            'barangay' => 'Tobias',
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
        ], $attributes));
    }

    public function test_dashboard_renders_the_full_operations_bundle(): void
    {
        $admin = $this->admin();
        $downSite = $this->site(['location_name' => 'Down Site']);
        $this->site(['location_name' => 'Up Site']);

        SiteDailyStatus::create(['site_id' => $downSite->id, 'date' => today()->toDateString(), 'status' => 'DOWN']);
        // The observer opened this episode; backdate it to test duration math.
        SiteStatusEvent::whereNull('resolved_at')->update(['started_at' => now()->subHours(30), 'cause' => 'manual']);

        $rule = AlertRule::create([
            'name' => 'Offline rule', 'metric' => 'offline_minutes', 'operator' => '>',
            'threshold' => 10, 'duration_minutes' => 0, 'severity' => 'critical',
            'notify_roles' => [], 'is_active' => true,
        ]);
        Alert::create([
            'rule_id' => $rule->id, 'site_id' => $downSite->id,
            'triggered_at' => now(), 'context' => ['observed' => 45],
        ]);

        BarangayReference::create([
            'province' => 'Cagayan', 'municipality' => 'Aparri',
            'name' => 'Tobias', 'name_normalized' => 'tobias', 'psgc' => '0201505001',
        ]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('stats.active_sites', 2)
                ->where('stats.reported_today', 1)
                ->has('stats.trend')
                ->has('stats.network.sites_per_province')
                ->where('stats.barangay_coverage.barangays', 1)
                ->where('stats.barangay_coverage.covered', 1)
                ->has('stats.devices')
                ->has('stats.down_episodes', 1)
                ->where('stats.down_episodes.0.site', 'Down Site')
                ->where('stats.down_episodes.0.duration_h', 30)
                ->has('stats.active_alerts', 1)
                ->where('stats.active_alerts.0.severity', 'critical')
                ->has('stats.alert_counts')
                ->has('stats.site_type_totals'));
    }

    public function test_dashboard_counts_devices_and_warranty_window(): void
    {
        $admin = $this->admin();
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
        ]);
        Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-D-1',
            'serial_number' => 'SN-D-1', 'status' => 'deployed', 'warranty_until' => now()->addDays(30),
        ]);
        Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-S-1',
            'serial_number' => 'SN-S-1', 'status' => 'in_stock',
        ]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.devices.deployed', 1)
                ->where('stats.devices.in_stock', 1)
                ->where('stats.devices.warranty_expiring', 1));
    }
}
