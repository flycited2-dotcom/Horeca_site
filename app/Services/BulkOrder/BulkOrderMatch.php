<?php

namespace App\Services\BulkOrder;

use App\Models\Product;
use App\Services\Pricing\Price;

/**
 * Строка заказа списком после сопоставления с каталогом: статус, найденный товар и его цена
 * для клиента, а при нескольких совпадениях — товары на выбор.
 */
final readonly class BulkOrderMatch
{
    /**
     * @param  list<Product>  $candidates  every product the article or 1C code points to
     * @param  array<int, Price|null>  $prices  product id => price for this customer
     * @param  list<int>  $buyable  the candidates that may go into the cart
     */
    public function __construct(
        public BulkOrderLine $line,
        public BulkOrderStatus $status,
        public array $candidates = [],
        public array $prices = [],
        public array $buyable = [],
    ) {}

    public function isBuyable(Product $product): bool
    {
        return in_array($product->id, $this->buyable, true);
    }

    /**
     * The one product of a line that points to one product.
     */
    public function product(): ?Product
    {
        return count($this->candidates) === 1 ? $this->candidates[0] : null;
    }

    public function price(Product $product): ?Price
    {
        return $this->prices[$product->id] ?? null;
    }
}
