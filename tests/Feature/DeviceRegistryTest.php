<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    private function deviceModel(string $type = 'outdoor_ap'): DeviceModel
    {
        return DeviceModel::create([
            'manufacturer' => 'Ubiquiti',
            'model_name' => 'LiteBeam',
            'model_number' => 'LBE-5AC',
            'type' => $type,
            'wifi_standard' => 'wifi5',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_device_index(): void
    {
        $admin = $this->admin();
        Device::create([
            'device_model_id' => $this->deviceModel()->id,
            'asset_tag' => 'FW-0001',
            'serial_number' => 'SN001',
            'status' => 'in_stock',
        ]);

        $this->actingAs($admin)->get('/devices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Devices/Index')
                ->where('counts.total', 1));
    }

    public function test_store_creates_deployment_when_registered_deployed(): void
    {
        $admin = $this->admin();
        $project = Project::first() ?? Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_icon' => 'wifi',
        ]);
        $site = Site::create([
            'project_id' => $project->id,
            'location_name' => 'Test Barangay Hall',
            'latitude' => 16.5, 'longitude' => 121.3,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post('/devices', [
            'device_model_id' => $this->deviceModel('router')->id,
            'asset_tag' => 'FW-0002',
            'serial_number' => 'SN002',
            'status' => 'deployed',
            'condition' => 'new',
            'site_id' => $site->id,
            'role_at_site' => 'primary_ap',
        ])->assertRedirect();

        $device = Device::where('asset_tag', 'FW-0002')->firstOrFail();
        $this->assertSame($site->id, $device->currentDeployment->site_id);
    }

    public function test_moving_a_device_between_sites_closes_old_deployment(): void
    {
        $admin = $this->admin();
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_icon' => 'wifi',
        ]);
        $siteA = Site::create(['project_id' => $project->id, 'location_name' => 'Site A', 'latitude' => 16.5, 'longitude' => 121.3]);
        $siteB = Site::create(['project_id' => $project->id, 'location_name' => 'Site B', 'latitude' => 16.6, 'longitude' => 121.4]);

        $device = Device::create([
            'device_model_id' => $this->deviceModel()->id,
            'asset_tag' => 'FW-0003',
            'serial_number' => 'SN003',
            'status' => 'in_stock',
        ]);

        $payload = fn (array $extra) => array_merge([
            'device_model_id' => $device->device_model_id,
            'asset_tag' => 'FW-0003',
            'serial_number' => 'SN003',
            'condition' => 'good',
        ], $extra);

        $this->actingAs($admin)->put("/devices/{$device->id}", $payload(['status' => 'deployed', 'site_id' => $siteA->id]))->assertRedirect();
        $this->actingAs($admin)->put("/devices/{$device->id}", $payload(['status' => 'deployed', 'site_id' => $siteB->id]))->assertRedirect();
        $this->actingAs($admin)->put("/devices/{$device->id}", $payload(['status' => 'in_stock']))->assertRedirect();

        $deployments = DeviceDeployment::where('device_id', $device->id)->orderBy('id')->get();
        $this->assertCount(2, $deployments);
        $this->assertEquals($siteA->id, $deployments[0]->site_id);
        $this->assertNotNull($deployments[0]->removed_at);
        $this->assertNotNull($deployments[1]->removed_at);
        $this->assertSame('in_stock', $device->fresh()->status);
    }

    public function test_user_without_permission_cannot_create_devices(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $viewer = User::factory()->create();
        $viewer->roles()->attach(4); // viewer

        $this->actingAs($viewer)->post('/devices', [
            'device_model_id' => $this->deviceModel()->id,
            'asset_tag' => 'FW-X',
            'serial_number' => 'SNX',
            'status' => 'in_stock',
        ])->assertForbidden();
        $this->assertDatabaseMissing('devices', ['asset_tag' => 'FW-X']);
    }
}
