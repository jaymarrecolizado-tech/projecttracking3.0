<?php

return [
    // Fallback recipient for DOWN alerts / warranty digest.
    'watchdog_email' => env('WATCHDOG_EMAIL'),

    // Monthly scheduled-report summary. Falls back to the watchdog address
    // so the pack never queues silently with nowhere to announce it.
    'reports_email' => env('REPORT_SCHEDULED_EMAIL', env('WATCHDOG_EMAIL')),

    // Telegram alert channel (free — no gateway contract needed). Create a bot
    // with @BotFather, put its token here, and the chat_id of the NOC group.
    // Both must be set for Telegram alerts to fire; otherwise email-only.
    // Comma-separated approved firmware strings; a device whose latest beat
    // reports anything else is "outdated" (alert metric firmware_outdated).
    // Empty list disables the check.
    'approved_firmware' => array_values(array_filter(explode(',', (string) env('APPROVED_FIRMWARE', '')))),

    // Uptime the program is held to, as a percentage. Unset by default: no SLA
    // target has been confirmed by DICT, so the reports must not imply one.
    // Once set, the period-health pack prints a PASS/FAIL against it and
    // ReportNarrative stops hedging. Whether NO_NMS counts against the target
    // is the same question as config/daily_status.php 'observed' — moving a
    // status out of that list moves it out of the SLA denominator everywhere.
    'sla_uptime_target' => env('SLA_UPTIME_TARGET') !== null && env('SLA_UPTIME_TARGET') !== ''
        ? (float) env('SLA_UPTIME_TARGET')
        : null,

    // Low-rating escalation (Plan.md S3, "Low-rating escalation"). Off until a
    // threshold AND a recipient are both set: an escalation that fires on a
    // guessed number mails the wrong people daily, which is worse than none.
    //
    // Deliberately email-only. The plan also says "auto-ticket", and that half
    // is genuinely blocked on two domain decisions, not on plumbing:
    // maintenance_tickets.category is an enum with no `satisfaction` value, and
    // reported_by is NOT NULL — a scheduled command has no user to attribute a
    // ticket to, so it would have to invent one. When the owner adds the enum
    // value and names a system account, this is where the ticket is raised.
    'survey_escalation' => [
        'mean_below' => env('SURVEY_ESCALATION_MEAN_BELOW') !== null && env('SURVEY_ESCALATION_MEAN_BELOW') !== ''
            ? (float) env('SURVEY_ESCALATION_MEAN_BELOW')
            : null,
        'notify_email' => env('SURVEY_ESCALATION_EMAIL') ?: null,
        'window_days' => 30,
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
];
