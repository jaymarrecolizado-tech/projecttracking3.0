<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use App\Services\BarangayCoverageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plan_revision §Phase 1.3–1.5 — attribution guarantees.
 *
 * - sites.region is required by the region filter; the model fills it from
 *   province so the filter can never silently drop a site again.
 * - The (project_id, ap_site_code) unique index cannot dedupe NULLs on MySQL,
 *   so a blank code gets a deterministic synthetic one: re-importing the same
 *   source row resolves to the same site instead of stacking duplicates.
 * - A district filter hides sites with no district recorded; the coverage
 *   payload must report how many, so a low figure can't look complete.
 */
class SiteAttributionGuaranteesTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->project = Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
    }

    public function test_region_is_filled_from_province_when_blank(): void
    {
        $site = Site::create([
            'project_id' => $this->project->id,
            'location_name' => 'Attributed Site',
            'province' => 'Isabela',
            'municipality' => 'Ilagan',
            'barangay' => 'Calamagui',
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
            // region intentionally omitted
        ]);

        $this->assertSame('II', $site->fresh()->region);
    }

    public function test_blank_site_code_gets_a_deterministic_synthetic_code(): void
    {
        $attributes = [
            'project_id' => $this->project->id,
            'location_name' => 'Codeless Site',
            'province' => 'Cagayan',
            'municipality' => 'Aparri',
            'barangay' => 'Tobias',
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
        ];

        $site = Site::create($attributes);
        $this->assertMatchesRegularExpression('/^NS-[0-9a-f]{12}$/', $site->ap_site_code);

        // The same source row must resolve to the same code — this is what
        // makes re-imports dedupe instead of duplicating.
        $this->assertSame($site->ap_site_code, (new Site($attributes))->syntheticCode());
    }

    public function test_identical_rows_get_disambiguated_codes_not_a_crash(): void
    {
        // NULLs could silently coexist under the unique index. Synthetic codes
        // collide by design (same source row = same code, so imports dedupe);
        // a genuinely distinct second site gets a suffix, never a 500.
        $attributes = [
            'project_id' => $this->project->id,
            'location_name' => 'Twin Site',
            'province' => 'Quirino',
            'municipality' => 'Diffun',
            'barangay' => 'San Isidro',
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
        ];

        $first = Site::create($attributes);
        $second = Site::create($attributes);

        $this->assertNotSame($first->ap_site_code, $second->ap_site_code);
        $this->assertMatchesRegularExpression('/^NS-[0-9a-f]{12}-2$/', $second->ap_site_code);
    }

    public function test_district_filter_reports_sites_hidden_by_a_blank_district(): void
    {
        DB::table('barangay_references')->insert([
            'province' => 'Isabela',
            'municipality' => 'Alicia',
            'name' => 'Poblacion',
            'name_normalized' => 'poblacion',
        ]);

        DB::table('legislative_districts')->insert([
            'province' => 'Isabela',
            'district' => '1st',
            'municipality' => 'Alicia',
        ]);

        Site::create([
            'project_id' => $this->project->id,
            'location_name' => 'Scoped Site',
            'province' => 'Isabela',
            'municipality' => 'Alicia',
            'barangay' => 'Poblacion',
            'district' => '1st',
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
        ]);

        $hidden = Site::create([
            'project_id' => $this->project->id,
            'location_name' => 'Blank District Site',
            'province' => 'Isabela',
            'municipality' => 'Alicia',
            'barangay' => 'Del Pilar',
            // district intentionally omitted
            'latitude' => 18.35,
            'longitude' => 121.64,
            'status' => 'active',
        ]);
        $this->assertNull($hidden->fresh()->district);

        $coverage = app(BarangayCoverageService::class)
            ->coverage(['province' => 'Isabela', 'district' => '1st']);

        $this->assertSame(1, $coverage['district_blank_sites']);
        $this->assertSame(1, $coverage['totals']['covered']);
    }
}
