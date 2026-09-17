<?php

namespace App\Services\Supplier\Import;

use App\Support\Money;
use InvalidArgumentException;

/**
 * Parses supplier prices in both feed formats: "48 605,8" (non-breaking space, comma) and "72581.5".
 */
final class PriceNormalizer
{
    /**
     * @return Money|null null for an empty value
     *
     * @throws InvalidArgumentException when the value is not a price
     */
    public function parse(?string $raw): ?Money
    {
        if ($raw === null) {
            return null;
        }

        $value = str_ireplace(['руб.', 'руб', '₽'], '', $raw);
        $value = (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);

        if (preg_match('/^-?\d+(\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid supplier price [{$raw}].");
        }

        return Money::fromDecimal($value);
    }
}
