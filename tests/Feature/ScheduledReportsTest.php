<?php

namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Plan.md Phase 5 — reports:scheduled queues one previous-month province
 * pack per province and announces it, without duplicating on re-runs.
 */
class ScheduledReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
        foreach (['Cagayan', 'Isabela'] as $province) {
            Site::create([
                'project_id' => $project->id,
                'location_name' => "{$province} Hall",
                'province' => $province,
                'latitude' => 16.5, 'longitude' => 121.3,
                'status' => 'active',
            ]);
        }
    }

    public function test_queues_one_pack_per_province_for_the_given_month(): void
    {
        Queue::fake();

        $this->artisan('reports:scheduled', ['--month' => '2026-08'])->assertSuccessful();

        $this->assertSame(2, ReportExport::where('type', 'province')->count());
        Queue::assertPushed(GenerateReport::class, 2);
        $params = ReportExport::firstWhere('params->province', 'Cagayan')->params;
        $this->assertSame('2026-08-01', $params['from']);
        $this->assertSame('2026-08-31', $params['to']);
    }

    public function test_rerun_skips_unless_forced(): void
    {
        Queue::fake();

        $this->artisan('reports:scheduled', ['--month' => '2026-08'])->assertSuccessful();
        $this->artisan('reports:scheduled', ['--month' => '2026-08'])->assertSuccessful();
        $this->assertSame(2, ReportExport::where('type', 'province')->count());

        $this->artisan('reports:scheduled', ['--month' => '2026-08', '--force' => true])->assertSuccessful();
        $this->assertSame(4, ReportExport::where('type', 'province')->count());
    }

    public function test_invalid_month_fails(): void
    {
        $this->artisan('reports:scheduled', ['--month' => 'last-summer'])->assertFailed();
    }
}
