<?php

namespace App\Services\Cart;

use App\Support\Money;

/**
 * The cart after the check on opening (TZ §10.1): the lines, the total of what can be
 * bought, the weight when every line has one and how much is left to free delivery.
 */
final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public array $lines = [],
        public ?Money $freeDeliveryFrom = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function positions(): int
    {
        return count($this->lines);
    }

    public function units(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity(), $this->lines));
    }

    public function total(): Money
    {
        $total = Money::zero();

        foreach ($this->lines as $line) {
            if ($line->block === null) {
                $total = $total->add($line->sum());
            }
        }

        return $total;
    }

    /**
     * A line that can no longer be bought holds the checkout until it is removed.
     */
    public function hasBlocked(): bool
    {
        return array_filter($this->lines, fn (CartLine $line): bool => $line->block !== null) !== [];
    }

    public function canCheckout(): bool
    {
        return ! $this->isEmpty() && ! $this->hasBlocked();
    }

    public function hasChangedPrices(): bool
    {
        return array_filter($this->lines, fn (CartLine $line): bool => $line->previousPrice !== null) !== [];
    }

    /**
     * The total weight in grams — only when it is known for every line.
     */
    public function weightGrams(): ?int
    {
        $weight = 0;

        foreach ($this->lines as $line) {
            $grams = $line->weightGrams();

            if ($grams === null) {
                return null;
            }

            $weight += $grams;
        }

        return $this->isEmpty() ? null : $weight;
    }

    /**
     * What is left to free delivery in the city, when the threshold is set and not reached.
     */
    public function freeDeliveryLeft(): ?Money
    {
        if ($this->freeDeliveryFrom === null || $this->isEmpty() || ! $this->total()->lessThan($this->freeDeliveryFrom)) {
            return null;
        }

        return $this->freeDeliveryFrom->subtract($this->total());
    }
}
