<?php

namespace App\Services\Notifications;

/**
 * Откуда пришёл покупатель, одной строкой для сообщения в Telegram: «yandex / cpc / kafe».
 * Метки запоминает RememberUtm; без меток переход был прямой или из поиска.
 */
final class UtmSource
{
    /**
     * @param  array<string, mixed>|null  $utm
     */
    public static function describe(?array $utm): ?string
    {
        $parts = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $key) {
            $value = $utm[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(' / ', $parts);
    }
}
