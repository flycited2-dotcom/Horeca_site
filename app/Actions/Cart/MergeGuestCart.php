<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartRules;
use App\Services\Cart\CartStore;
use App\Services\Pricing\PriceResolver;
use Illuminate\Support\Facades\DB;

/**
 * Гость вошёл (ТЗ §10.1): его корзина сливается с корзиной клиента — количества
 * суммируются, цены пересчитываются под клиента (оптовику — его цены). Что купить уже
 * нельзя, не переносится. Гостевая корзина удаляется, браузер её забывает.
 */
final class MergeGuestCart
{
    public function __construct(
        private readonly CartStore $carts,
        private readonly CartRules $rules,
        private readonly PriceResolver $prices,
    ) {}

    public function handle(Cart $guest, User $user): void
    {
        DB::transaction(function () use ($guest, $user): void {
            $items = $guest->items()->get();

            if ($items->isNotEmpty()) {
                $cart = $this->carts->currentOrCreate($user);
                $products = Product::query()->whereKey($items->pluck('product_id'))->get()->keyBy('id');
                $prices = $this->prices->forMany($products, $user);

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    $price = $prices[$item->product_id] ?? null;

                    if ($product === null || $price === null || $this->rules->block($product, $price) !== null) {
                        continue;
                    }

                    $target = $cart->items()->firstOrNew(['product_id' => $item->product_id]);
                    $target->qty = CartRules::quantity(($target->exists ? $target->qty : 0) + $item->qty);
                    $target->price = $price->amount;
                    $target->save();
                }
            }

            $guest->delete();
        });

        $this->carts->forgetGuest();
    }
}
