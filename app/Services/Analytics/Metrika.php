<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Pricing\Price;
use App\Support\Money;

/**
 * События Яндекс Метрики (ТЗ §14): цели add_to_cart, checkout_start, order_created,
 * lead_created (с типом лида), bulk_order и электронная коммерция через dataLayer —
 * просмотр товара, добавление в корзину, покупка. Событие — массив
 * {goal, params, ecommerce}; скрипт витрины отправляет его, только если посетитель
 * согласился на аналитические cookie и номер счётчика задан (§15.10). Имя и телефон
 * клиента в события не попадают.
 */
final class Metrika
{
    public const string SESSION_KEY = 'metrika';

    /**
     * @param  array<string, scalar>  $params
     * @param  array<string, mixed>|null  $ecommerce
     * @return array{goal: ?string, params: array<string, scalar>, ecommerce: ?array<string, mixed>}
     */
    public static function event(?string $goal, array $params = [], ?array $ecommerce = null): array
    {
        return ['goal' => $goal, 'params' => $params, 'ecommerce' => $ecommerce];
    }

    /**
     * @return array{goal: ?string, params: array<string, scalar>, ecommerce: ?array<string, mixed>}
     */
    public static function detail(Product $product, ?Price $price): array
    {
        return self::event(null, [], self::ecommerce('detail', ['products' => [self::product($product, $price?->amount)]]));
    }

    /**
     * @return array{goal: ?string, params: array<string, scalar>, ecommerce: ?array<string, mixed>}
     */
    public static function addToCart(Product $product, Money $price, int $quantity): array
    {
        return self::event('add_to_cart', [], self::ecommerce('add', ['products' => [self::product($product, $price) + ['quantity' => $quantity]]]));
    }

    /**
     * @return array{goal: ?string, params: array<string, scalar>, ecommerce: ?array<string, mixed>}
     */
    public static function purchase(Order $order): array
    {
        return self::event('order_created', [], self::ecommerce('purchase', [
            'actionField' => ['id' => $order->number, 'revenue' => $order->total->toDecimal()],
            'products' => $order->items->map(fn (OrderItem $item): array => array_filter([
                'id' => $item->sku ?? $item->supplier_code ?? (string) $item->product_id,
                'name' => $item->name,
                'price' => $item->price->toDecimal(),
                'quantity' => $item->qty,
            ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all(),
        ]));
    }

    /**
     * An event for the next page: the form went without scripts and the browser follows a redirect.
     *
     * @param  array{goal: ?string, params: array<string, scalar>, ecommerce: ?array<string, mixed>}  $event
     */
    public static function flash(array $event): void
    {
        $events = session()->get(self::SESSION_KEY, []);

        session()->flash(self::SESSION_KEY, [...(is_array($events) ? $events : []), $event]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function ecommerce(string $action, array $data): array
    {
        return ['currencyCode' => 'RUB', $action => $data];
    }

    /**
     * @return array<string, scalar>
     */
    private static function product(Product $product, ?Money $price): array
    {
        return array_filter([
            'id' => $product->sku ?? $product->supplier_code ?? (string) $product->id,
            'name' => $product->name,
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,
            'price' => $price?->toDecimal(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
