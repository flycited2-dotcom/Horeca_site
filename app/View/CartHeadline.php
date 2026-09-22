<?php

namespace App\View;

use App\Support\Money;
use App\Support\Typography;

/**
 * The cart in the header (layout — screen 5): «3 позиции» and the sum, or just «Корзина»
 * while it is empty. The same data goes to the storefront script after «В корзину».
 */
final readonly class CartHeadline
{
    public function __construct(
        public int $positions = 0,
        public ?Money $total = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->positions === 0;
    }

    public function label(): string
    {
        return $this->isEmpty()
            ? __('shop.cart.title')
            : trans_choice('shop.home.positions', $this->positions, ['count' => $this->positions]);
    }

    public function totalLabel(): string
    {
        return Typography::money($this->total ?? Money::zero());
    }

    /**
     * @return array{positions: int, label: string, total: string}
     */
    public function toArray(): array
    {
        return ['positions' => $this->positions, 'label' => $this->label(), 'total' => $this->totalLabel()];
    }
}
