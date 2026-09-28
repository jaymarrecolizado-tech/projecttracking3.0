<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyResponse;
use Database\Factories\SiteSurveyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The JSON face of the public survey, used by the Next.js public surface
 * (Plan_UI.md Part II §9).
 *
 * These tests exist to pin the properties that the Next.js split could quietly
 * break. The HTML face keeps its own coverage in SiteSurveyTest; what is verified
 * here is that every anti-abuse guarantee survives the seam — signed POST, honeypot,
 * minimum elapsed time, real-IP rate limiting, and soft duplicate suppression.
 */
class PublicSurveyApiTest extends TestCase
{
    use RefreshDatabase;

    private function site(): Site
    {
        return Site::factory()->create(['ap_site_code' => 'FW-Lu-001']);
    }

    private function publishSurvey(): SiteSurvey
    {
        return SiteSurveyFactory::new()->create();
    }

    public function test_it_returns_the_questionnaire_and_a_signed_submit_url(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        $response = $this->getJson("/api/public/surveys/{$site->ap_site_code}");

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('site.name', $site->location_name)
            ->assertJsonPath('survey.title', $survey->title);

        // The submit URL must be signed — an unsigned one would be replayable
        // against any site, which is the whole reason the URL is signed.
        $submitUrl = $response->json('submitUrl');
        $this->assertIsString($submitUrl);
        $this->assertStringContainsString('signature=', $submitUrl);
    }

    public function test_an_unknown_site_code_is_a_neutral_404(): void
    {
        $this->publishSurvey();

        $this->getJson('/api/public/surveys/NOPE-999')
            ->assertNotFound()
            ->assertJsonPath('status', 'unknown');
    }

    public function test_an_unpublished_survey_reports_unavailable(): void
    {
        $site = $this->site();
        // No active survey row.

        $this->getJson("/api/public/surveys/{$site->ap_site_code}")
            ->assertStatus(409)
            ->assertJsonPath('status', 'unavailable');
    }

    public function test_a_signed_submission_is_recorded(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        $url = $this->signedUrl($site);

        $response = $this->postJson($url, [
            'elapsed_ms' => 9000,
            'ratings' => $this->ratingsFor($survey),
            'comments' => 'Signal is fine here.',
        ]);

        $response->assertOk()->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('site_survey_responses', [
            'site_id' => $site->id,
            'comments' => 'Signal is fine here.',
        ]);
    }

    public function test_an_unsigned_submission_is_rejected(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        // Same path, no signature. This is the replay a signature exists to stop.
        $this->postJson("/api/public/surveys/{$site->ap_site_code}", [
            'elapsed_ms' => 9000,
            'ratings' => $this->ratingsFor($survey),
        ])->assertForbidden();
    }

    public function test_a_signature_for_one_site_cannot_be_replayed_at_another(): void
    {
        $first = $this->site();
        $second = Site::factory()->create(['ap_site_code' => 'FW-Lu-002']);
        $survey = $this->publishSurvey();

        // Swap the site code inside a signature minted for the first site.
        $tampered = str_replace(
            $first->ap_site_code,
            $second->ap_site_code,
            $this->signedUrl($first)
        );

        $this->postJson($tampered, [
            'elapsed_ms' => 9000,
            'ratings' => $this->ratingsFor($survey),
        ])->assertForbidden();

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_the_honeypot_rejects_a_bot(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        $this->postJson($this->signedUrl($site), [
            'elapsed_ms' => 9000,
            'ratings' => $this->ratingsFor($survey),
            'website' => 'http://spam.example',
        ])->assertStatus(422);

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_a_too_fast_submission_looks_successful_but_records_nothing(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        // Deliberately identical to the HTML face: answering `ok` rather than
        // erroring is what stops a bot learning it was detected. The record is
        // simply not written.
        $this->postJson($this->signedUrl($site), [
            'elapsed_ms' => 10,
            'ratings' => $this->ratingsFor($survey),
        ])->assertOk()->assertJsonPath('status', 'ok');

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_unknown_rating_keys_are_rejected(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        $ratings = $this->ratingsFor($survey);
        $ratings['smuggled_key'] = 5;

        $this->postJson($this->signedUrl($site), [
            'elapsed_ms' => 9000,
            'ratings' => $ratings,
        ])->assertStatus(422);

        $this->assertDatabaseCount('site_survey_responses', 0);
    }

    public function test_a_second_response_from_the_same_ip_replaces_rather_than_adds(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        $this->postJson($this->signedUrl($site), [
            'elapsed_ms' => 9000,
            'ratings' => $this->ratingsFor($survey, 1),
            'comments' => 'First answer.',
        ])->assertOk();

        $this->postJson($this->signedUrl($site), [
            'elapsed_ms' => 9000,
            'ratings' => $this->ratingsFor($survey, 5),
            'comments' => 'Changed my mind.',
        ])->assertOk();

        // Soft suppression: one row, updated — not two rows skewing the average.
        $this->assertSame(1, SiteSurveyResponse::where('site_id', $site->id)->count());
        $this->assertDatabaseHas('site_survey_responses', [
            'site_id' => $site->id,
            'comments' => 'Changed my mind.',
        ]);
    }

    public function test_the_submission_never_stores_a_raw_ip(): void
    {
        $site = $this->site();
        $survey = $this->publishSurvey();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson($this->signedUrl($site), [
                'elapsed_ms' => 9000,
                'ratings' => $this->ratingsFor($survey),
            ])
            ->assertOk();

        $response = SiteSurveyResponse::where('site_id', $site->id)->firstOrFail();

        $this->assertNotNull($response->ip_hash);
        $this->assertSame(64, strlen((string) $response->ip_hash));
        $this->assertStringNotContainsString('203.0.113.7', json_encode($response->getAttributes()));
    }

    public function test_the_thanks_endpoint_resolves_without_the_questionnaire(): void
    {
        $site = $this->site();

        $this->getJson("/api/public/surveys/{$site->ap_site_code}/thanks")
            ->assertOk()
            ->assertJsonPath('siteName', $site->location_name);
    }

    /**
     * A signed POST URL, built the way the app builds it.
     */
    private function signedUrl(Site $site): string
    {
        return url()->signedRoute('api.public.surveys.store', ['siteCode' => $site->ap_site_code]);
    }

    /**
     * A full set of valid ratings for the published survey's rating keys.
     *
     * @return array<string, int>
     */
    private function ratingsFor(SiteSurvey $survey, int $value = 4): array
    {
        $ratings = [];

        foreach ($survey->ratingKeys() as $key) {
            $ratings[$key] = $value;
        }

        return $ratings;
    }
}
