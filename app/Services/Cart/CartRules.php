<?php

namespace App\Services\Cart;

use App\Enums\Availability;
use App\Models\Product;
use App\Services\Pricing\Price;

/**
 * What may be bought (TZ §6.5, §10.1): a product on the storefront, still made and with
 * a price. «Под заказ» and «Ожидается» may — their term is confirmed by the manager.
 */
final class CartRules
{
    public const int MAX_QUANTITY = 9999;

    public function block(Product $product, ?Price $price): ?CartBlock
    {
        return match (true) {
            ! $product->is_visible => CartBlock::Hidden,
            $product->availability === Availability::Discontinued => CartBlock::Discontinued,
            $price === null => CartBlock::PriceOnRequest,
            default => null,
        };
    }

    /**
     * A whole number from 1 to 9999: anything else from a form is brought into range.
     */
    public static function quantity(mixed $value): int
    {
        $quantity = is_numeric($value) ? (int) $value : 1;

        return max(1, min(self::MAX_QUANTITY, $quantity));
    }
}
