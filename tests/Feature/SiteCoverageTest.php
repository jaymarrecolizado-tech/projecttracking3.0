<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteCoverageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteCoverageTest extends TestCase
{
    use RefreshDatabase;

    private int $projectId;

    private function site(array $attributes): Site
    {
        $this->seed(RolePermissionSeeder::class);
        if (! isset($this->projectId)) {
            $this->projectId = Project::create([
                'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
                'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
            ])->id;
        }

        return Site::create(array_merge([
            'project_id' => $this->projectId,
            'location_name' => 'Coverage Site',
            'latitude' => 17.6,
            'longitude' => 121.7,
            'status' => 'active',
        ], $attributes));
    }

    private function deploy(Site $site): void
    {
        if (! isset($this->modelId)) {
            $this->modelId = DeviceModel::create([
                'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
            ])->id;
        }
        $device = Device::create([
            'device_model_id' => $this->modelId,
            'asset_tag' => 'DEV-'.uniqid(),
            'serial_number' => 'SN-'.uniqid(),
            'status' => 'deployed',
        ]);
        DeviceDeployment::create([
            'device_id' => $device->id,
            'site_id' => $site->id,
            'role_at_site' => 'primary_ap',
            'installed_at' => now(),
        ]);
    }

    public function test_coverage_counts_registered_actual_and_devices_by_site_type(): void
    {
        // PES: 3 registered, 1 actual with 2 devices.
        $pes1 = $this->site(['municipality' => 'Aparri', 'site_type' => 'PES']);
        $this->site(['municipality' => 'Aparri', 'site_type' => 'PES']);
        $this->site(['municipality' => 'Ballesteros', 'site_type' => 'PES']);
        $this->deploy($pes1);
        $this->deploy($pes1);

        // PHS: 1 registered, 1 actual.
        $phs = $this->site(['municipality' => 'Aparri', 'site_type' => 'PHS']);
        $this->deploy($phs);

        $coverage = app(SiteCoverageService::class)->coverage();

        $pes = collect($coverage['rows'])->firstWhere('site_type', 'PES');
        $this->assertSame(3, $pes['registered']);
        $this->assertSame(1, $pes['actual']);
        $this->assertSame(2, $pes['gap']);
        $this->assertSame(2, $pes['devices']);
        $this->assertSame(33.3, $pes['coverage_pct']);

        $phs = collect($coverage['rows'])->firstWhere('site_type', 'PHS');
        $this->assertSame(1, $phs['registered']);
        $this->assertSame(0, $phs['gap']);

        $this->assertSame(4, $coverage['totals']['registered']);
        $this->assertSame(2, $coverage['totals']['actual']);
        $this->assertSame(50.0, $coverage['totals']['coverage_pct']);
    }

    public function test_coverage_respects_geo_filters(): void
    {
        $this->site(['municipality' => 'Aparri', 'site_type' => 'PES']);
        $this->site(['municipality' => 'Palanan', 'site_type' => 'PHS']);

        $coverage = app(SiteCoverageService::class)->coverage(['municipality' => 'Palanan']);

        $this->assertSame(1, $coverage['totals']['registered']);
        $this->assertSame('PHS', $coverage['rows'][0]['site_type']);
    }

    public function test_site_type_report_queues_and_completes(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $this->site(['municipality' => 'Aparri', 'site_type' => 'PES']);

        $this->actingAs($admin)
            ->post(route('reports.site-type'), ['province' => 'Cagayan'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $export = ReportExport::where('type', 'site_type')->sole();
        $this->assertSame('DONE', $export->status);
        $this->assertSame('Cagayan', $export->params['filters']['province']);
        Storage::disk('local')->assertExists($export->filename);
    }

    public function test_site_type_report_requires_authentication(): void
    {
        $this->post(route('reports.site-type'))->assertRedirect('/login');
    }
}
