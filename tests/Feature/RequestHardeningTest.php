<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan.md backlog #7 — small request-hardening fixes with one runnable
 * check each: boolean status filter, API pagination cap + history window,
 * heartbeat write race.
 */
class RequestHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    private function userWithRole(string $role, ?int $projectId = null): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Role::where('name', $role)->value('id'),
            ['project_id' => $projectId],
        );

        return $user;
    }

    private function site(): Site
    {
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);

        return Site::create([
            'project_id' => $project->id,
            'location_name' => 'Hardening Site',
            'latitude' => 16.5, 'longitude' => 121.3,
            'status' => 'active',
        ]);
    }

    public function test_status_false_lists_inactive_users(): void
    {
        $admin = $this->admin();
        User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        // Pre-fix `?status=false` was truthy and returned active users.
        $this->actingAs($admin)->get('/users?status=false')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->where('users.data', fn ($data) => collect($data)->pluck('id')->all() === [$inactive->id]));
    }

    public function test_api_sites_caps_per_page(): void
    {
        $token = $this->admin()->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/sites?per_page=1000000')->assertStatus(422);

        $this->withToken($token)->getJson('/api/sites?per_page=5')
            ->assertOk()
            ->assertJsonPath('per_page', 5);
    }

    public function test_api_site_show_caps_daily_history(): void
    {
        $site = $this->site();
        for ($i = 0; $i < 100; $i++) {
            SiteDailyStatus::create([
                'site_id' => $site->id,
                'date' => today()->subDays($i)->toDateString(),
                'status' => 'UP',
            ]);
        }
        $token = $this->admin()->createToken('api')->plainTextToken;

        $json = $this->withToken($token)->getJson("/api/sites/{$site->id}")->assertOk()->json();

        $this->assertCount(90, $json['daily_statuses']);
    }

    public function test_repeated_heartbeats_keep_a_single_daily_row(): void
    {
        $site = $this->site();
        $manager = $this->userWithRole('project_manager');
        $token = $manager->createToken('probe', ['heartbeat'])->plainTextToken;
        $payload = ['site_code' => $site->ap_site_code, 'status' => 'UP'];

        // Two back-to-back first-beats used to both miss and both create,
        // violating unique(site_id, date) → 500.
        $this->withToken($token)->postJson('/api/heartbeat', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/heartbeat', $payload)->assertOk();

        $this->assertSame(1, SiteDailyStatus::where('site_id', $site->id)
            ->whereDate('date', today())->count());
    }
}
