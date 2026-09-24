<?php

namespace App\Support;

/**
 * Data typography of the design system (CLAUDE.md, TZ §9): "383 995 ₽" with non-breaking
 * spaces, so a price never breaks across lines.
 */
final class Typography
{
    public const string NBSP = "\u{00A0}";

    /**
     * Whole rubles without kopecks, otherwise two digits after a comma: "48 605,80 ₽".
     */
    public static function money(Money $money): string
    {
        $kopecks = abs($money->kopecks);
        $fraction = $kopecks % 100 === 0 ? '' : ','.str_pad((string) ($kopecks % 100), 2, '0', STR_PAD_LEFT);

        return ($money->isNegative() ? '−' : '').self::groupDigits((string) intdiv($kopecks, 100)).$fraction.self::NBSP.'₽';
    }

    /**
     * A count with grouped digits: "7 021", so a number never breaks across lines.
     */
    public static function number(int $value): string
    {
        return ($value < 0 ? '−' : '').self::groupDigits((string) abs($value));
    }

    /**
     * A value of a characteristic as it is stored: «1300.000» → «1 300», «0.286» → «0,286».
     * Done on the digits, so no float ever touches it.
     */
    public static function decimal(string $value): string
    {
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $whole = ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0');

        return ($negative ? '−' : '').self::groupDigits($whole).($fraction === '' ? '' : ','.$fraction);
    }

    /**
     * "383995" → "383 995". Done on the digits, so no float ever touches the amount.
     */
    private static function groupDigits(string $digits): string
    {
        $head = strlen($digits) % 3;
        $groups = $head > 0 ? [substr($digits, 0, $head)] : [];

        return implode(self::NBSP, [...$groups, ...str_split(substr($digits, $head), 3)]);
    }

    /**
     * «18,5 кг», «39 кг», «0,25 кг»: grams as kilograms with a decimal comma, no float.
     */
    public static function kilograms(int $grams): string
    {
        $whole = intdiv($grams, 1000);
        $rest = rtrim(str_pad((string) ($grams % 1000), 3, '0', STR_PAD_LEFT), '0');

        return self::number($whole).($rest === '' ? '' : ','.$rest).self::NBSP.'кг';
    }
}
