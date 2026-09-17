<?php

namespace App\Services\Search;

/**
 * Builds products.search_text (TZ §8.4): normalized words plus compact codes.
 *
 * "Плита индукционная КИП-27Н-3,5", article 71000019569 →
 * "плита индукционная кип-27н-3,5 abat 71000019569 цб-ц0017339 ... кип27н35".
 */
final class SearchTextBuilder
{
    public function build(?string $name, ?string $model, ?string $sku, ?string $supplierCode, ?string $brand): string
    {
        $words = self::words(implode(' ', array_filter([$name, $model, $brand, $sku, $supplierCode], filled(...))));

        $codes = array_values(array_unique(array_filter(
            array_map(self::compact(...), [$sku, $supplierCode, $model]),
            fn (string $code): bool => $code !== '',
        )));

        return trim($words.' '.implode(' ', $codes));
    }

    /**
     * Lower case, "ё" as "е", single spaces.
     */
    public static function words(string $text): string
    {
        $text = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $text));

        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text));
    }

    /**
     * A code without separators: "КИП-27Н-3,5" → "кип27н35".
     */
    public static function compact(?string $code): string
    {
        return (string) preg_replace('/[\s\x{00A0}\x{202F}\-.\/,_]+/u', '', self::words((string) $code));
    }
}
