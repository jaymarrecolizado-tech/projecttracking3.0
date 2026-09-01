<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Telegram Bot API client for operational alerts. Deliberately
 * fire-and-forget: an unreachable Telegram must never break the scheduled
 * alert run, so failures log and return false.
 */
class Telegram
{
    public function sendMessage(string $text): bool
    {
        $token = config('monitoring.telegram.bot_token');
        $chatId = config('monitoring.telegram.chat_id');

        if (! $token || ! $chatId) {
            return false;
        }

        try {
            return Http::asJson()->timeout(10)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ],
            )->successful();
        } catch (ConnectionException $e) {
            report($e);

            return false;
        }
    }

    public function configured(): bool
    {
        return (bool) (config('monitoring.telegram.bot_token') && config('monitoring.telegram.chat_id'));
    }
}
