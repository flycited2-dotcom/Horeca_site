<?php

namespace App\Services\Notifications;

use App\Jobs\SendTelegramMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Messages to the managers' Telegram group (TZ §13).
 *
 * The token and the chat id live only in .env. While they are not filled in, messages go
 * to the log instead, so nothing in the shop waits for Telegram.
 */
final class TelegramNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.chat_id'));
    }

    /**
     * Queues the message on the default queue: imports must not hold up order notifications.
     */
    public function send(string $message): void
    {
        if (! $this->isConfigured()) {
            Log::info('Telegram не настроен, сообщение записано в журнал.', ['message' => $message]);

            return;
        }

        SendTelegramMessage::dispatch($message);
    }

    /**
     * The request itself. A failure throws, so the job retries it.
     */
    public function deliver(string $message): void
    {
        Http::timeout((int) config('services.telegram.timeout'))
            ->asForm()
            ->post('https://api.telegram.org/bot'.config('services.telegram.token').'/sendMessage', [
                'chat_id' => config('services.telegram.chat_id'),
                'text' => $message,
                'disable_web_page_preview' => true,
            ])
            ->throw();
    }
}
