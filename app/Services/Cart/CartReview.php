<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogQuery;
use App\Services\Pricing\PriceResolver;
use App\Services\Settings\Settings;
use App\Support\Money;
use App\View\CartHeadline;

/**
 * Сверка корзины при каждом открытии (ТЗ §7, §10.1): цена сравнивается с зафиксированной
 * при добавлении — при расхождении позиция помечается «Цена изменилась» и цена
 * пересчитывается; товар, который больше нельзя купить, подсвечивается и держит
 * оформление, пока его не удалят.
 */
final class CartReview
{
    public function __construct(
        private readonly CartStore $carts,
        private readonly CartRules $rules,
        private readonly CatalogQuery $catalog,
        private readonly PriceResolver $prices,
        private readonly Settings $settings,
    ) {}

    public function review(?User $user): CartSummary
    {
        $threshold = $this->settings->integer('delivery.free_city_from', 0);
        $freeFrom = $threshold > 0 ? Money::ofRubles($threshold) : null;

        $cart = $this->carts->current($user);
        $items = $cart?->items()->orderBy('id')->get() ?? collect();

        if ($items->isEmpty()) {
            return new CartSummary(freeDeliveryFrom: $freeFrom);
        }

        $products = $this->catalog
            ->withCardData(Product::query()->whereKey($items->pluck('product_id')), $user)
            ->get()
            ->keyBy('id');
        $prices = $this->prices->forMany($products, $user);

        $lines = [];

        foreach ($items as $item) {
            $product = $products->get($item->product_id);

            if ($product === null) {
                continue;
            }

            $price = $prices[$product->id] ?? null;
            $block = $this->rules->block($product, $price);
            $previous = null;

            if ($block === null && $price !== null && ! $price->amount->equals($item->price)) {
                $previous = $item->price;
                $item->price = $price->amount;
                $item->save();
            }

            $lines[] = new CartLine($item, $product, $price, $previous, $block);
        }

        return new CartSummary($lines, $freeFrom);
    }

    /**
     * For the header: how many positions and for how much, by the prices in the cart —
     * one query, the check happens on the cart page.
     */
    public function headline(?User $user): CartHeadline
    {
        $cart = $this->carts->current($user);

        if ($cart === null) {
            return new CartHeadline;
        }

        $row = CartItem::query()
            ->where('cart_id', $cart->id)
            ->toBase()
            ->selectRaw('COUNT(*) AS positions, COALESCE(SUM(qty * price), 0) AS total')
            ->first();

        return new CartHeadline((int) ($row->positions ?? 0), Money::fromDecimal((string) ($row->total ?? '0')));
    }
}
