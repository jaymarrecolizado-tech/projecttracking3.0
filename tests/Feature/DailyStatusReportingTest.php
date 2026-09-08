<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use App\Services\ReportingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan_revision §Phase 1 — every reported figure must account for all four
 * observed statuses (UP, DOWN, NO_NMS, DOWN_SERVER). Before this fix, uptime
 * and the 14-day trend only counted UP and DOWN, so 19.3% of real rows were
 * invisible and uptime was overstated by ~16 points on production data.
 */
class DailyStatusReportingTest extends TestCase
{
    use RefreshDatabase;

    private int $projectId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->projectId = Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
            'is_active' => true,
        ])->id;
    }

    private function site(array $attributes = []): Site
    {
        return Site::create(array_merge([
            'project_id' => $this->projectId,
            'location_name' => 'Reporting Site',
            'province' => 'Cagayan',
            'municipality' => 'Aparri',
            'barangay' => 'Tobias',
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
        ], $attributes));
    }

    /** @param  array<string, int>  $counts  status => number of rows */
    private function seedStatuses(array $counts, string $date): void
    {
        foreach ($counts as $status => $times) {
            for ($i = 0; $i < $times; $i++) {
                SiteDailyStatus::create([
                    // One fresh site per row: (site_id, date) is unique, so reusing
                    // a site across statuses on the same day would collapse rows.
                    'site_id' => $this->site()->id,
                    'date' => $date,
                    'status' => $status,
                ]);
            }
        }
    }

    public function test_uptime_counts_every_observed_status(): void
    {
        $this->seedStatuses([
            'UP' => 6,
            'DOWN' => 2,
            'NO_NMS' => 1,
            'DOWN_SERVER' => 1,
        ], today()->toDateString());

        $uptime = app(ReportingService::class)->getDashboardStats()['uptime_pct_7d'];

        // 6 / (6 + 2 + 1 + 1) = 60%. The old two-status maths gave 6/8 = 75%.
        $this->assertSame(60.0, $uptime);
    }

    public function test_no_data_stays_out_of_the_uptime_ratio(): void
    {
        $this->seedStatuses(['UP' => 3, 'DOWN' => 1], today()->toDateString());
        $this->assertSame(75.0, app(ReportingService::class)->getDashboardStats()['uptime_pct_7d']);

        // A NO_DATA row is the absence of a report, not a report.
        $this->seedStatuses(['NO_DATA' => 5], today()->toDateString());
        $this->assertSame(75.0, app(ReportingService::class)->getDashboardStats()['uptime_pct_7d']);
    }

    public function test_trend_exposes_a_series_per_status(): void
    {
        $this->seedStatuses([
            'UP' => 4,
            'DOWN' => 2,
            'NO_NMS' => 3,
            'DOWN_SERVER' => 1,
        ], today()->toDateString());

        $today = collect(app(ReportingService::class)->getDashboardStats()['trend'])->last();

        $this->assertSame(4, $today['up']);
        $this->assertSame(2, $today['down']);
        $this->assertSame(3, $today['no_nms']);
        $this->assertSame(1, $today['down_server']);
    }

    public function test_a_no_data_row_does_not_count_as_reporting(): void
    {
        $this->site(['location_name' => 'Reported NO_NMS']);
        $reported = $this->site(['location_name' => 'Not Reported']);
        $noData = $this->site(['location_name' => 'Snapshot NO_DATA']);

        SiteDailyStatus::create([
            'site_id' => $reported->id, 'date' => today()->toDateString(), 'status' => 'NO_NMS',
        ]);
        SiteDailyStatus::create([
            'site_id' => $noData->id, 'date' => today()->toDateString(), 'status' => 'NO_DATA',
        ]);

        $stats = app(ReportingService::class)->getDashboardStats();

        $this->assertSame(3, $stats['active_sites']);
        // Only the NO_NMS site genuinely reported; NO_DATA must not count.
        $this->assertSame(1, $stats['reported_today']);
        $this->assertSame(2, $stats['no_data_today']);
        $this->assertSame(1, $stats['no_nms_today']);
    }

    public function test_dashboard_page_renders_the_corrected_uptime(): void
    {
        $this->seedStatuses([
            'UP' => 6,
            'DOWN' => 2,
            'NO_NMS' => 1,
            'DOWN_SERVER' => 1,
        ], today()->toDateString());

        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // JSON round-trips 60.0 as 60, so compare numerically.
                ->where('stats.uptime_pct_7d', 60)
                ->where('stats.no_nms_today', 1)
                ->where('stats.down_server_today', 1));
    }
}
