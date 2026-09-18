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
     * "383995" → "383 995". Done on the digits, so no float ever touches the amount.
     */
    private static function groupDigits(string $digits): string
    {
        $head = strlen($digits) % 3;
        $groups = $head > 0 ? [substr($digits, 0, $head)] : [];

        return implode(self::NBSP, [...$groups, ...str_split(substr($digits, $head), 3)]);
    }
}
