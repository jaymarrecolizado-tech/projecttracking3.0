<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceFirmwareFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    private DeviceModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = DeviceModel::create([
            'manufacturer' => 'Ubiquiti', 'model_name' => 'LiteBeam',
            'model_number' => 'LBE-5AC', 'type' => 'outdoor_ap',
            'wifi_standard' => 'wifi5', 'is_active' => true,
        ]);
    }

    private function makeDevice(string $tag, ?string $firmware): Device
    {
        return Device::create([
            'device_model_id' => $this->model->id,
            'asset_tag' => $tag,
            'serial_number' => "SN-{$tag}",
            'status' => 'in_stock',
            'firmware_version' => $firmware,
        ]);
    }

    public function test_outdated_filter_counts_and_lists_unapproved_versions(): void
    {
        config(['monitoring.approved_firmware' => ['v2.0']]);
        $admin = $this->admin();
        $this->makeDevice('FW-0001', 'v2.0');
        $this->makeDevice('FW-0002', 'v1.0');
        $this->makeDevice('FW-0003', null);

        $this->actingAs($admin)->get('/devices?firmware=outdated')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Devices/Index')
                ->where('firmware.outdated', 2)
                ->where('devices.data', fn ($data) => collect($data)->pluck('asset_tag')->sort()->values()->all() === ['FW-0002', 'FW-0003']));
    }

    public function test_counter_is_null_when_approved_firmware_unconfigured(): void
    {
        config(['monitoring.approved_firmware' => []]);
        $admin = $this->admin();
        $this->makeDevice('FW-0001', 'v1.0');

        $this->actingAs($admin)->get('/devices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('firmware.outdated', null)
                ->has('devices.data', 1));
    }
}
