<?php

namespace App\Services\Supplier\Import;

use App\Services\Supplier\Data\SupplierAttribute;
use Illuminate\Support\Str;

/**
 * Characteristics of a supplier (TZ §6): the key carries the unit after a comma —
 * «Потребляемая мощность, Вт» — and the value is text as the supplier typed it: «1 300»,
 * «0,286», «380В», «Есть». A value becomes a number only when the key has a unit and the whole
 * value is a number; everything else stays text. Numbers are decimal strings, never floats.
 */
final class AttributeValueParser
{
    /**
     * @return array{name: string, unit: ?string, slug: string, number: ?string, text: string}
     */
    public function parse(SupplierAttribute $attribute): array
    {
        $key = trim((string) preg_replace('/\s+/u', ' ', $attribute->key));
        $unit = null;
        $name = $key;

        if (preg_match('/^(.+),\s*([^,]{1,16})$/u', $key, $match) === 1) {
            $name = trim($match[1]);
            $unit = trim($match[2]);
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', str_replace(['¶', "\u{00A0}"], ' ', $attribute->rawValue)));

        return [
            'name' => $name,
            'unit' => $unit,
            'slug' => Str::limit(Str::slug($key), 160, ''),
            'number' => $unit !== null ? self::number($text) : null,
            'text' => $text,
        ];
    }

    /**
     * «1 350» → «1350», «0,286» → «0.286»; anything that is not a plain number → null.
     */
    public static function number(?string $value): ?string
    {
        $value = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) $value));
        $value = str_replace(',', '.', $value);

        return preg_match('/^-?\d{1,9}(\.\d{1,3})?$/', $value) === 1 ? $value : null;
    }

    /**
     * A whole number for columns such as dimensions: «1 620» → 1620; a fraction or text → null.
     */
    public static function integer(?string $value): ?int
    {
        $number = self::number($value);

        return $number !== null && ! str_contains($number, '.') && (int) $number > 0 ? (int) $number : null;
    }
}
