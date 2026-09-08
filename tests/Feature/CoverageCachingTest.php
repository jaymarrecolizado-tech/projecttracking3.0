<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteCoverageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plan_revision §Phase 3.2 — the dashboard must not walk every site row on
 * every load: the two coverage aggregates are cached, and any site write
 * invalidates them.
 */
class CoverageCachingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(
            Role::where('name', 'admin')->value('id'),
        );

        $project = Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
            'is_active' => true,
        ]);

        foreach (range(1, 5) as $i) {
            Site::create([
                'project_id' => $project->id,
                'location_name' => "Cached Site {$i}",
                'province' => 'Cagayan',
                'municipality' => 'Aparri',
                'barangay' => "Barangay {$i}",
                'latitude' => 18.3 + $i / 100,
                'longitude' => 121.6 + $i / 100,
                'status' => 'active',
            ]);
        }
    }

    public function test_second_coverage_fetch_runs_fewer_queries(): void
    {
        cache()->flush();

        DB::enableQueryLog();
        $this->actingAs($this->admin)->getJson('/map/coverage');
        $cold = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->actingAs($this->admin)->getJson('/map/coverage');
        $warm = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertGreaterThan(0, $cold);
        $this->assertLessThan($cold, $warm + 1, "warm fetch ran {$warm} queries vs cold {$cold} — coverage cache is not being hit");
    }

    public function test_site_write_invalidates_cached_coverage(): void
    {
        app(SiteCoverageService::class)->coverage([]);
        $before = (int) cache()->get('coverage.version', 0);

        Site::first()->update(['location_name' => 'Renamed Site']);

        $this->assertSame(
            $before + 1,
            (int) cache()->get('coverage.version', $before),
            'a site write must bump the coverage cache version',
        );
    }
}
