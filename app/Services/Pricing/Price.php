<?php

namespace App\Services\Pricing;

use App\Support\Money;

/**
 * The price one customer sees for one product (TZ §7).
 *
 * A wholesale customer sees the retail price crossed out next to "Ваша цена"; a manual
 * promotion (old price) is shown only to retail customers, so there are never three prices.
 */
final readonly class Price
{
    public function __construct(
        public Money $amount,
        public Money $retail,
        public bool $isWholesale = false,
        public ?string $tierName = null,
        public ?Money $oldPrice = null,
    ) {}

    public function hasDiscount(): bool
    {
        return $this->amount->lessThan($this->retail);
    }
}
