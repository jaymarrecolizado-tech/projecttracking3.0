<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_last_admin_cannot_delete_their_account(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->value('id'));

        $this->actingAs($admin)->delete('/profile', ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertNotNull($admin->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_issuing_probe_token_prunes_expired_tokens(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->value('id'));

        $admin->createToken('stale', ['heartbeat'], now()->subDay());
        $admin->createToken('fresh', ['heartbeat'], now()->addMonth());

        $this->actingAs($admin)->post(route('probe-tokens.store'), ['name' => 'field-probe'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $admin->id, 'name' => 'stale']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $admin->id, 'name' => 'fresh']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $admin->id, 'name' => 'field-probe']);
    }
}
