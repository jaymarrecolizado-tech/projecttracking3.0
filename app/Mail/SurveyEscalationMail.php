<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sites whose survey satisfaction fell below the configured threshold.
 *
 * A Mailable rather than `Mail::raw` for three reasons, in order of weight:
 * `MailFake::raw()` is a no-op, so a raw-text digest cannot be asserted in a
 * test at all; a mailable is queued like the rest of the scheduled mail; and it
 * renders through a template a person can edit without touching a command.
 */
class SurveyEscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{site: string, rating: float, responses: int}>  $breaching
     */
    public function __construct(
        public float $threshold,
        public int $windowDays,
        public int $minimumResponses,
        public array $breaching,
        public string $reportsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[FPIAP · FreeWiFi] {$this->threshold}/5 satisfaction — {$this->breachingCount()} site(s) below target",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.survey-escalation',
            with: [
                'threshold' => $this->threshold,
                'windowDays' => $this->windowDays,
                'minimumResponses' => $this->minimumResponses,
                'breaching' => $this->breaching,
                'reportsUrl' => $this->reportsUrl,
            ],
        );
    }

    private function breachingCount(): int
    {
        return count($this->breaching);
    }
}
