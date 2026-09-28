<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyResponse;
use App\Models\User;
use App\Services\SiteCodeResolver;
use App\Services\SiteSurveyAnalytics;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Per-site survey (Plan.md S2/S5/S7).
 *
 * The two things most likely to break silently: (1) a merged duplicate's site
 * code losing its surveys, and (2) the minimum-N guard failing to suppress a
 * meaningless average. Both are asserted directly.
 */
class SiteSurveyTest extends TestCase
{
    use RefreshDatabase;

    private function survey(): SiteSurvey
    {
        return SiteSurvey::create([
            'code' => 'v1',
            'title' => 'Free WiFi Experience Survey',
            'is_active' => true,
            'questions' => [
                ['key' => 'overall', 'label' => 'Overall?', 'type' => 'rating', 'required' => true],
                ['key' => 'speed', 'label' => 'Speed?', 'type' => 'rating', 'required' => true],
            ],
        ]);
    }

    private function submitUrl(Site $site): string
    {
        return URL::signedRoute('survey.store', ['siteCode' => $site->ap_site_code]);
    }

    private function validPayload(): array
    {
        return [
            'ratings' => ['overall' => 4, 'speed' => 5],
            'comments' => 'Good connection',
            'elapsed_ms' => 9000,
        ];
    }

    // ── S2: site binding ────────────────────────────────────────────────────

    public function test_public_survey_form_renders_for_a_known_site(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-ABC123', 'location_name' => 'Ilagan Plaza']);

        $this->get(route('survey.show', ['siteCode' => 'F-ABC123']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Survey/Form')
                ->where('site.name', 'Ilagan Plaza'));
    }

    public function test_survey_page_is_reachable_without_authentication(): void
    {
        $this->survey();
        Site::factory()->create(['ap_site_code' => 'F-PUBLIC']);

        // The whole point of the feature: a stranger on the portal can reach it.
        $this->assertGuest();
        $this->get(route('survey.show', ['siteCode' => 'F-PUBLIC']))->assertOk();
    }

    public function test_unknown_site_code_shows_neutral_page_not_an_error(): void
    {
        $this->survey();

        $this->get(route('survey.show', ['siteCode' => 'NOPE-404']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Survey/Unknown'));
    }

    public function test_submission_is_recorded_against_the_resolved_site(): void
    {
        $this->survey();
        $site = Site::factory()->create([
            'ap_site_code' => 'F-RECORD1',
            'cms_provider' => 'PHILCOMSAT',
            'last_mile_tech' => 'LEO',
        ]);

        $this->post($this->submitUrl($site), $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('site_survey_responses', [
            'site_id' => $site->id,
            'ap_site_code' => 'F-RECORD1',
            // Provider captured at submit time (Plan.md S2).
            'cms_provider' => 'PHILCOMSAT',
            'last_mile_tech' => 'LEO',
        ]);
    }

    /**
     * The single likeliest bug (Plan.md S2): sites:dedupe hides a duplicate and
     * stamps metadata.merged_into. A stale QR code must still land on the
     * survivor, or every merged site silently loses its surveys.
     */
    public function test_a_merged_duplicate_code_resolves_to_the_canonical_site(): void
    {
        $this->survey();
        $canonical = Site::factory()->create(['ap_site_code' => 'F-CANON']);

        $duplicate = Site::factory()->create([
            'ap_site_code' => 'F-STALE',
            'metadata' => ['merged_into' => $canonical->id],
        ]);
        $duplicate->delete();

        $this->assertSame(
            $canonical->id,
            app(SiteCodeResolver::class)->resolve('F-STALE')?->id,
        );

        $this->post($this->submitUrl($canonical), $this->validPayload());

        // Submitted under the canonical code, but the row records the real site.
        $this->assertDatabaseHas('site_survey_responses', ['site_id' => $canonical->id]);
        $this->assertDatabaseCount('site_survey_responses', 1);
    }

    // ── S1/S5: anti-abuse ───────────────────────────────────────────────────

    public function test_unsigned_post_is_rejected(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-UNSIGNED']);

        // No signature at all — the route must not accept it.
        $this->post('/s/F-UNSIGNED', $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_honeypot_submission_is_rejected(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-BOT']);

        $payload = $this->validPayload();
        $payload['website'] = 'http://spam.example';
        $this->post($this->submitUrl($site), $payload)
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_ratings_are_bounded_and_required(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-BOUNDS']);

        $this->post($this->submitUrl($site), ['ratings' => ['overall' => 9, 'speed' => 3], 'elapsed_ms' => 9000])
            ->assertSessionHasErrors('ratings.overall');

        $this->post($this->submitUrl($site), ['ratings' => ['speed' => 3], 'elapsed_ms' => 9000])
            ->assertSessionHasErrors('ratings.overall');

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_unknown_question_key_is_rejected(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-EXTRA']);

        $this->post($this->submitUrl($site), [
            'ratings' => ['overall' => 4, 'speed' => 5, 'injected' => 5],
            'elapsed_ms' => 9000,
        ])->assertSessionHasErrors('ratings');

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_a_second_submission_from_the_same_respondent_replaces_rather_than_doubles(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-DUP']);

        $this->post($this->submitUrl($site), $this->validPayload());
        $this->post($this->submitUrl($site), [
            'ratings' => ['overall' => 1, 'speed' => 1],
            'comments' => 'Changed my mind',
            'elapsed_ms' => 9000,
        ]);

        // Soft suppression: one row, updated — not two rows inflating the count.
        $this->assertDatabaseCount('site_survey_responses', 1);
        $this->assertSame([1, 1], array_values(SiteSurveyResponse::first()->ratings));
    }

    public function test_a_too_fast_submission_is_not_recorded(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-FAST']);

        // 500ms — faster than a human can read the questions.
        $payload = $this->validPayload();
        $payload['elapsed_ms'] = 500;
        $this->post($this->submitUrl($site), $payload)->assertRedirect();

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_ip_address_is_never_stored_only_its_hash(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-PRIVACY']);

        $this->post($this->submitUrl($site), $this->validPayload());

        $row = SiteSurveyResponse::first();
        $this->assertNotNull($row->ip_hash);
        $this->assertSame(64, strlen($row->ip_hash));
        // No column anywhere in the table carries the raw address.
        $this->assertFalse(array_key_exists('ip_address', $row->getAttributes()));
    }

    // ── S6: aggregation and the minimum-N guard ──────────────────────────────

    public function test_average_is_withheld_below_the_minimum_response_count(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-FEW']);
        SiteSurveyResponse::factory()->count(4)->create([
            'site_id' => $site->id,
            'survey_id' => SiteSurvey::first()->id,
            'ratings' => ['overall' => 5, 'speed' => 5],
        ]);

        $summary = app(SiteSurveyAnalytics::class)->forSite($site);

        $this->assertSame(4, $summary['responses']);
        $this->assertFalse($summary['meets_minimum'], 'Four responses must not produce a rating.');
    }

    public function test_average_is_reported_at_or_above_the_minimum(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-ENOUGH']);
        SiteSurveyResponse::factory()->count(5)->create([
            'site_id' => $site->id,
            'survey_id' => SiteSurvey::first()->id,
            'ratings' => ['overall' => 4, 'speed' => 2],
        ]);

        $summary = app(SiteSurveyAnalytics::class)->forSite($site);

        $this->assertTrue($summary['meets_minimum']);
        $this->assertSame(3.0, $summary['overall']); // mean of (4 + 2)
    }

    public function test_provider_rollup_groups_by_provider_and_transport(): void
    {
        $this->survey();
        $survey = SiteSurvey::first();

        SiteSurveyResponse::factory()->count(6)->create([
            'survey_id' => $survey->id,
            'cms_provider' => 'PHILCOMSAT',
            'last_mile_tech' => 'LEO',
            'ratings' => ['overall' => 5, 'speed' => 5],
        ]);
        SiteSurveyResponse::factory()->count(6)->create([
            'survey_id' => $survey->id,
            'cms_provider' => 'PHILCOMSAT',
            'last_mile_tech' => 'VSAT',
            'ratings' => ['overall' => 2, 'speed' => 2],
        ]);

        $rollup = collect(app(SiteSurveyAnalytics::class)->byProvider())
            ->keyBy(fn ($row) => $row['cms_provider'].'|'.$row['last_mile_tech']);

        // Same provider, different transport must be reported separately.
        $this->assertSame(6, $rollup['PHILCOMSAT|LEO']['responses']);
        $this->assertSame(6, $rollup['PHILCOMSAT|VSAT']['responses']);
        $this->assertSame(5.0, $rollup['PHILCOMSAT|LEO']['overall']);
        $this->assertSame(2.0, $rollup['PHILCOMSAT|VSAT']['overall']);
    }

    // ── S8: aggregation for the report packs ────────────────────────────────

    public function test_scope_rollup_averages_every_answer_not_the_site_averages(): void
    {
        $this->survey();
        $survey = SiteSurvey::first();
        $quiet = Site::factory()->create();
        $busy = Site::factory()->create();

        // 1 response at 1, 9 at 5. Mean-of-site-means would say 5.0; the
        // honest number for "how do users rate us" is 4.6.
        SiteSurveyResponse::factory()->create([
            'site_id' => $quiet->id, 'survey_id' => $survey->id,
            'ratings' => ['overall' => 1, 'speed' => 1],
        ]);
        SiteSurveyResponse::factory()->count(9)->create([
            'site_id' => $busy->id, 'survey_id' => $survey->id,
            'ratings' => ['overall' => 5, 'speed' => 5],
        ]);

        $analytics = app(SiteSurveyAnalytics::class);

        $this->assertSame(10, $analytics->forScope([$quiet->id, $busy->id])['responses']);
        $this->assertSame(4.6, $analytics->forScope([$quiet->id, $busy->id])['overall']);
    }

    public function test_scope_rollup_is_empty_for_no_sites(): void
    {
        $this->survey();

        $this->assertSame(
            ['responses' => 0, 'meets_minimum' => false, 'overall' => null],
            array_intersect_key(
                app(SiteSurveyAnalytics::class)->forScope([]),
                array_flip(['responses', 'meets_minimum', 'overall'])
            )
        );
    }

    public function test_provider_rollup_can_be_scoped_to_the_sites_in_a_report(): void
    {
        $this->survey();
        $survey = SiteSurvey::first();
        $inScope = Site::factory()->create();
        $outOfScope = Site::factory()->create();

        SiteSurveyResponse::factory()->count(6)->create([
            'site_id' => $inScope->id, 'survey_id' => $survey->id,
            'cms_provider' => 'DICT', 'last_mile_tech' => 'RADIO',
            'ratings' => ['overall' => 5, 'speed' => 5],
        ]);
        SiteSurveyResponse::factory()->count(6)->create([
            'site_id' => $outOfScope->id, 'survey_id' => $survey->id,
            'cms_provider' => 'PHILCOMSAT', 'last_mile_tech' => 'LEO',
            'ratings' => ['overall' => 1, 'speed' => 1],
        ]);

        $rollup = collect(app(SiteSurveyAnalytics::class)->byProvider(30, [$inScope->id]));

        // A province report must not quote the national provider numbers.
        $this->assertCount(1, $rollup);
        $this->assertSame('DICT', $rollup[0]['cms_provider']);
    }

    /**
     * Plan.md #4a: the workbook spells one provider two ways, so a raw grouping
     * reported a 1-response company beside its own 139-response sibling. The
     * responses keep the spelling they were recorded with; only the rollup is
     * folded, and the counts add up rather than being averaged.
     */
    public function test_provider_rollup_folds_case_variants_of_the_same_company(): void
    {
        $this->survey();
        $survey = SiteSurvey::first();

        SiteSurveyResponse::factory()->count(3)->create([
            'survey_id' => $survey->id, 'cms_provider' => 'IT BUSINESS SOLUTIONS',
            'last_mile_tech' => 'LEO', 'ratings' => ['overall' => 5, 'speed' => 5],
        ]);
        SiteSurveyResponse::factory()->count(2)->create([
            'survey_id' => $survey->id, 'cms_provider' => 'IT Business Solutions',
            'last_mile_tech' => 'LEO', 'ratings' => ['overall' => 1, 'speed' => 1],
        ]);

        $rollup = app(SiteSurveyAnalytics::class)->byProvider();

        $this->assertCount(1, $rollup, 'One company must not be two rows.');
        $this->assertSame('IT Business Solutions', $rollup[0]['cms_provider']);
        $this->assertSame(5, $rollup[0]['responses'], 'Counts add; they are not averaged.');
        $this->assertSame(3.4, $rollup[0]['overall'], '(3×5 + 2×1) / 5, computed over the answers themselves.');
    }

    /** An unrecognised provider keeps its own name rather than being guessed at. */
    public function test_provider_rollup_keeps_an_unregistered_provider_visible(): void
    {
        $this->survey();
        $survey = SiteSurvey::first();

        SiteSurveyResponse::factory()->count(6)->create([
            'survey_id' => $survey->id, 'cms_provider' => 'Nowhere Telecom',
            'last_mile_tech' => 'LEO', 'ratings' => ['overall' => 2, 'speed' => 2],
        ]);

        $rollup = app(SiteSurveyAnalytics::class)->byProvider();

        $this->assertSame('Nowhere Telecom', $rollup[0]['cms_provider']);
    }

    public function test_response_rate_uses_the_window_it_was_asked_for(): void
    {
        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-WINDOW']);
        SiteSurveyResponse::factory()->count(2)->create([
            'site_id' => $site->id, 'survey_id' => SiteSurvey::first()->id,
            'ratings' => ['overall' => 4, 'speed' => 4],
            'submitted_at' => now(),
        ]);

        // One day inside the 7-day window with 1,000 users, and one day outside
        // it with another 1,000. A 7-day window must divide by 1,000 (0.2%); the
        // 30-day default divides by 2,000 (0.1%). The old code hardcoded the
        // 30-day denominator for every caller, so the two always agreed.
        foreach ([1, 20] as $daysAgo) {
            SiteDailyStatus::factory()->create([
                'site_id' => $site->id,
                'date' => today()->subDays($daysAgo),
                'status' => 'UP',
                'total_unique_users' => 1000,
            ]);
        }

        $analytics = app(SiteSurveyAnalytics::class);

        $this->assertSame(0.2, $analytics->forSite($site, 7)['response_rate']);
        $this->assertSame(0.1, $analytics->forSite($site, 30)['response_rate']);
    }

    // ── S8: the field QR placard ────────────────────────────────────────────

    public function test_qr_sheet_prints_one_placard_per_site_in_the_filtered_scope(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);
        $this->survey();

        Site::factory()->create(['ap_site_code' => 'F-QR-1', 'location_name' => 'Placard One', 'province' => 'Cagayan']);
        Site::factory()->create(['ap_site_code' => 'F-QR-2', 'location_name' => 'Placard Two', 'province' => 'Isabela']);

        $response = $this->actingAs($admin)->get(route('sites.survey-qr', ['province' => 'Cagayan']));

        $response->assertOk();
        $response->assertSee('Placard One');
        $response->assertDontSee('Placard Two');
        // chillerlan returns a base64 data URI, which is what the <img> needs.
        $response->assertSee('data:image/svg+xml;base64,', false);
    }

    public function test_qr_sheet_is_not_public(): void
    {
        $this->assertGuest();
        $this->get(route('sites.survey-qr'))->assertRedirect(route('login'));
    }

    public function test_qr_sheet_states_its_own_truncation_instead_of_dropping_sites_silently(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);
        $this->survey();

        // One more than the sheet limit, which is why the notice must appear:
        // an earlier PDF appendix silently dropped rows past its cap.
        Site::factory()->count(201)->create();

        $html = $this->actingAs($admin)->get(route('sites.survey-qr'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Showing 200 of 201 sites/', $html);
    }

    public function test_survey_url_is_null_until_a_survey_is_published(): void
    {
        $this->assertNull(SiteSurvey::urlFor('F-ANY'));

        $this->survey();

        $url = SiteSurvey::urlFor('F-ANY');
        $this->assertStringContainsString('/s/F-ANY', $url);
        // Unsigned on purpose: survey.show has no `signed` middleware, so a
        // signature would only bloat the printed QR.
        $this->assertStringNotContainsString('signature=', $url);
    }

    // ── Admin surface ───────────────────────────────────────────────────────

    public function test_site_show_exposes_satisfaction_without_exposing_the_uptime_formula(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $this->survey();
        $site = Site::factory()->create(['ap_site_code' => 'F-SHOW']);

        $this->actingAs($admin)->get(route('sites.show', $site))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sites/Show')
                ->has('satisfaction')
                ->has('surveyUrl'));
    }
}
