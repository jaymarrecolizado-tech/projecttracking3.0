<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\DeviceModel;
use App\Models\FreewifiImportBatch;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\ImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $header, array ...$rows): string
    {
        $path = storage_path('app/testing-device-import-'.uniqid().'.csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, $header);
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        return $path;
    }

    private function site(): Site
    {
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_icon' => 'wifi',
        ]);

        return Site::create(['project_id' => $project->id, 'location_name' => 'Import Site', 'latitude' => 16.5, 'longitude' => 121.3, 'ap_site_code' => 'AP-001']);
    }

    public function test_import_creates_devices_with_auto_tags_and_assignments(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);
        $this->actingAs($admin);
        $site = $this->site();

        $path = $this->csv(
            ['MODEL MANUFACTURER', 'MODEL NUMBER', 'SERIAL NUMBER', 'MAC ADDRESS', 'SITE CODE'],
            ['Ubiquiti', 'LBE-5AC', 'SN-A', 'AA:BB:CC:DD:EE:01', 'AP-001'],
            ['Ubiquiti', 'LBE-5AC', 'SN-B', '', ''],
            ['TP-Link', 'CPE710', 'SN-C', 'bogus-mac', 'NOPE'],
        );

        $batch = FreewifiImportBatch::create(['filename' => 'devices.csv', 'type' => 'devices', 'imported_by' => $admin->id]);
        app(ImportService::class)->processDeviceImport($batch, $path);

        $this->assertSame('DONE', $batch->fresh()->job_status);
        $this->assertEquals(3, $batch->fresh()->rows_total);
        $this->assertEquals(2, $batch->fresh()->rows_success);
        $this->assertEquals(1, $batch->fresh()->rows_failed);

        // Auto tags FW-0001 / FW-0002; catalog auto-created; MAC validated
        $a = Device::where('serial_number', 'SN-A')->first();
        $b = Device::where('serial_number', 'SN-B')->first();
        // Row 3 references an unknown site — its writes roll back atomically.
        $c = Device::where('serial_number', 'SN-C')->first();
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNull($c); // failed row leaves no partial device behind
        $this->assertSame('deployed', $a->status);
        $this->assertNotNull(DeviceDeployment::where('device_id', $a->id)->where('site_id', $site->id)->first());
        $this->assertSame('in_stock', $b->status);
        $this->assertSame(1, DeviceModel::count()); // TP-Link CPE710 rolled back with its failed row; Ubiquiti deduped across rows 1+2
        $this->assertSame(1, DeviceModel::where('manufacturer', 'Ubiquiti')->count());

        unlink($path);
    }

    public function test_reimport_fills_blanks_without_resetting_deployed_device(): void
    {
        $device = Device::create([
            'device_model_id' => DeviceModel::create([
                'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
            ])->id,
            'asset_tag' => 'FW-0042',
            'serial_number' => 'SN-X',
            'status' => 'deployed',
        ]);

        $path = $this->csv(
            ['MODEL MANUFACTURER', 'MODEL NUMBER', 'SERIAL NUMBER'],
            ['U', 'M1', 'SN-X'],
        );
        $batch = FreewifiImportBatch::create(['filename' => 'again.csv', 'type' => 'devices']);
        app(ImportService::class)->processDeviceImport($batch, $path);

        $fresh = $device->fresh();
        $this->assertSame('FW-0042', $fresh->asset_tag);   // tag untouched
        $this->assertSame('deployed', $fresh->status);     // status not reset to in_stock

        unlink($path);
    }

    public function test_scan_route_redirects_to_device_page(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $device = Device::create([
            'device_model_id' => DeviceModel::create([
                'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
            ])->id,
            'asset_tag' => 'FW-0777',
            'serial_number' => 'SN-QR',
        ]);

        $this->actingAs($admin)->get('/d/FW-0777')
            ->assertRedirect(route('devices.show', $device));
        $this->get('/d/MISSING')->assertNotFound();
    }

    public function test_label_page_renders_qr_svg(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $device = Device::create([
            'device_model_id' => DeviceModel::create([
                'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
            ])->id,
            'asset_tag' => 'FW-0888',
            'serial_number' => 'SN-LBL',
        ]);

        $response = $this->actingAs($admin)->get('/devices-labels?device='.$device->id);
        $response->assertOk();
        // chillerlan v6 renders QRs as base64 SVG data URIs
        $this->assertStringContainsString('data:image/svg+xml;base64,', $response->getContent());
        $this->assertStringContainsString('FW-0888', $response->getContent());
    }
}
