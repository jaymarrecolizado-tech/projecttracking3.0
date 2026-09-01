<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceMetric;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function probeToken(): string
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();

        return $user->createToken('probe')->plainTextToken;
    }

    private function site(): Site
    {
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
        ]);

        return Site::create([
            'project_id' => $project->id,
            'location_name' => 'Telemetry Site',
            'ap_site_code' => 'AP-TM-1',
            'latitude' => 14.6,
            'longitude' => 120.9,
            'status' => 'active',
        ]);
    }

    private function device(): Device
    {
        $model = DeviceModel::create([
            'manufacturer' => 'Ubiquiti', 'model_name' => 'LiteBeam', 'model_number' => 'LBE-5AC',
            'type' => 'outdoor_ap', 'is_active' => true,
        ]);

        return Device::create([
            'device_model_id' => $model->id,
            'asset_tag' => 'DEV-0001',
            'serial_number' => 'SN-TELEMETRY-1',
            'status' => 'deployed',
        ]);
    }

    public function test_heartbeat_with_telemetry_writes_device_metric_row(): void
    {
        $token = $this->probeToken();
        $site = $this->site();
        $device = $this->device();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', [
                'site_code' => 'AP-TM-1',
                'status' => 'UP',
                'device_serial' => 'SN-TELEMETRY-1',
                'uptime_s' => 86400,
                'cpu_pct' => 12.5,
                'wan_latency_ms' => 23,
                'users' => 14,
                'bw_rx_mbps' => 18.4,
                'bw_tx_mbps' => 6.2,
                'power' => ['source' => 'solar', 'battery_v' => 13.1, 'solar_w' => 45],
                'firmware' => 'v2.1',
            ])->assertOk();

        $metric = DeviceMetric::where('device_id', $device->id)->sole();
        $this->assertSame($site->id, $metric->site_id);
        $this->assertSame(86400, $metric->uptime_s);
        $this->assertSame(23, $metric->latency_ms);
        $this->assertSame(14, $metric->clients);
        $this->assertSame('solar', $metric->power_source);
        $this->assertEquals('13.1', $metric->battery_v);
        $this->assertSame('v2.1', $metric->firmware);

        // Daily status row still upserted alongside the series point.
        $this->assertSame('UP', SiteDailyStatus::where('site_id', $site->id)->whereDate('date', today())->first()->status);
    }

    public function test_plain_heartbeat_does_not_grow_metrics_table(): void
    {
        $token = $this->probeToken();
        $site = $this->site();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', ['site_code' => 'AP-TM-1', 'status' => 'DOWN'])
            ->assertOk();

        $this->assertSame(0, DeviceMetric::count());
    }

    public function test_unknown_device_serial_is_rejected(): void
    {
        $token = $this->probeToken();
        $this->site();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/heartbeat', [
                'site_code' => 'AP-TM-1',
                'status' => 'UP',
                'device_serial' => 'SN-UNKNOWN',
            ])->assertStatus(422);
    }

    public function test_metrics_prune_deletes_only_rows_past_retention(): void
    {
        $site = $this->site();
        $device = $this->device();

        $old = DeviceMetric::create(['device_id' => $device->id, 'site_id' => $site->id, 'ts' => now()->subDays(120), 'latency_ms' => 100]);
        $recent = DeviceMetric::create(['device_id' => $device->id, 'site_id' => $site->id, 'ts' => now()->subDays(10), 'latency_ms' => 20]);

        $this->artisan('metrics:prune', ['--days' => 90])->expectsOutputToContain('device_metrics');

        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());
    }
}
