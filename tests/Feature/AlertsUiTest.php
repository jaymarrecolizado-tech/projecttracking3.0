<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private int $projectId;

    private function alert(array $ruleAttributes = [], array $alertAttributes = []): Alert
    {
        if (! isset($this->projectId)) {
            $this->projectId = Project::create([
                'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
                'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
            ])->id;
        }
        $site = Site::create([
            'project_id' => $this->projectId, 'location_name' => 'Alert Site',
            'latitude' => 17.6, 'longitude' => 121.7, 'status' => 'active',
        ]);
        $rule = AlertRule::create(array_merge([
            'name' => 'Test rule', 'metric' => 'latency_ms', 'operator' => '>',
            'threshold' => 150, 'duration_minutes' => 0, 'severity' => 'warning',
            'notify_roles' => ['daily.approve'], 'is_active' => true,
        ], $ruleAttributes));

        return Alert::create(array_merge([
            'rule_id' => $rule->id, 'site_id' => $site->id,
            'triggered_at' => now(), 'context' => ['observed' => 300],
        ], $alertAttributes));
    }

    public function test_approvers_can_view_acknowledge_and_resolve(): void
    {
        $approver = User::factory()->create();
        $approver->roles()->attach(1); // admin holds daily.approve
        $alert = $this->alert();

        $this->actingAs($approver)->get(route('alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 1)
                ->where('canManageRules', true));

        $this->post(route('alerts.acknowledge', $alert))->assertRedirect();
        $this->assertNotNull($alert->fresh()->acknowledged_at);
        $this->assertSame($approver->id, $alert->fresh()->acknowledged_by);

        $this->post(route('alerts.resolve', $alert))->assertRedirect();
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_users_without_daily_approve_cannot_access(): void
    {
        $viewer = User::factory()->create();
        $viewer->roles()->attach(4);
        $alert = $this->alert();

        $this->actingAs($viewer)->get(route('alerts.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('alerts.acknowledge', $alert))->assertForbidden();
        $this->assertNull($alert->fresh()->acknowledged_at);
    }

    public function test_non_admin_cannot_manage_rules(): void
    {
        $encoder = User::factory()->create();
        $encoder->roles()->attach(3);

        $this->actingAs($encoder)
            ->post(route('alert-rules.store'), ['name' => 'X', 'metric' => 'latency_ms', 'operator' => '>', 'threshold' => 1, 'duration_minutes' => 0, 'severity' => 'info', 'is_active' => true])
            ->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_rules(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(1);
        $alert = $this->alert();
        $rule = $alert->rule;

        $this->actingAs($admin)
            ->post(route('alert-rules.store'), [
                'name' => 'Battery sag', 'metric' => 'battery_v', 'operator' => '<', 'threshold' => 11.8,
                'duration_minutes' => 0, 'severity' => 'critical', 'notify_roles' => ['daily.approve'], 'is_active' => true,
            ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('alert_rules', ['name' => 'Battery sag']);

        $this->actingAs($admin)
            ->put(route('alert-rules.update', $rule), [
                'name' => 'Test rule', 'metric' => 'latency_ms', 'operator' => '>', 'threshold' => 200,
                'duration_minutes' => 15, 'severity' => 'warning', 'notify_roles' => ['daily.approve'], 'is_active' => false,
            ])->assertRedirect();
        $this->assertSame(200.0, (float) $rule->fresh()->threshold);
        $this->assertFalse($rule->fresh()->is_active);

        $this->actingAs($admin)->delete(route('alert-rules.destroy', $rule))->assertRedirect();
        $this->assertDatabaseMissing('alert_rules', ['id' => $rule->id]);
    }

    public function test_active_alerts_feed_counts_on_index(): void
    {
        $approver = User::factory()->create();
        $approver->roles()->attach(1);
        $this->alert(['name' => 'Critical offline rule', 'severity' => 'critical']);
        $this->alert(['name' => 'Latency rule'], ['resolved_at' => now()]);

        $this->actingAs($approver)->get(route('alerts.index', ['state' => 'active']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('alerts.data', 1));
        $this->assertSame(1, Alert::whereNull('resolved_at')->count());
    }
}
