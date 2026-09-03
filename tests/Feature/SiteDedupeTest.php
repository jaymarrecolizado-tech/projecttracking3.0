<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteDedupeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function pair(): array
    {
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
        ]);

        $canonical = Site::create([
            'project_id' => $project->id, 'location_name' => 'Science High School',
            'ap_site_code' => 'AP-1A', 'latitude' => 17.61, 'longitude' => 121.72, 'status' => 'active',
        ]);
        $duplicate = Site::create([
            'project_id' => $project->id, 'location_name' => 'Science High School',
            'ap_site_code' => 'AP-1B', 'latitude' => 17.61, 'longitude' => 121.72, 'status' => 'active',
        ]);

        return [$canonical, $duplicate];
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->pair();

        $this->artisan('sites:dedupe')->expectsOutputToContain('Dry run');

        $this->assertSame(2, Site::count());
    }

    public function test_apply_moves_relations_and_soft_deletes_duplicate(): void
    {
        [$canonical, $duplicate] = $this->pair();

        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
        ]);
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-DUP-1',
            'serial_number' => 'SN-DUP-1', 'status' => 'deployed',
        ]);
        $deployment = DeviceDeployment::create([
            'device_id' => $device->id, 'site_id' => $duplicate->id,
            'role_at_site' => 'primary_ap', 'installed_at' => now(),
        ]);
        // A day only the duplicate recorded, and a day both recorded.
        SiteDailyStatus::create(['site_id' => $duplicate->id, 'date' => today()->subDays(2)->toDateString(), 'status' => 'UP']);
        SiteDailyStatus::create(['site_id' => $duplicate->id, 'date' => today()->toDateString(), 'status' => 'DOWN']);
        SiteDailyStatus::create(['site_id' => $canonical->id, 'date' => today()->toDateString(), 'status' => 'UP']);

        $this->artisan('sites:dedupe', ['--apply' => true])->expectsOutputToContain('Merged 1 duplicate row(s)');

        // Deployment survives — re-homed on the canonical site.
        $this->assertSame($canonical->id, $deployment->fresh()->site_id);

        // Unique (site_id, date): the overlapping day is dropped, the other moved.
        $this->assertSame(2, SiteDailyStatus::where('site_id', $canonical->id)->count());
        $this->assertNotNull(SiteDailyStatus::where('site_id', $canonical->id)->whereDate('date', today()->subDays(2))->first());

        // Duplicate is soft-deleted with a pointer to its canonical row.
        $this->assertSame(1, Site::count());
        $trashed = Site::withTrashed()->find($duplicate->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame($canonical->id, $trashed->metadata['merged_into']);

        // Site lookup by the duplicate's AP code finds the trashed row with
        // withTrashed() — probes keep resolving (see HeartbeatController).
        $this->assertNotNull(Site::withTrashed()->where('ap_site_code', 'AP-1B')->first());

        // Rows on the trashed duplicate were moved out first — nothing orphaned.
        $this->assertSame(0, DB::table('device_deployments')->where('site_id', $duplicate->id)->count());
        $this->assertSame(0, DB::table('site_daily_statuses')->where('site_id', $duplicate->id)->count());
    }
}
