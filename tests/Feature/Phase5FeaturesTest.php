<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use App\Services\ImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class Phase5FeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        return $admin;
    }

    private function project(): Project
    {
        return Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
        ]);
    }

    public function test_dashboard_includes_trend_and_wallboard_renders(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/wallboard')->assertOk();
    }

    public function test_heartbeat_upserts_today_status_for_site_code(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $project = $this->project();
        Site::create([
            'project_id' => $project->id,
            'location_name' => 'Probe Site',
            'latitude' => 14.6,
            'longitude' => 120.9,
            'ap_site_code' => 'AP-HB-1',
            'status' => 'active',
        ]);
        $tokenUser = User::factory()->create();
        $token = $tokenUser->createToken('probe')->plainTextToken;

        // First beat: DOWN.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', ['site_code' => 'AP-HB-1', 'status' => 'DOWN'])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'DOWN']);

        // Second beat same day: upserts, not duplicates.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', [
                'site_code' => 'AP-HB-1',
                'status' => 'UP',
                'bandwidth_mbps' => 12.5,
                'users' => 8,
            ])->assertOk();

        $site = Site::where('ap_site_code', 'AP-HB-1')->first();
        $this->assertSame(1, SiteDailyStatus::where('site_id', $site->id)->count());
        $today = SiteDailyStatus::where('site_id', $site->id)->first();
        $this->assertSame('UP', $today->status);
        $this->assertEquals('12.5', $today->bandwidth_utilization_mbps);

        // Unauthenticated probes rejected. Sanctum's guard caches the resolved
        // user per app instance, so forget it to simulate a fresh probe.
        Auth::guard('sanctum')->forgetUser();
        $this->flushHeaders();
        $this->postJson('/api/heartbeat', ['site_code' => 'AP-HB-1', 'status' => 'UP'])->assertUnauthorized();
    }

    public function test_ticket_lifecycle_through_http(): void
    {
        $admin = $this->admin();
        $project = $this->project();
        $site = Site::create([
            'project_id' => $project->id,
            'location_name' => 'Ticket Site',
            'latitude' => 14.6,
            'longitude' => 120.9,
        ]);

        $payload = [
            'title' => 'AP unreachable after brownout',
            'site_id' => $site->id,
            'priority' => 'critical',
            'category' => 'power',
        ];
        $this->actingAs($admin)->post('/tickets', $payload)->assertRedirect()->assertSessionHas('success');
        $ticket = MaintenanceTicket::where('title', $payload['title'])->sole();
        $this->assertSame($admin->id, $ticket->reported_by);
        $this->assertSame('OPEN', $ticket->status);

        // Resolve requires notes when provided via update endpoint rules.
        $this->actingAs($admin)
            ->put("/tickets/{$ticket->id}", ['status' => 'RESOLVED', 'resolution_notes' => 'Replaced PoE injector.'])
            ->assertRedirect();
        $fresh = $ticket->fresh();
        $this->assertSame('RESOLVED', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);

        // Viewer role lacks tickets.manage → blocked from index.
        $viewer = User::factory()->create();
        $viewer->roles()->attach(4);
        $this->actingAs($viewer)->get('/tickets')->assertForbidden();
    }

    public function test_device_asset_tag_survives_mysql_mode_import(): void
    {
        // Guards the driver-aware nextAssetTag() expression (MySQL path uses SUBSTRING/UNSIGNED).
        $this->seed(RolePermissionSeeder::class);
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
        ]);
        foreach (['FW-0001', 'FW-0007', 'FW-0002'] as $tag) {
            Device::create([
                'device_model_id' => $model->id,
                'asset_tag' => $tag,
                'serial_number' => "SN-{$tag}",
            ]);
        }

        $reflection = new \ReflectionClass(ImportService::class);
        $method = $reflection->getMethod('nextAssetTag');
        $method->setAccessible(true);

        $this->assertSame('FW-0008', $method->invoke(app(ImportService::class)));
    }
}
