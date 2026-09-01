<?php

return [
    // Fallback recipient for DOWN alerts / warranty digest.
    'watchdog_email' => env('WATCHDOG_EMAIL'),

    // Telegram alert channel (free — no gateway contract needed). Create a bot
    // with @BotFather, put its token here, and the chat_id of the NOC group.
    // Both must be set for Telegram alerts to fire; otherwise email-only.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
];
