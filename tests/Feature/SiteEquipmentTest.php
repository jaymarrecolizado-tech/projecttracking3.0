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

class SiteEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private int $projectId;

    private function site(): Site
    {
        if (! isset($this->projectId)) {
            $this->projectId = Project::create([
                'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
                'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
            ])->id;
        }

        return Site::create([
            'project_id' => $this->projectId, 'location_name' => 'Equipment Site',
            'latitude' => 17.6, 'longitude' => 121.7, 'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    private function model(): DeviceModel
    {
        return DeviceModel::create([
            'manufacturer' => 'Ubiquiti', 'model_name' => 'LiteBeam 5AC', 'model_number' => 'LBE-5AC',
            'type' => 'outdoor_ap', 'is_active' => true,
        ]);
    }

    public function test_admin_can_register_a_new_unit_straight_into_a_site(): void
    {
        $admin = $this->admin();
        $site = $this->site();
        $model = $this->model();

        $this->actingAs($admin)
            ->post(route('sites.equipment.store', $site), [
                'mode' => 'new',
                'device_model_id' => $model->id,
                'asset_tag' => 'DEV-NEW-1',
                'serial_number' => 'SN-NEW-1',
                'role_at_site' => 'primary_ap',
                'installed_at' => '2026-09-01',
            ])->assertRedirect()->assertSessionHas('success');

        $device = Device::where('asset_tag', 'DEV-NEW-1')->first();
        $this->assertNotNull($device);
        $this->assertSame('deployed', $device->status);

        $deployment = DeviceDeployment::where('device_id', $device->id)->whereNull('removed_at')->sole();
        $this->assertSame($site->id, $deployment->site_id);
        $this->assertSame('primary_ap', $deployment->role_at_site);
        $this->assertSame($admin->id, $deployment->installed_by);
    }

    public function test_admin_can_assign_an_in_stock_unit(): void
    {
        $admin = $this->admin();
        $site = $this->site();
        $model = $this->model();
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-STOCK-1',
            'serial_number' => 'SN-STOCK-1', 'status' => 'in_stock',
        ]);

        $this->actingAs($admin)
            ->post(route('sites.equipment.store', $site), [
                'mode' => 'existing',
                'device_id' => $device->id,
                'role_at_site' => 'backup_ap',
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('deployed', $device->fresh()->status);
        $this->assertNotNull(DeviceDeployment::where('device_id', $device->id)->whereNull('removed_at')->first());
    }

    public function test_detaching_closes_deployment_and_returns_unit_to_stock(): void
    {
        $admin = $this->admin();
        $site = $this->site();
        $model = $this->model();
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-DEP-1',
            'serial_number' => 'SN-DEP-1', 'status' => 'deployed',
        ]);
        $deployment = DeviceDeployment::create([
            'device_id' => $device->id, 'site_id' => $site->id,
            'role_at_site' => 'primary_ap', 'installed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('sites.equipment.destroy', ['site' => $site->id, 'deployment' => $deployment]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNotNull($deployment->fresh()->removed_at);
        $this->assertSame('in_stock', $device->fresh()->status);
    }

    public function test_cannot_detach_a_deployment_from_another_site(): void
    {
        $admin = $this->admin();
        $site = $this->site();
        $otherSite = $this->site();
        $model = $this->model();
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-X-1',
            'serial_number' => 'SN-X-1', 'status' => 'deployed',
        ]);
        $deployment = DeviceDeployment::create([
            'device_id' => $device->id, 'site_id' => $otherSite->id,
            'role_at_site' => 'primary_ap', 'installed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('sites.equipment.destroy', ['site' => $site->id, 'deployment' => $deployment]))
            ->assertNotFound();
        $this->assertNull($deployment->fresh()->removed_at);
    }

    public function test_viewer_cannot_attach(): void
    {
        $viewer = User::factory()->create();
        $viewer->roles()->attach(4);
        $site = $this->site();
        $model = $this->model();

        $this->actingAs($viewer)
            ->post(route('sites.equipment.store', $site), [
                'mode' => 'new',
                'device_model_id' => $model->id,
                'asset_tag' => 'DEV-V-1',
                'serial_number' => 'SN-V-1',
                'role_at_site' => 'primary_ap',
            ])->assertForbidden();
    }
}
