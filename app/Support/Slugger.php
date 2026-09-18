<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Builds a unique, readable address from a Russian name (TZ §6.6).
 *
 * A slug is created once and never changed by the import: the storefront address
 * of a product or a category belongs to the manager.
 */
final class Slugger
{
    public const int PRODUCT_LIMIT = 120;

    public const int CATEGORY_LIMIT = 160;

    /**
     * Transliterates and trims to the limit. Returns an empty string when nothing is left.
     */
    public static function make(string $source, int $limit = self::PRODUCT_LIMIT): string
    {
        $slug = Str::slug($source, language: 'ru');

        return trim(substr($slug, 0, $limit), '-');
    }

    /**
     * A slug no one has taken yet. On a collision the fallback (the supplier 1C code)
     * is appended, then a counter.
     *
     * @param  callable(string): bool  $taken
     */
    public static function unique(string $source, callable $taken, int $limit = self::PRODUCT_LIMIT, ?string $fallback = null): string
    {
        $base = self::make($source, $limit);

        if ($base === '') {
            $base = self::make((string) $fallback, $limit);
        }

        if ($base === '') {
            $base = 'tovar';
        }

        if (! $taken($base)) {
            return $base;
        }

        if (filled($fallback)) {
            $candidate = self::withTail($base, self::make($fallback, $limit), $limit);

            if ($candidate !== $base && ! $taken($candidate)) {
                return $candidate;
            }
        }

        for ($copy = 2; ; $copy++) {
            $candidate = self::withTail($base, (string) $copy, $limit);

            if (! $taken($candidate)) {
                return $candidate;
            }
        }
    }

    /**
     * Appends a tail, freeing room for it inside the limit. Slugs are ASCII, so bytes are characters.
     */
    private static function withTail(string $base, string $tail, int $limit): string
    {
        if ($tail === '') {
            return $base;
        }

        $room = $limit - strlen($tail) - 1;

        return trim(substr($base, 0, max(1, $room)), '-').'-'.$tail;
    }
}
