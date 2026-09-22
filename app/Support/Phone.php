<?php

namespace App\Support;

/**
 * Российский номер телефона (ТЗ §10.2): как бы его ни ввели — «8 978 123 45 67»,
 * «+7(978)1234567», «9781234567», — хранится в одном виде «+7 978 123-45-67».
 */
final class Phone
{
    /**
     * The ten digits after +7, or null when this is not a Russian number.
     */
    public static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    public static function normalize(?string $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === null) {
            return null;
        }

        return sprintf('+7 %s %s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2));
    }
}
