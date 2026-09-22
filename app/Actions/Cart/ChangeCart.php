<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\User;
use App\Services\Cart\CartRules;
use App\Services\Cart\CartStore;

/**
 * Количество, удаление и очистка корзины (ТЗ §10.1). Позиция ищется только в корзине
 * текущего посетителя: чужую так не изменить и не удалить. Удалённое можно вернуть —
 * «Вернуть» кладёт товар заново тем же количеством по текущей цене.
 */
final class ChangeCart
{
    public function __construct(private readonly CartStore $carts) {}

    public function setQuantity(int $productId, mixed $quantity, ?User $user): ?CartItem
    {
        $item = $this->item($productId, $user);

        if ($item === null) {
            return null;
        }

        $item->qty = CartRules::quantity($quantity);
        $item->save();
        $this->carts->currentOrCreate($user);

        return $item;
    }

    /**
     * @return array{product_id: int, qty: int}|null what «Вернуть» needs
     */
    public function remove(int $productId, ?User $user): ?array
    {
        $item = $this->item($productId, $user);

        if ($item === null) {
            return null;
        }

        $item->delete();

        return ['product_id' => $item->product_id, 'qty' => $item->qty];
    }

    public function clear(?User $user): void
    {
        $this->carts->current($user)?->items()->delete();
    }

    private function item(int $productId, ?User $user): ?CartItem
    {
        return $this->carts->current($user)?->items()->where('product_id', $productId)->first();
    }
}
