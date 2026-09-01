<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StatusReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportDate,
        public array $perProject, // [project name => missing count]
        public string $reportUrl,
    ) {}

    public function envelope(): Envelope
    {
        $total = array_sum($this->perProject);

        return new Envelope(
            subject: "[FPIAP · FreeWiFi] {$total} sites awaiting {$this->reportDate} report",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.status-reminder',
            with: [
                'reportDate' => $this->reportDate,
                'perProject' => $this->perProject,
                'reportUrl' => $this->reportUrl,
            ],
        );
    }
}
