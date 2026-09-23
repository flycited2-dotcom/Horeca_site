<?php

namespace App\Services\BulkOrder;

/**
 * Что вышло со строкой заказа списком (ТЗ §11, сценарий 4 из §2): найден и можно купить,
 * несколько совпадений — клиент выбирает товар, не найден, цена по запросу — не добавляется,
 * но на такие позиции можно запросить цену одной заявкой, снят с производства, строку не
 * разобрали. Подписи — shop.bulk.status.
 */
enum BulkOrderStatus: string
{
    case Found = 'found';
    case Multiple = 'multiple';
    case NotFound = 'not_found';
    case PriceOnRequest = 'price_on_request';
    case Discontinued = 'discontinued';
    case Invalid = 'invalid';

    public function label(): string
    {
        return __('shop.bulk.status.'.$this->value);
    }
}
