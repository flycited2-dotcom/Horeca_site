<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CannotAddToCart;
use App\Services\Cart\CartBlock;
use App\Services\Cart\CartRules;
use App\Services\Cart\CartStore;
use App\Services\Pricing\PriceResolver;

/**
 * Кладёт товар в корзину (ТЗ §10.1): количество прибавляется к уже лежащему, цена
 * фиксируется на момент добавления (§7). Цена по запросу, снятый с производства
 * и скрытый менеджером товар не кладутся — CannotAddToCart.
 */
final class AddToCart
{
    public function __construct(
        private readonly CartStore $carts,
        private readonly CartRules $rules,
        private readonly PriceResolver $prices,
    ) {}

    public function handle(Product $product, int $quantity, ?User $user): CartItem
    {
        $price = $this->prices->for($product, $user);
        $block = $this->rules->block($product, $price);

        if ($block !== null || $price === null) {
            throw new CannotAddToCart($block ?? CartBlock::PriceOnRequest);
        }

        $cart = $this->carts->currentOrCreate($user);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);

        $item->qty = CartRules::quantity(($item->exists ? $item->qty : 0) + $quantity);
        $item->price = $price->amount;
        $item->save();

        return $item;
    }
}
