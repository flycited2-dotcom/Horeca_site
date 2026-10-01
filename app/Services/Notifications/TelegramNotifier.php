<?php

namespace App\Services\Notifications;

use App\Jobs\SendTelegramMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
     * The request itself. A failure throws, so the job retries it. The address of the request
     * holds the token of the bot, and the exception of the HTTP client repeats that address in
     * its text: it goes to the log and to the group, so the token is cut out of it here and
     * the original exception is not chained.
     */
    public function deliver(string $message, ?int $timeout = null): void
    {
        try {
            Http::timeout($timeout ?? (int) config('services.telegram.timeout'))
                ->asForm()
                ->post('https://api.telegram.org/bot'.config('services.telegram.token').'/sendMessage', [
                    'chat_id' => config('services.telegram.chat_id'),
                    'text' => $message,
                    'disable_web_page_preview' => true,
                ])
                ->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException(self::withoutToken($exception->getMessage()));
        }
    }

    /**
     * The text with the token of the bot, wherever it stands, replaced by a mark.
     */
    public static function withoutToken(string $text): string
    {
        $token = (string) config('services.telegram.token');

        if ($token !== '') {
            $text = str_replace($token, '<токен>', $text);
        }

        return (string) preg_replace('~bot\d{6,}:[A-Za-z0-9_-]{20,}~', 'bot<токен>', $text);
    }
}
