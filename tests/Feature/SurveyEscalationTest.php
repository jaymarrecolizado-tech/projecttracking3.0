<?php

namespace Tests\Feature;

use App\Mail\SurveyEscalationMail;
use App\Models\MaintenanceTicket;
use App\Models\Site;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Low-rating escalation (Plan.md S3). The command is a notification, not a
 * ticket writer: `maintenance_tickets.category` is an enum with no
 * `satisfaction` value and `reported_by` is NOT NULL, so an automatic ticket
 * would need two owner decisions first. What is tested here is the part that
 * exists — and, more importantly, that it does nothing at all until the owner
 * supplies a threshold and a recipient.
 */
class SurveyEscalationTest extends TestCase
{
    use RefreshDatabase;

    private ?SiteSurvey $survey = null;

    /** code is unique, so one survey row serves the whole test. */
    private function survey(): SiteSurvey
    {
        return $this->survey ??= SiteSurvey::create([
            'code' => 'v1', 'title' => 'Survey', 'is_active' => true,
            'questions' => [['key' => 'overall', 'label' => 'Overall?', 'type' => 'rating', 'required' => true]],
        ]);
    }

    private function siteWithRating(string $name, int $responses, int $score): Site
    {
        $site = Site::factory()->create(['location_name' => $name, 'status' => 'active']);
        SiteSurveyResponse::factory()->count($responses)->create([
            'site_id' => $site->id, 'survey_id' => $this->survey()->id,
            'ratings' => ['overall' => $score],
        ]);

        return $site;
    }

    private function configure(?float $threshold = 3.0, ?string $recipient = 'ops@example.com'): void
    {
        config()->set('monitoring.survey_escalation', [
            'mean_below' => $threshold,
            'notify_email' => $recipient,
            'window_days' => 30,
        ]);
    }

    public function test_it_sends_nothing_until_a_threshold_and_a_recipient_exist(): void
    {
        Mail::fake();
        $this->siteWithRating('Bad Site', 6, 1);

        // Neither set: the default production state.
        $this->artisan('survey:escalate')
            ->expectsOutputToContain('Not configured — nothing sent')
            ->assertSuccessful();

        // Threshold only, still no recipient.
        $this->configure(3.0, null);
        $this->artisan('survey:escalate')->expectsOutputToContain('Not configured')->assertSuccessful();

        // Recipient only, still no threshold.
        $this->configure(null, 'ops@example.com');
        $this->artisan('survey:escalate')->expectsOutputToContain('Not configured')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_mails_the_breaching_sites_worst_first(): void
    {
        Mail::fake();
        $this->configure();
        $this->siteWithRating('Terrible Site', 6, 1);
        $this->siteWithRating('Poor Site', 6, 2);
        $this->siteWithRating('Fine Site', 6, 5);

        $this->artisan('survey:escalate')
            ->expectsOutputToContain('Escalation mailed to ops@example.com')
            ->assertSuccessful();

        Mail::assertSentCount(1);
        Mail::assertSent(function (SurveyEscalationMail $mail) {
            $body = $mail->render();

            $this->assertStringContainsString('2 site(s) below 3/5', $body);
            $this->assertStringContainsString('Terrible Site: 1.0/5 from 6 response(s)', $body);
            $this->assertStringContainsString('Poor Site: 2.0/5 from 6 response(s)', $body);
            $this->assertStringNotContainsString('Fine Site', $body);
            // Worst first, so the top of the mail is the thing to act on.
            $this->assertLessThan(strpos($body, 'Poor Site'), strpos($body, 'Terrible Site'));
            $this->assertStringContainsString('A rating needs 5+ responses per site', $body);
            $this->assertTrue($mail->hasTo('ops@example.com'));

            return true;
        });
    }

    /** The minimum-N guard is the same one the reports use. */
    public function test_a_site_below_the_threshold_but_below_minimum_n_is_not_escalated(): void
    {
        Mail::fake();
        $this->configure();
        $this->siteWithRating('Two Angry Answers', 2, 1);

        $this->artisan('survey:escalate')
            ->expectsOutputToContain('No rated site is below 3/5')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_it_writes_nothing_to_the_ops_queue(): void
    {
        Mail::fake();
        $this->configure();
        $this->siteWithRating('Terrible Site', 6, 1);

        $this->artisan('survey:escalate')->assertSuccessful();

        $this->assertSame(0, MaintenanceTicket::count());
        $this->assertSame(6, SiteSurveyResponse::count(), 'The responses are read, never written.');
    }
}
