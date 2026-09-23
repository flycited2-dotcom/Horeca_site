<?php

namespace App\Actions\Orders;

/**
 * Итог «Повторить заказ»: сколько позиций легло в корзину и какие не добавлены и почему.
 * Причина — значение CartBlock или RepeatOrder::DELETED; подпись — shop.account.repeat.reasons.
 */
final readonly class RepeatOrderResult
{
    /**
     * @param  list<array{name: string, sku: ?string, slug: ?string, reason: string}>  $skipped
     */
    public function __construct(
        public int $added,
        public array $skipped,
    ) {}
}
