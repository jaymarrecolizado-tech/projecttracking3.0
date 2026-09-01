<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceMetric;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\Site;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AggregateDeviceMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollup_buckets_metrics_hourly_and_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
        ]);
        $site = Site::create([
            'project_id' => $project->id, 'location_name' => 'Rollup Site',
            'latitude' => 17.6, 'longitude' => 121.7, 'status' => 'active',
        ]);
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
        ]);
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-RU-1',
            'serial_number' => 'SN-RU-1', 'status' => 'deployed',
        ]);

        $hour = now()->subHours(2)->startOfHour();
        foreach ([[10, 5], [30, 9], [20, 7]] as [$latency, $clients]) {
            DeviceMetric::create([
                'device_id' => $device->id, 'site_id' => $site->id,
                'ts' => $hour->copy()->addMinutes(10), 'latency_ms' => $latency, 'clients' => $clients,
            ]);
        }

        $this->artisan('metrics:aggregate')->expectsOutputToContain('hourly bucket');

        $bucket = DB::table('device_metric_hourlies')->where('device_id', $device->id)->first();
        $this->assertSame(3, (int) $bucket->samples);
        $this->assertEqualsWithDelta(20.0, (float) $bucket->latency_avg, 0.01);
        $this->assertSame(30.0, (float) $bucket->latency_max);
        $this->assertSame(9, (int) $bucket->clients_max);

        // Re-run: same bucket refreshed, never duplicated.
        $this->artisan('metrics:aggregate');
        $this->assertSame(1, DB::table('device_metric_hourlies')->count());
    }
}
