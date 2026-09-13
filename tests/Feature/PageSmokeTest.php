<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan.md gap #5 (cheap layer) — the shipped "blank page" and "500" bugs
 * were invisible to unit tests. This render smoke hits the main boards and
 * asserts each returns its page instead of an error.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_pages_render(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $pages = [
            '/' => 'Dashboard',
            '/daily-ops' => 'DailyOps/Index',
            '/map' => 'Map/Index',
            '/reports' => 'Reports/Index',
            '/wallboard' => 'Wallboard',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }
}
