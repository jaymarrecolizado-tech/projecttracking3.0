<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteSurvey;
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

    /**
     * Regression guard: the Vite prefetch bootstrap is a SECOND inline script
     * that does not come from Blade — it is emitted by Laravel's Vite class, so
     * it only gets a nonce via Vite::useCspNonce(). Asserting the Ziggy tag
     * alone let that script ship un-nonced and get blocked by the policy in
     * production. Every inline script must carry the header nonce.
     */
    public function test_every_inline_script_carries_the_header_nonce(): void
    {
        $admin = $this->admin();
        $this->app->instance('env', 'production');

        $response = $this->actingAs($admin)->get('/')->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m[1] ?? null, 'the policy carries a nonce');
        $nonce = $m[1];

        preg_match_all('/<script\b([^>]*)>/i', (string) $response->getContent(), $tags);

        // Inline = no src attribute; those are the only ones the nonce protects.
        $inline = array_values(array_filter(
            $tags[1],
            fn (string $attrs) => ! str_contains($attrs, ' src='),
        ));

        $this->assertNotEmpty($inline, 'the page should render at least one inline script');

        foreach ($inline as $attrs) {
            $this->assertStringContainsString(
                'nonce="'.$nonce.'"',
                $attrs,
                'every inline <script> must carry the CSP nonce, got: <script'.$attrs.'>',
            );
        }
    }

    /**
     * Plan.md S1 — the survey is the first page a *stranger* loads, so the
     * public surface must serve the same hardened policy as the internal one.
     * A route added outside the `auth` group is exactly where that could
     * silently regress.
     */
    public function test_public_survey_page_serves_the_csp(): void
    {
        SiteSurvey::create([
            'code' => 'v1',
            'title' => 'Survey',
            'is_active' => true,
            'questions' => [['key' => 'overall', 'label' => 'Overall?', 'type' => 'rating', 'required' => true]],
        ]);
        $site = Site::factory()->create(['ap_site_code' => 'F-CSP']);

        $this->app->instance('env', 'production');

        // Unauthenticated on purpose — this is the public route.
        $response = $this->get(route('survey.show', ['siteCode' => 'F-CSP']))->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertNotEmpty($response->headers->get('Strict-Transport-Security'));

        // Every inline script on the public page carries the same nonce.
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $nonce = $m[1];
        preg_match_all('/<script\b([^>]*)>/i', (string) $response->getContent(), $tags);
        $inline = array_values(array_filter($tags[1], fn (string $a) => ! str_contains($a, ' src=')));

        foreach ($inline as $attrs) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $attrs, 'public inline <script> must carry the nonce');
        }
    }
}
