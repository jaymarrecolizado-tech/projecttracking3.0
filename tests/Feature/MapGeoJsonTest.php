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

class MapGeoJsonTest extends TestCase
{
    use RefreshDatabase;

    private int $projectId;

    private function siteWith(array $attributes, bool $withDeployment = false): Site
    {
        if (! isset($this->projectId)) {
            $this->seed(RolePermissionSeeder::class);
            $this->projectId = Project::create([
                'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
                'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
            ])->id;
        }

        $site = Site::create(array_merge([
            'project_id' => $this->projectId,
            'location_name' => 'Map Site',
            'ap_site_code' => 'AP-MAP-'.uniqid(),
            'latitude' => 17.6,
            'longitude' => 121.7,
            'status' => 'active',
        ], $attributes));

        if ($withDeployment) {
            $model = DeviceModel::create([
                'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
            ]);
            $device = Device::create([
                'device_model_id' => $model->id,
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

        return $site;
    }

    public function test_default_geojson_includes_every_site_with_coordinates(): void
    {
        $this->siteWith(['province' => 'Cagayan', 'municipality' => 'Aparri'], withDeployment: true);
        $this->siteWith(['province' => 'Cagayan', 'municipality' => 'Ballesteros']);

        $features = collect($this->actingAs(User::factory()->create())
            ->getJson('/map/geojson')->json('features'));

        $this->assertCount(2, $features);
        $this->assertEqualsCanonicalizing(
            ['Aparri', 'Ballesteros'],
            $features->pluck('properties.municipality')->all(),
        );
    }

    public function test_deployed_only_layer_omits_sites_without_active_deployments(): void
    {
        $this->siteWith(['province' => 'Cagayan', 'municipality' => 'Aparri'], withDeployment: true);
        $this->siteWith(['province' => 'Cagayan', 'municipality' => 'Ballesteros']);

        $features = collect($this->actingAs(User::factory()->create())
            ->getJson('/map/geojson?deployed_only=1')->json('features'));

        $this->assertContains('Map Site', $features->pluck('properties.location_name'));
        // The site without an active deployment stays off the device layer.
        $this->assertNotContains('Ballesteros', $features->pluck('properties.location_name'));
        $this->assertCount(1, $features);
    }

    public function test_geo_filters_constrain_site_features(): void
    {
        $this->siteWith(['province' => 'Cagayan', 'municipality' => 'Aparri', 'site_type' => 'PES']);
        $this->siteWith(['province' => 'Isabela', 'municipality' => 'Palanan', 'site_type' => 'PHS']);

        $features = collect($this->actingAs(User::factory()->create())
            ->getJson('/map/geojson?province=Isabela')->json('features'));
        $this->assertCount(1, $features);
        $this->assertSame('Palanan', $features[0]['properties']['municipality']);

        $features = collect($this->getJson('/map/geojson?site_type=PHS')->json('features'));
        $this->assertCount(1, $features);
        $this->assertSame('PHS', $features[0]['properties']['site_type']);
    }

    public function test_device_features_aggregate_units_per_site(): void
    {
        $site = $this->siteWith(['province' => 'Cagayan', 'municipality' => 'Aparri'], withDeployment: true);
        // A second deployed unit at the SAME site aggregates into one marker.
        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'Y', 'model_number' => 'M2', 'type' => 'router', 'is_active' => true,
        ]);
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-2ND-'.uniqid(),
            'serial_number' => 'SN-2ND-'.uniqid(), 'status' => 'deployed',
        ]);
        DeviceDeployment::create([
            'device_id' => $device->id, 'site_id' => $site->id,
            'role_at_site' => 'backup_ap', 'installed_at' => now(),
        ]);

        $features = collect($this->actingAs(User::factory()->create())
            ->getJson('/map/geojson?deployed_only=1')->json('features'));

        $this->assertCount(1, $features);
        $this->assertSame('Map Site', $features[0]['properties']['location_name']);
        $this->assertSame(2, $features[0]['properties']['device_count']);
        $this->assertCount(2, $features[0]['properties']['devices']);
        $this->assertSame('NO_DATA', $features[0]['properties']['daily_status']);
    }

    public function test_province_boundaries_return_region_ii_features(): void
    {
        $json = $this->actingAs(User::factory()->create())
            ->getJson('/map/boundaries?level=province')->json();
        $names = collect($json['features'])->pluck('properties.name');

        $this->assertGreaterThan(0, $names->count());
        $this->assertContains('Cagayan', $names);
    }

    public function test_municipality_boundaries_are_clipped_to_province(): void
    {
        $json = $this->actingAs(User::factory()->create())
            ->getJson('/map/boundaries?level=municipality&province=Cagayan')->json();
        $parents = collect($json['features'])->pluck('properties.parent_name')->unique();

        $this->assertTrue($parents->every(fn ($parent) => $parent === 'Cagayan'));
    }

    public function test_barangay_boundaries_clip_to_municipality(): void
    {
        $json = $this->actingAs(User::factory()->create())
            ->getJson('/map/boundaries?level=barangay&municipality=Aparri')->json();
        $parents = collect($json['features'])->pluck('properties.municipality')->unique();

        $this->assertGreaterThan(0, $json['features']);
        $this->assertTrue($parents->every(fn ($muni) => $muni === 'Aparri'));
    }

    public function test_district_boundaries_clip_to_province(): void
    {
        $json = $this->actingAs(User::factory()->create())
            ->getJson('/map/boundaries?level=district&province=Cagayan')->json();

        $names = collect($json['features'])->pluck('properties.name')->unique();
        $this->assertCount(3, $names);
        $this->assertContains('3rd District', $names);
    }

    public function test_unknown_boundary_level_falls_back_to_province(): void
    {
        $json = $this->actingAs(User::factory()->create())
            ->getJson('/map/boundaries?level=region')->json();

        $this->assertGreaterThan(0, $json['features']);
    }

    public function test_filter_options_cascade_from_province(): void
    {
        $this->siteWith(['province' => 'Cagayan', 'district' => '1st District', 'municipality' => 'Aparri', 'barangay' => 'Zitanga']);
        $this->siteWith(['province' => 'Isabela', 'district' => '2nd District', 'municipality' => 'Palanan', 'barangay' => 'Didaducan']);

        $json = $this->actingAs(User::factory()->create())
            ->getJson('/map/filter-options?province=Cagayan')->json();

        $this->assertContains('Cagayan', $json['provinces']);
        $this->assertSame(['1st District'], $json['districts']);
        $this->assertNotContains('Palanan', $json['municipalities']);
    }

    public function test_map_page_renders_for_plain_viewer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $viewer = User::factory()->create();
        $viewer->roles()->attach(4);

        $this->actingAs($viewer)->get(route('map.index'))->assertOk();
    }
}
