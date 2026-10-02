<?php

namespace App\Services\Supplier\Sources\Rosholod;

use App\Services\Supplier\Data\SupplierAttribute;
use App\Services\Supplier\Data\SupplierProductDetails;
use App\Services\Supplier\Import\AttributeValueParser;

/**
 * Description and characteristics of a Rosholod product as our details (TZ §6): the same
 * dictionary «название, единица: значение» comes from the supplier's site and from its API,
 * so both sources turn it into SupplierProductDetails here, the one way.
 */
final class RosholodDetailsMapper
{
    public const string COUNTRY = 'Страна производства';

    /**
     * Characteristics that are columns of a product, not characteristics.
     */
    private const array COLUMNS = [
        'Длина, мм' => 'length',
        'Ширина, мм' => 'width',
        'Глубина, мм' => 'depth',
        'Высота, мм' => 'height',
        'Вес, кг' => 'weight',
        'Гарантия (месяцев)' => 'warranty',
    ];

    /**
     * The brand comes with the price list already.
     */
    private const array SKIPPED = ['Бренд'];

    /**
     * A dash in the supplier's table means "no value", not a value of its own.
     */
    private const array BLANK = ['-', '—', '–'];

    /**
     * @param  mixed  $characteristics  «название: значение»; a value of another kind than text or number is skipped
     */
    public static function details(string $externalId, mixed $description, mixed $characteristics, ?string $country): SupplierProductDetails
    {
        $columns = [];
        $attributes = [];

        foreach (is_array($characteristics) ? $characteristics : [] as $key => $value) {
            $text = self::text($value);

            if (! is_string($key) || $text === null || in_array($text, ['', ...self::BLANK], true) || in_array(trim($key), self::SKIPPED, true)) {
                continue;
            }

            $key = trim($key);

            if (isset(self::COLUMNS[$key])) {
                $columns[self::COLUMNS[$key]] = $text;
            } else {
                $attributes[] = new SupplierAttribute($key, $text);
            }
        }

        // «Ширина» is the width; without it «Глубина» is. When both come, the depth stays a characteristic.
        $width = AttributeValueParser::integer($columns['width'] ?? null);

        if ($width === null) {
            $width = AttributeValueParser::integer($columns['depth'] ?? null);
        } elseif (isset($columns['depth'])) {
            $attributes[] = new SupplierAttribute('Глубина, мм', $columns['depth']);
        }

        if (is_string($country) && trim($country) !== '') {
            $attributes[] = new SupplierAttribute(self::COUNTRY, mb_convert_case(trim($country), MB_CASE_TITLE));
        }

        $weight = AttributeValueParser::number($columns['weight'] ?? null);

        return new SupplierProductDetails(
            externalId: $externalId,
            description: self::description($description),
            attributes: $attributes,
            lengthMm: AttributeValueParser::integer($columns['length'] ?? null),
            widthMm: $width,
            heightMm: AttributeValueParser::integer($columns['height'] ?? null),
            weightKg: $weight !== null && $weight !== '0' && ! str_starts_with($weight, '-') ? $weight : null,
            warrantyMonths: AttributeValueParser::integer($columns['warranty'] ?? null),
        );
    }

    /**
     * The description as plain text: no markup, and without the heading «Описание» the site
     * sometimes glues to the first word («ОписаниеСтол холодильный…»).
     */
    public static function description(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $value));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/^Описание(?=\p{Lu})/u', '', trim($text));
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/\s*\n\s*/u', "\n", $text);

        return trim($text) === '' ? null : trim($text);
    }

    /**
     * A characteristic value as text. The API gives values of different JSON types: a number
     * or a flag is turned into text, a list or an object is not a value we can show.
     */
    private static function text(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => trim($value),
            is_int($value) => (string) $value,
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            is_bool($value) => $value ? 'Да' : 'Нет',
            default => null,
        };
    }
}
