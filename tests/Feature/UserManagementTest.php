<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->value('id'));

        return $admin;
    }

    public function test_admin_can_view_users_and_create_scoped_encoder(): void
    {
        $admin = $this->admin();
        $project = Project::create([
            'code' => 'OPS-A', 'name' => 'Project OPS-A', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi',
        ]);
        $encoderRole = Role::where('name', 'encoder')->value('id');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Field Encoder',
            'email' => 'encoder@dict.gov.ph',
            'password' => 'secure-password-1',
            'roles' => [['role_id' => $encoderRole, 'project_id' => $project->id]],
        ])->assertRedirect()->assertSessionHas('success');

        $user = User::where('email', 'encoder@dict.gov.ph')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_active);
        $this->assertSame($project->id, (int) $user->roles()->first()->pivot->project_id);
        $this->assertTrue($user->hasPermission('daily.create', $project->id));
        $this->assertFalse($user->hasPermission('daily.create', $project->id + 999));
    }

    public function test_non_manager_cannot_access_user_administration(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('name', 'viewer')->value('id'));

        $this->actingAs($viewer)->get('/users')->assertForbidden();
        $this->actingAs($viewer)->post('/users', ['name' => 'X', 'email' => 'x@x.ph', 'password' => 'longenough1'])->assertForbidden();
    }

    public function test_deactivated_user_cannot_sign_in(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['password' => bcrypt('longenough1'), 'is_active' => true]);

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'password' => null,
            'is_active' => false,
            'roles' => [],
        ])->assertRedirect();

        // Drop the admin session — the login route is guest-only.
        auth()->logout();
        $this->post('/login', ['email' => $target->email, 'password' => 'longenough1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => null,
            'is_active' => false,
            'roles' => [],
        ])->assertRedirect();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/users/{$admin->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
