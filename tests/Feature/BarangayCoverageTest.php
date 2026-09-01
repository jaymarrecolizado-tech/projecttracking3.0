<?php

namespace Tests\Feature;

use App\Models\BarangayReference;
use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use App\Services\BarangayCoverageService;
use App\Support\NameNormalizer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarangayCoverageTest extends TestCase
{
    use RefreshDatabase;

    private int $projectId;

    private function seedReferences(string $municipality, array $names, string $province = 'Cagayan'): void
    {
        foreach ($names as $name) {
            BarangayReference::create([
                'province' => $province,
                'municipality' => $municipality,
                'name' => $name,
                'name_normalized' => NameNormalizer::normalize($name),
            ]);
        }
    }

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
            'location_name' => 'Brgy Site',
            'municipality' => 'Aparri',
            'province' => 'Cagayan',
            'latitude' => 18.3,
            'longitude' => 121.6,
            'status' => 'active',
        ], $attributes));
    }

    public function test_coverage_counts_covered_and_remaining_barangays(): void
    {
        $this->seedReferences('Aparri', ['Tobias', 'Zitanga', 'Dadapilan', 'Mabanguc']);
        // Two spellings of the same barangay + one unknown barangay.
        $this->site(['barangay' => 'Tobias']);
        $this->site(['barangay' => 'TOBIAS']);
        $this->site(['barangay' => 'Bisagu']);
        // Site without a barangay stays unattributed.
        $this->site(['barangay' => null, 'location_name' => 'No Brgy Site']);

        $coverage = app(BarangayCoverageService::class)->coverage();

        $this->assertSame(4, $coverage['totals']['barangays']);
        // Only 'Tobias' is in the reference list; 'Bisagu' isn't a recognized barangay.
        $this->assertSame(1, $coverage['totals']['covered']);
        $this->assertSame(3, $coverage['totals']['remaining']);
        $this->assertSame(25.0, $coverage['totals']['coverage_pct']);
        $this->assertSame(1, $coverage['unattributed_sites']);

        $row = $coverage['rows'][0];
        $this->assertSame('Aparri', $row['municipality']);
        // Only sites inside COVERED barangays count (Tobias ×2, one spelled differently).
        $this->assertSame(2, $row['sites']);
        $this->assertSame(0, $row['deployed']);
    }

    public function test_deployed_column_requires_active_deployment(): void
    {
        $this->seedReferences('Aparri', ['Tobias', 'Zitanga']);
        $this->site(['barangay' => 'Tobias']);
        $this->site(['barangay' => 'Zitanga']);

        $model = DeviceModel::create([
            'manufacturer' => 'U', 'model_name' => 'X', 'model_number' => 'M1', 'type' => 'router', 'is_active' => true,
        ]);
        $device = Device::create([
            'device_model_id' => $model->id, 'asset_tag' => 'DEV-BC-1',
            'serial_number' => 'SN-BC-1', 'status' => 'deployed',
        ]);
        DeviceDeployment::create([
            'device_id' => $device->id, 'site_id' => Site::where('barangay', 'Zitanga')->sole()->id,
            'role_at_site' => 'primary_ap', 'installed_at' => now(),
        ]);

        $coverage = app(BarangayCoverageService::class)->coverage();
        $this->assertSame(2, $coverage['totals']['covered']);
        $this->assertSame(1, $coverage['totals']['deployed']);
    }

    public function test_normalizer_unifies_city_spellings(): void
    {
        $this->assertSame('ilagan', NameNormalizer::normalize('Ilagan City'));
        $this->assertSame('ilagan', NameNormalizer::normalize('City of Ilagan'));
        $this->assertSame('basco', NameNormalizer::normalize('Basco (Capital)'));
        $this->assertSame('penablanca', NameNormalizer::normalize('Peñablanca'));
    }

    public function test_barangay_coverage_pdf_queues_and_completes(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $this->seedReferences('Aparri', ['Tobias']);
        $site = $this->site(['barangay' => 'Tobias']);
        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'UP']);
        DB::table('sites')->where('id', $site->id)->update(['barangay' => 'Tobias']);

        $this->actingAs($admin)
            ->post(route('reports.barangay-coverage'), ['province' => 'Cagayan'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $export = ReportExport::where('type', 'barangay_coverage')->sole();
        $this->assertSame('DONE', $export->status);
        Storage::disk('local')->assertExists($export->filename);
    }

    public function test_psgc_import_is_authoritative_and_sets_psgc_codes(): void
    {
        // Bootstrap from the boundary layer, then let PSGC reconcile.
        $this->artisan('barangays:sync-reference');

        // Two real Aparri barangays from the layer + one newer PSGC-only name.
        $existing = BarangayReference::where('municipality', 'Aparri')->limit(2)->get();
        $fixture = sys_get_temp_dir().'/psgc-test-'.uniqid().'.json';
        file_put_contents($fixture, json_encode(array_merge(
            $existing->map(fn ($r, $i) => [
                'psgc_id' => '020500800'.($i + 1), 'name' => $r->name,
                'muni' => 'Aparri', 'prov' => 'Cagayan',
            ])->values()->all(),
            [['psgc_id' => '0205008003', 'name' => 'Renamed Pob.', 'muni' => 'Aparri', 'prov' => 'Cagayan']],
        )));

        $before = BarangayReference::count();
        $this->artisan('barangays:import-psgc', ['file' => $fixture])
            ->expectsOutputToContain('PSGC row(s)');

        unlink($fixture);

        // Existing rows refresh in place; only the PSGC-only rename is new.
        $this->assertSame($before + 1, BarangayReference::count());
        foreach ($existing as $i => $reference) {
            $this->assertSame('020500800'.($i + 1), $reference->fresh()->psgc);
        }
        $this->assertNotNull(BarangayReference::where('name', 'Renamed Pob.')->first());
    }

    public function test_attach_psgc_stamps_site_codes(): void
    {
        $this->artisan('barangays:sync-reference');

        // Pick a real reference barangay and build a site on it.
        $reference = BarangayReference::whereNotNull('psgc')->first();
        $this->site([
            'province' => $reference->province,
            'municipality' => $reference->municipality,
            'barangay' => '  '.ucwords(strtolower($reference->name)).'  ', // messy spelling must still match
        ]);
        $this->site(['barangay' => null, 'location_name' => 'No Brgy']);
        $this->site(['barangay' => 'Not A Real Barangay', 'location_name' => 'Unknown Brgy']);

        $this->artisan('sites:attach-psgc')->expectsOutputToContain('Matched 1 site(s)');

        $matched = Site::whereNotNull('loc_id')->first();
        $this->assertSame($reference->psgc, $matched->loc_id);
        $this->assertSame(substr($reference->psgc, 0, 5).'00000', $matched->prov_id);
        $this->assertSame(substr($reference->psgc, 0, 9).'0', $matched->metadata['municipality_psgc']);
    }

    public function test_sync_command_upserts_without_deleting_manual_rows(): void
    {
        BarangayReference::create([
            'province' => 'Cagayan', 'municipality' => 'Aparri',
            'name' => 'PSA-added Barangay', 'name_normalized' => 'psa added barangay',
        ]);

        $this->artisan('barangays:sync-reference')->expectsOutputToContain('Reference total');

        // Manual PSA correction survives the sync.
        $this->assertNotNull(BarangayReference::where('name', 'PSA-added Barangay')->first());
        // 2,262 from the boundary layer + the manual PSA row.
        $this->assertSame(2263, BarangayReference::count());
    }
}
