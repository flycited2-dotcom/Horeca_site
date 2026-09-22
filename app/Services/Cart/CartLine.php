<?php

namespace App\Services\Cart;

use App\Enums\Availability;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Pricing\Price;
use App\Support\Money;

/**
 * A line of the cart as the customer sees it now: the current price, the price it had when
 * it changed since the product was put in, and why it can not be bought any more.
 */
final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public Product $product,
        public ?Price $price,
        public ?Money $previousPrice = null,
        public ?CartBlock $block = null,
    ) {}

    public function quantity(): int
    {
        return $this->item->qty;
    }

    public function unitPrice(): Money
    {
        return $this->price?->amount ?? $this->item->price;
    }

    public function sum(): Money
    {
        return $this->unitPrice()->multiply($this->item->qty);
    }

    /**
     * «Срок поставки уточнит менеджер» (TZ §10.1).
     */
    public function onOrder(): bool
    {
        return $this->block === null && $this->product->availability === Availability::OnOrder;
    }

    /**
     * The weight of the line in grams, or null when the supplier did not give it.
     */
    public function weightGrams(): ?int
    {
        $weight = $this->product->weight_kg;

        if ($weight === null) {
            return null;
        }

        [$kilograms, $fraction] = array_pad(explode('.', (string) $weight, 2), 2, '');

        return ((int) $kilograms * 1000 + (int) str_pad(substr($fraction, 0, 3), 3, '0')) * $this->item->qty;
    }
}
