<?php

namespace App\Console\Commands;

use App\Mail\SurveyEscalationMail;
use App\Models\Site;
use App\Services\SiteSurveyAnalytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Mail the sites whose user satisfaction has fallen below a threshold
 * (Plan.md S3, "Low-rating escalation").
 *
 * Read-only by construction: it mails a ranked list and writes nothing. The
 * plan's other half — opening a maintenance ticket automatically — is blocked
 * on two domain decisions rather than on code (see config/monitoring.php
 * `survey_escalation`), and a command that guessed a category enum and a
 * reporting user would put fabricated records in the ops queue.
 *
 * Inert until both `SURVEY_ESCALATION_MEAN_BELOW` and `SURVEY_ESCALATION_EMAIL`
 * are set; it says so and exits rather than guessing. The minimum-N guard is
 * the same one the reports use, so a site with two angry answers never becomes
 * an escalation.
 */
class SurveyEscalationDigest extends Command
{
    protected $signature = 'survey:escalate
        {--email= : Override the configured recipient}
        {--window= : Override the look-back window in days}';

    protected $description = 'Email the sites whose survey rating is below the configured threshold';

    public function handle(SiteSurveyAnalytics $analytics): int
    {
        $config = config('monitoring.survey_escalation');
        $threshold = $config['mean_below'];
        $recipient = $this->option('email') ?: $config['notify_email'];
        $windowDays = max(1, (int) ($this->option('window') ?: $config['window_days']));

        if ($threshold === null || $recipient === null) {
            $this->warn('Not configured — nothing sent. Set SURVEY_ESCALATION_MEAN_BELOW and SURVEY_ESCALATION_EMAIL (see config/monitoring.php).');

            return self::SUCCESS;
        }

        $ids = Site::where('status', 'active')->pluck('id');
        $summaries = $analytics->forSites($ids, $windowDays);

        $breaching = collect($summaries)
            ->filter(fn (array $s) => $s['meets_minimum'] && $s['overall'] < $threshold)
            ->sortBy(fn (array $s) => $s['overall']);

        if ($breaching->isEmpty()) {
            $this->info("No rated site is below {$threshold}/5 over the last {$windowDays} days.");

            return self::SUCCESS;
        }

        $names = Site::whereIn('id', $breaching->keys())->pluck('location_name', 'id');
        $rows = $breaching->map(fn (array $s, $id) => [
            'site' => $names->get($id, "#{$id}"),
            'rating' => (float) $s['overall'],
            'responses' => $s['responses'],
        ])->values()->all();

        Mail::to($recipient)->send(new SurveyEscalationMail(
            threshold: (float) $threshold,
            windowDays: $windowDays,
            minimumResponses: SiteSurveyAnalytics::MIN_RESPONSES,
            breaching: $rows,
            reportsUrl: route('reports.index'),
        ));

        $this->info("Escalation mailed to {$recipient} for {$breaching->count()} site(s).");
        $this->table(
            ['Site', 'Rating', 'Responses'],
            $breaching->map(fn (array $s, $id) => [
                $names->get($id, "#{$id}"),
                $s['overall'],
                $s['responses'],
            ])
        );

        return self::SUCCESS;
    }
}
