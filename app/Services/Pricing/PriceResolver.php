<?php

namespace App\Services\Pricing;

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\User;
use App\Services\Settings\Settings;
use App\Support\Money;
use App\Support\Percent;

/**
 * Which price a customer pays (TZ §7). null means "Цена по запросу".
 *
 * Retail customers pay the retail price. A customer of an approved company pays the price
 * of its tier: a fixed price from product_prices or the retail price minus the tier
 * discount, rounded up to whole rubles. Either way the price never drops below the floor:
 * - purchase price unknown — the discount is capped by pricing.max_discount_without_purchase,
 *   because selling below the RRP at an unknown cost may lose money;
 * - purchase price known — not below purchase × (1 + pricing.min_margin_percent).
 * A wholesale price is never above the retail one.
 *
 * For listings, eager-load the tier prices: products->load(['prices' => fn ($q) => $q->where(...)]).
 */
final class PriceResolver
{
    public function __construct(private readonly Settings $settings) {}

    public function for(Product $product, ?User $user): ?Price
    {
        $retail = $product->retail_price;

        if ($retail === null) {
            return null;
        }

        $tier = $this->tierOf($user);

        if ($tier === null) {
            return new Price($retail, $retail, oldPrice: $this->oldPrice($product, $retail));
        }

        return new Price(
            amount: $this->wholesale($product, $retail, $tier),
            retail: $retail,
            isWholesale: true,
            tierName: $this->settings->boolean('pricing.show_tier_name', false) ? $tier->name : null,
        );
    }

    /**
     * The tier of a customer whose company has been approved, otherwise null.
     */
    public function tierOf(?User $user): ?PriceTier
    {
        if ($user === null || ! $user->hasApprovedCompany()) {
            return null;
        }

        return $user->company?->priceTier;
    }

    private function wholesale(Product $product, Money $retail, PriceTier $tier): Money
    {
        $fixed = $this->fixedPrice($product, $tier);

        $price = $fixed ?? $retail->withDiscount($this->discount($product, $tier))->roundUpToRubles();

        return $this->min($this->max($price, $this->floor($product, $retail)), $retail);
    }

    /**
     * The tier discount, capped while the purchase price is unknown.
     */
    private function discount(Product $product, PriceTier $tier): Percent
    {
        $discount = $tier->discount_percent ?? Percent::zero();

        if ($product->purchase_price === null) {
            return $discount->min($this->maxDiscountWithoutPurchase());
        }

        return $discount;
    }

    private function floor(Product $product, Money $retail): Money
    {
        if ($product->purchase_price === null) {
            return $retail->withDiscount($this->maxDiscountWithoutPurchase())->roundUpToRubles();
        }

        $margin = $this->settings->percent('pricing.min_margin_percent') ?? Percent::zero();

        return $product->purchase_price->withMarkup($margin)->roundUpToRubles();
    }

    private function fixedPrice(Product $product, PriceTier $tier): ?Money
    {
        $prices = $product->relationLoaded('prices')
            ? $product->prices
            : $product->prices()->where('price_tier_id', $tier->id)->get();

        return $prices->firstWhere('price_tier_id', $tier->id)?->price;
    }

    private function oldPrice(Product $product, Money $retail): ?Money
    {
        return $product->old_price !== null && $product->old_price->greaterThan($retail) ? $product->old_price : null;
    }

    private function maxDiscountWithoutPurchase(): Percent
    {
        return $this->settings->percent('pricing.max_discount_without_purchase') ?? Percent::zero();
    }

    private function max(Money $a, Money $b): Money
    {
        return $a->lessThan($b) ? $b : $a;
    }

    private function min(Money $a, Money $b): Money
    {
        return $a->greaterThan($b) ? $b : $a;
    }
}
