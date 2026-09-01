<?php

namespace Tests\Feature;

use Database\Seeders\LegislativeDistrictSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegislativeDistrictBackfillTest extends TestCase
{
    use RefreshDatabase;

    private int $projectId;

    private function site(array $attributes): int
    {
        if (! isset($this->projectId)) {
            $this->projectId = (int) DB::table('projects')->insertGetId([
                'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
                'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return (int) DB::table('sites')->insertGetId(array_merge([
            'project_id' => $this->projectId,
            'location_name' => 'Test Site',
            'latitude' => 17.6,
            'longitude' => 121.7,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    public function test_municipality_maps_to_expected_district(): void
    {
        $this->seed(LegislativeDistrictSeeder::class);

        $id = $this->site(['province' => 'Cagayan', 'municipality' => 'Aparri']);
        Artisan::call('sites:backfill-districts');
        $this->assertSame('1st District', DB::table('sites')->find($id)->district);

        // Alias spelling used by the workbook resolves to the same district.
        $id = $this->site(['province' => 'Cagayan', 'municipality' => 'Tuguegarao']);
        Artisan::call('sites:backfill-districts');
        $this->assertSame('3rd District', DB::table('sites')->find($id)->district);

        // Lone-district provinces cover every municipality.
        $id = $this->site(['province' => 'Quirino', 'municipality' => 'Maddela']);
        Artisan::call('sites:backfill-districts');
        $this->assertSame('Lone District', DB::table('sites')->find($id)->district);
    }

    public function test_unknown_lgu_is_left_null_and_never_guessed(): void
    {
        $this->seed(LegislativeDistrictSeeder::class);

        $id = $this->site(['province' => 'Cagayan', 'municipality' => 'Not A Real Municipality']);
        Artisan::call('sites:backfill-districts');
        $this->assertNull(DB::table('sites')->find($id)->district);
    }

    public function test_backfill_is_idempotent(): void
    {
        $this->seed(LegislativeDistrictSeeder::class);
        $id = $this->site(['province' => 'Isabela', 'municipality' => 'Ilagan City']);

        Artisan::call('sites:backfill-districts');
        Artisan::call('sites:backfill-districts');

        $this->assertSame('1st District', DB::table('sites')->find($id)->district);
        $this->assertSame(1, DB::table('sites')->where('municipality', 'Ilagan City')->count());
    }
}
