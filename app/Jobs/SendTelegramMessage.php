<?php

namespace App\Jobs;

use App\Services\Notifications\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one Telegram message with retries (TZ §13). A failing Telegram never breaks
 * an order or an import: the job just gives up and the failure stays in the log.
 */
final class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(private readonly string $message)
    {
        $this->onQueue('default');
    }

    public function handle(TelegramNotifier $telegram): void
    {
        $telegram->deliver($this->message);
    }
}
