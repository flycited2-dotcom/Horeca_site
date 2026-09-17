<?php

namespace App\Services\Pricing;

use App\Enums\PriceKind;
use App\Models\Supplier;
use App\Support\Money;
use App\Support\Percent;

/**
 * Retail price from the supplier price (TZ §7). null means "price on request".
 */
final class RetailPriceCalculator
{
    public function forSupplier(Supplier $supplier, ?Money $rrpPrice, ?Money $purchasePrice): ?Money
    {
        return $this->calculate($supplier->price_kind, $supplier->markup_percent, $supplier->retail_round_to, $rrpPrice, $purchasePrice);
    }

    public function calculate(PriceKind $kind, Percent $markup, int $roundTo, ?Money $rrpPrice, ?Money $purchasePrice): ?Money
    {
        $base = match ($kind) {
            PriceKind::Rrp => $rrpPrice,
            PriceKind::Dealer => $purchasePrice?->withMarkup($markup),
        };

        if ($base === null || $base->isZero() || $base->isNegative()) {
            return null;
        }

        return $base->roundUpToRubles(max(1, $roundTo));
    }
}
