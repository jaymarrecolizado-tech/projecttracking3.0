<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plan.md F1 residual — MariaDB stores `$table->json()` as LONGTEXT, so a local
 * run cannot exercise MySQL 8's native JSON type. MySQL 8 NORMALISES documents
 * on write (keys reordered, whitespace stripped, duplicate keys collapsed), so
 * this pins the only contract the app is allowed to rely on:
 *
 *   1. values survive the round-trip through the `array` cast, and
 *   2. key ORDER is not part of that contract.
 *
 * Everything here must hold on SQLite, MariaDB and MySQL 8 alike.
 */
class JsonColumnRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function siteWithMetadata(array $metadata): Site
    {
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);

        return Site::create([
            'project_id' => $project->id, 'location_name' => 'JSON Round Trip',
            'province' => 'Cagayan', 'municipality' => 'Aparri',
            'latitude' => 18.3, 'longitude' => 121.6, 'status' => 'active',
            'metadata' => $metadata,
        ]);
    }

    public function test_metadata_values_survive_the_round_trip(): void
    {
        $site = $this->siteWithMetadata([
            'merged_into' => 42,
            'note' => 'Peñablanca — naïve café',
            'empty_string' => '',
            'zero' => 0,
            'flag' => true,
            'off' => false,
            'nothing' => null,
            'ratio' => 0.5,
            'nested' => ['municipality_psgc' => '0201505001', 'depth' => ['a' => 1]],
            'list' => [1, 2, 3],
            'empty_list' => [],
        ]);

        $fresh = Site::findOrFail($site->id);
        $meta = $fresh->metadata;

        $this->assertSame(42, $meta['merged_into']);
        $this->assertSame('Peñablanca — naïve café', $meta['note']);
        $this->assertSame('', $meta['empty_string']);
        $this->assertSame(0, $meta['zero']);
        $this->assertTrue($meta['flag']);
        $this->assertFalse($meta['off']);
        $this->assertNull($meta['nothing']);
        $this->assertSame(0.5, $meta['ratio']);
        $this->assertSame('0201505001', $meta['nested']['municipality_psgc']);
        $this->assertSame(1, $meta['nested']['depth']['a']);
        $this->assertSame([1, 2, 3], $meta['list']);
        $this->assertSame([], $meta['empty_list']);
    }

    /**
     * The hazard this test exists for: MySQL 8 reorders object keys, SQLite does
     * not. Comparing keys as an ordered list would pass locally and fail in
     * production — so the contract is asserted as a SET.
     */
    public function test_key_order_is_not_part_of_the_contract(): void
    {
        $site = $this->siteWithMetadata([
            'zulu' => 1,
            'alpha' => 2,
            'mike' => 3,
        ]);

        $meta = Site::findOrFail($site->id)->metadata;

        // Order-agnostic: the same keys, whatever order the engine returns.
        $this->assertEqualsCanonicalizing(['zulu', 'alpha', 'mike'], array_keys($meta));

        // And each key still maps to its own value — reordering must not
        // scramble values between keys.
        $this->assertSame(1, $meta['zulu']);
        $this->assertSame(2, $meta['alpha']);
        $this->assertSame(3, $meta['mike']);
    }

    /**
     * Raw JSON written past the cast (whitespace padding, duplicate keys) must
     * still decode predictably. Duplicate keys resolve to the LAST value on both
     * engines: MySQL 8 collapses them on write, and PHP's json_decode does the
     * same on read, so the outcome is identical either way.
     */
    public function test_raw_json_written_past_the_cast_still_decodes(): void
    {
        $site = $this->siteWithMetadata(['seed' => true]);

        DB::table('sites')->where('id', $site->id)->update([
            'metadata' => '{ "padded" : 1 , "dup" : 2 , "dup" : 3 }',
        ]);

        $meta = Site::findOrFail($site->id)->metadata;

        $this->assertSame(1, $meta['padded']);
        $this->assertSame(3, $meta['dup'], 'duplicate keys must resolve to the last value');
        $this->assertArrayNotHasKey('seed', $meta, 'the raw write replaced the document');
    }
}
