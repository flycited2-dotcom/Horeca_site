<?php

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\CannotAddToCart;

/**
 * «Повторить заказ» (ТЗ §11, сценарий 5 из §2): позиции прежней заявки ложатся в корзину
 * клиента по текущим ценам — через AddToCart, как с витрины. Что купить уже нельзя — товар
 * удалён, снят с производства, скрыт или с ценой по запросу, — в корзину не идёт и
 * возвращается списком «Не добавлены».
 */
final class RepeatOrder
{
    public const string DELETED = 'deleted';

    public function __construct(private readonly AddToCart $addToCart) {}

    public function handle(Order $order, User $user): RepeatOrderResult
    {
        $order->loadMissing('items.product');

        $added = 0;
        $skipped = [];

        foreach ($order->items as $item) {
            $product = $item->product;

            if ($product === null) {
                $skipped[] = ['name' => $item->name, 'sku' => $item->sku, 'slug' => null, 'reason' => self::DELETED];

                continue;
            }

            try {
                $this->addToCart->handle($product, $item->qty, $user);
                $added++;
            } catch (CannotAddToCart $exception) {
                $skipped[] = ['name' => $item->name, 'sku' => $item->sku, 'slug' => $product->slug, 'reason' => $exception->block->value];
            }
        }

        return new RepeatOrderResult($added, $skipped);
    }
}
