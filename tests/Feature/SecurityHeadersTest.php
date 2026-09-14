<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan.md F2 — the Ziggy inline script carries the request nonce and the
 * production CSP allows exactly that nonce (plus self). Dev stays
 * header-free so Vite HMR keeps working.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(1);

        return $user;
    }

    public function test_production_serves_csp_with_matching_nonce(): void
    {
        $admin = $this->admin();
        // config('app.env') is snapshotted at bootstrap — flip the binding.
        // Must run after seeding: db:seed confirms on production.
        $this->app->instance('env', 'production');
        $response = $this->actingAs($admin)->get('/')->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertNotEmpty($response->headers->get('Strict-Transport-Security'));

        // NOTE: BladeRouteGenerator::$generated is process-static — if an
        // earlier test already rendered @routes, this one takes the
        // Object.assign merge path instead of the const Ziggy= path.
        preg_match('/<script type="text\/javascript" nonce="([^"]+)">(?:const Ziggy=|Object\.assign\(Ziggy)/', $response->getContent(), $m);
        $this->assertNotEmpty($m[1] ?? null, 'Ziggy inline script carries a nonce');
        $this->assertStringContainsString("'nonce-{$m[1]}'", $csp);
    }

    public function test_non_production_serves_no_csp_or_hsts(): void
    {
        $response = $this->actingAs($this->admin())->get('/')->assertOk();

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNotEmpty($response->headers->get('X-Content-Type-Options'));
    }
}
