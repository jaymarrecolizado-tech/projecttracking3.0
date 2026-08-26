<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the split resource-route authorization: create / edit / delete each
 * resolve through their own policy methods so project-scoped permissions hold.
 */
class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $code): Project
    {
        return Project::create([
            'code' => $code,
            'name' => "Project {$code}",
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
        ]);
    }

    private function site(Project $project, string $name = 'Test Site'): Site
    {
        return Site::create([
            'project_id' => $project->id,
            'location_name' => $name,
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ]);
    }

    private function userWithRole(string $roleName, ?int $projectId = null): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $roleId = Role::where('name', $roleName)->value('id');
        $user->roles()->attach($roleId, ['project_id' => $projectId]);

        return $user;
    }

    public function test_viewer_cannot_create_sites(): void
    {
        $viewer = $this->userWithRole('viewer');

        $response = $this->actingAs($viewer)->post(route('sites.store'), [
            'location_name' => 'New Site',
            'latitude' => 14.6,
            'longitude' => 120.9,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Site::count());
    }

    public function test_scoped_manager_can_edit_only_within_assigned_project(): void
    {
        $projectA = $this->project('PROJ-A');
        $projectB = $this->project('PROJ-B');
        $siteA = $this->site($projectA, 'Site A');
        $siteB = $this->site($projectB, 'Site B');

        // Manager scoped to project A: holds sites.edit globally per seeder,
        // but the policy restricts edits to the assigned project.
        $manager = $this->userWithRole('project_manager', $projectA->id);

        $this->actingAs($manager)
            ->put(route('sites.update', $siteA), ['location_name' => 'Renamed A'])
            ->assertRedirect(route('sites.show', $siteA));
        $this->assertSame('Renamed A', $siteA->fresh()->location_name);

        // Pre-fix this returned a redirect: create-permission leaked into update.
        $this->actingAs($manager)
            ->put(route('sites.update', $siteB), ['location_name' => 'Hacked'])
            ->assertForbidden();
        $this->assertSame('Site B', $siteB->fresh()->location_name);
    }

    public function test_encoder_cannot_delete_sites(): void
    {
        $project = $this->project('PROJ-C');
        $site = $this->site($project);

        $encoder = $this->userWithRole('encoder', $project->id);

        $this->actingAs($encoder)
            ->delete(route('sites.destroy', $site))
            ->assertForbidden();
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_any_site(): void
    {
        $project = $this->project('PROJ-D');
        $site = $this->site($project);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->delete(route('sites.destroy', $site))
            ->assertRedirect();

        $this->assertSoftDeleted('sites', ['id' => $site->id]);
    }
}
