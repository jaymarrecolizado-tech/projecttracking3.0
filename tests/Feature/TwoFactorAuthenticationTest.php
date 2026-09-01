<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    public function test_admin_can_enable_and_confirm_two_factor(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Start setup: secret stored, not yet enabled.
        $this->post(route('two-factor.enable'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_enabled_at);
        $this->assertFalse($admin->hasTwoFactorEnabled());

        $secret = $admin->two_factor_secret;
        $setup = session('two_factor_setup');
        $this->assertSame($secret, $setup['secret']);
        $this->assertStringContainsString('<svg', $setup['qr']);

        // Wrong code does not enable.
        $this->from(route('profile.edit'))
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
        $this->assertNull($admin->refresh()->two_factor_enabled_at);

        // Correct code enables and clears the setup session.
        $this->post(route('two-factor.confirm'), ['code' => Totp::code($secret)])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNotNull($admin->refresh()->two_factor_enabled_at);
        $this->assertNull(session('two_factor_setup'));

        // Disabling requires a current code.
        $this->delete(route('two-factor.disable'), ['code' => Totp::code($secret)])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNull($admin->refresh()->two_factor_secret);
    }

    public function test_non_admin_cannot_enable_two_factor(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $viewer = User::factory()->create();
        $viewer->roles()->attach(4);

        $this->actingAs($viewer)->post(route('two-factor.enable'))->assertForbidden();
        $this->assertNull($viewer->refresh()->two_factor_secret);
    }

    public function test_login_with_two_factor_requires_challenge_code(): void
    {
        $admin = $this->admin();
        $admin->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_enabled_at' => now(),
        ])->save();

        // Credentials accepted but login deferred to the challenge screen.
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        // Challenge page renders with the pending session.
        $this->get(route('two-factor.challenge'))->assertOk();

        // Wrong code keeps the user locked out.
        $this->post(route('two-factor.challenge.store'), ['code' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // Correct code finishes sign-in.
        $this->post(route('two-factor.challenge.store'), ['code' => Totp::code($admin->two_factor_secret)]);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_plain_login_still_works_without_two_factor(): void
    {
        $user = $this->admin();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_secret_is_hidden_from_serialization(): void
    {
        $admin = $this->admin();
        $admin->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_enabled_at' => now(),
        ])->save();

        $this->actingAs($admin)->get('/profile');
        $this->assertStringNotContainsString($admin->two_factor_secret, $admin->toJson());
    }
}
