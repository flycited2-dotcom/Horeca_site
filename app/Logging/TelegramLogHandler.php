<?php

namespace App\Logging;

use App\Services\Notifications\TelegramNotifier;
use Illuminate\Support\Facades\Cache;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Критические записи журнала — в Telegram (ТЗ §15.9, §19: «падение приходит в Telegram»).
 *
 * Отправка идёт сразу и с коротким сроком, а не через очередь: когда ломается Redis или
 * база, очередью не воспользоваться. Обработчик журнала не имеет права ронять сайт, поэтому
 * любой собственный сбой глотается. Одинаковое сообщение не чаще раза в десять минут,
 * иначе одна поломка на каждый запрос завалила бы группу. Пока токен и группа не заданы,
 * ничего не происходит.
 */
final class TelegramLogHandler extends AbstractProcessingHandler
{
    public const int REPEAT_MINUTES = 10;

    public const int TIMEOUT_SECONDS = 5;

    public const int MAX_LENGTH = 1200;

    public function __construct(int|string|Level $level = Level::Critical, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $telegram = app(TelegramNotifier::class);

            if (! $telegram->isConfigured()) {
                return;
            }

            $text = $this->text($record);

            if (! $this->isNew($text)) {
                return;
            }

            $telegram->deliver($text, self::TIMEOUT_SECONDS);
        } catch (Throwable) {
            // Журнал не должен ронять то, что он записывает.
        }
    }

    private function text(LogRecord $record): string
    {
        $lines = [__('backup.telegram.alert', [
            'site' => (string) config('app.name'),
            'env' => (string) config('app.env'),
        ]), $record->message];

        $exception = $record->context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            // Сообщение исключения уже стоит первой строкой, когда журнал записал именно его.
            $lines[] = $exception::class.($exception->getMessage() === $record->message ? '' : ': '.$exception->getMessage());
            $lines[] = basename($exception->getFile()).':'.$exception->getLine();
        }

        return mb_substr(implode("\n", $lines), 0, self::MAX_LENGTH);
    }

    /**
     * The same text is sent once per REPEAT_MINUTES; a cache that is down does not stop the alert.
     */
    private function isNew(string $text): bool
    {
        try {
            return Cache::add('telegram-log:'.md5($text), 1, now()->addMinutes(self::REPEAT_MINUTES));
        } catch (Throwable) {
            return true;
        }
    }
}
