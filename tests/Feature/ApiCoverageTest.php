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
 * Plan.md backlog #8 — the two endpoints that had no coverage:
 * probe-tokens.destroy and /api/daily-statuses (+ bySite).
 */
class ApiCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
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

    private function project(string $code): Project
    {
        return Project::create([
            'code' => $code, 'name' => "{$code} Project", 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
            'is_active' => true,
        ]);
    }

    private function site(Project $project, string $name): Site
    {
        return Site::create([
            'project_id' => $project->id,
            'location_name' => $name,
            'latitude' => 16.5, 'longitude' => 121.3,
            'status' => 'active',
        ]);
    }

    private function statusRow(Site $site, string $date, string $status = 'UP'): SiteDailyStatus
    {
        return SiteDailyStatus::create([
            'site_id' => $site->id,
            'date' => $date,
            'status' => $status,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_user_can_revoke_own_probe_token(): void
    {
        $encoder = $this->userWithRole('encoder');
        $token = $encoder->createToken('field-probe', ['heartbeat']);

        $this->actingAs($encoder)
            ->delete(route('probe-tokens.destroy', $token->accessToken->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_user_cannot_revoke_another_users_probe_token(): void
    {
        $me = $this->userWithRole('encoder');
        $other = $this->userWithRole('encoder');
        $token = $other->createToken('field-probe', ['heartbeat']);

        // Scoped to the owner's own tokens — another user's id is a 404.
        $this->actingAs($me)
            ->delete(route('probe-tokens.destroy', $token->accessToken->id))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_daily_statuses_index_filters_and_scopes(): void
    {
        $projectA = $this->project('PROJ-A');
        $projectB = $this->project('PROJ-B');
        $siteA = $this->site($projectA, 'Site A');
        $siteB = $this->site($projectB, 'Site B');
        $this->statusRow($siteA, today()->toDateString(), 'UP');
        $this->statusRow($siteA, today()->subDay()->toDateString(), 'DOWN');
        $this->statusRow($siteB, today()->toDateString(), 'DOWN');

        $manager = $this->userWithRole('project_manager', $projectA->id);
        $token = $manager->createToken('api')->plainTextToken;

        // Sees only the assigned project.
        $this->withToken($token)->getJson('/api/daily-statuses')
            ->assertOk()
            ->assertJsonFragment(['location_name' => 'Site A'])
            ->assertJsonMissing(['location_name' => 'Site B']);

        // Date + status filters narrow within scope.
        $this->withToken($token)
            ->getJson('/api/daily-statuses?date='.today()->subDay()->toDateString().'&status=DOWN')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Same uncapped-pagination hole as /api/sites had.
        $this->withToken($token)->getJson('/api/daily-statuses?per_page=1000000')->assertStatus(422);
    }

    public function test_daily_statuses_by_site_filters_and_scopes(): void
    {
        $projectA = $this->project('PROJ-A');
        $projectB = $this->project('PROJ-B');
        $siteA = $this->site($projectA, 'Site A');
        $siteB = $this->site($projectB, 'Site B');
        $this->statusRow($siteA, today()->toDateString(), 'UP');
        $this->statusRow($siteA, today()->subDay()->toDateString(), 'DOWN');
        $this->statusRow($siteB, today()->toDateString(), 'DOWN');

        $manager = $this->userWithRole('project_manager', $projectA->id);
        $token = $manager->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson("/api/daily-statuses/site/{$siteB->id}")->assertForbidden();

        $this->withToken($token)
            ->getJson("/api/daily-statuses/site/{$siteA->id}?date=".today()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['status' => 'UP']);
    }
}
