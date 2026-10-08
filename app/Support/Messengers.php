<?php

namespace App\Support;

use App\Services\Settings\Settings;

/**
 * Мессенджеры магазина из «Настроек» (ТЗ §5.5): Telegram и MAX. Администратор вводит
 * ссылку как привык — «@gastrosnab», «t.me/gastrosnab», «https://max.ru/u/…», — витрина
 * показывает кнопку, только если из значения получилась ссылка на сам мессенджер.
 * Чужой адрес или «javascript:» кнопкой не станет.
 */
final class Messengers
{
    public const string TELEGRAM = 'telegram';

    public const string MAX = 'max';

    /**
     * Setting key of each messenger, in the order the buttons go.
     */
    public const array KEYS = [
        self::TELEGRAM => 'contacts.telegram',
        self::MAX => 'contacts.max',
    ];

    private const array TELEGRAM_HOSTS = ['t.me', 'telegram.me'];

    private const array MAX_HOSTS = ['max.ru', 'web.max.ru'];

    /**
     * The link to a Telegram chat or channel, or null when the value is not one.
     */
    public static function telegram(?string $value): ?string
    {
        $value = trim((string) $value);

        // «@gastrosnab» or «gastrosnab»: a Telegram username, 5–32 letters, digits and «_».
        if (preg_match('/^@?([A-Za-z][A-Za-z0-9_]{3,30}[A-Za-z0-9])$/', $value, $match) === 1) {
            return 'https://t.me/'.$match[1];
        }

        return self::url($value, self::TELEGRAM_HOSTS);
    }

    /**
     * The link to a MAX chat or channel, or null when the value is not one. MAX has no
     * short names, so only a link from the app is accepted.
     */
    public static function max(?string $value): ?string
    {
        return self::url(trim((string) $value), self::MAX_HOSTS);
    }

    public static function link(string $messenger, ?string $value): ?string
    {
        return match ($messenger) {
            self::TELEGRAM => self::telegram($value),
            self::MAX => self::max($value),
            default => null,
        };
    }

    /**
     * The buttons the storefront shows, in the order of KEYS; a messenger without a valid link is left out.
     *
     * @return list<array{key: string, label: string, href: string}>
     */
    public static function links(Settings $settings): array
    {
        $settings->preload(...array_values(self::KEYS));

        $result = [];

        foreach (self::KEYS as $messenger => $key) {
            $value = $settings->get($key);
            $href = self::link($messenger, is_string($value) ? $value : null);

            if ($href !== null) {
                $result[] = ['key' => $messenger, 'label' => __("shop.messengers.{$messenger}"), 'href' => $href];
            }
        }

        return $result;
    }

    /**
     * An https link on one of the hosts with a path; «t.me/name» without the scheme is accepted too.
     *
     * @param  list<string>  $hosts
     */
    private static function url(string $value, array $hosts): ?string
    {
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if (! in_array($host, $hosts, true) || trim($path, '/') === '') {
            return null;
        }

        return 'https://'.$host.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
