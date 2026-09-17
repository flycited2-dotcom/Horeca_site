<?php

namespace App\Services\Supplier\Import;

/**
 * Cleans supplier text without rewriting it: whitespace only (TZ §6.1, fact 8).
 */
final class SupplierText
{
    /**
     * Collapses whitespace including non-breaking spaces, trims, turns empty into null, cuts to the limit.
     */
    public static function clean(?string $value, ?int $limit = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $value));

        if ($value === '') {
            return null;
        }

        return $limit === null ? $value : mb_substr($value, 0, $limit);
    }

    /**
     * A matching key for names that must compare regardless of case and spacing.
     */
    public static function key(string $value): string
    {
        return mb_strtolower(self::clean($value) ?? '');
    }
}
