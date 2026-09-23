<?php

namespace App\Actions\BulkOrder;

use App\Actions\Leads\CreateLead;
use App\Enums\LeadType;
use App\Models\Lead;
use App\Models\User;
use App\Services\BulkOrder\BulkOrderLine;
use App\Services\BulkOrder\BulkOrderMatcher;
use App\Services\BulkOrder\BulkOrderStatus;
use Illuminate\Validation\ValidationException;

/**
 * «Запросить цену на эти позиции» (ТЗ §11): все строки заказа списком с ценой по запросу —
 * одним лидом «Запрос цены» со списком артикулов, названий и количеств. Контакт — клиент
 * и телефон его кабинета, а без него — телефон компании. Менеджеры узнают о лиде как
 * обычно (§13).
 */
final class RequestBulkOrderPrices
{
    public function __construct(
        private readonly BulkOrderMatcher $matcher,
        private readonly CreateLead $createLead,
    ) {}

    /**
     * @param  list<BulkOrderLine>  $lines
     * @param  array{ip?: ?string, utm?: ?array<string, string>}  $meta
     */
    public function handle(array $lines, User $user, array $meta = []): Lead
    {
        $items = [];

        foreach ($this->matcher->match($lines, $user) as $match) {
            if ($match->status === BulkOrderStatus::PriceOnRequest && ($product = $match->product()) !== null) {
                $items[] = ['product' => $product, 'qty' => $match->line->qty];
            }
        }

        if ($items === []) {
            throw ValidationException::withMessages(['bulk' => __('shop.bulk.errors.nothing_to_request')]);
        }

        $message = [__('shop.bulk.lead_heading', ['count' => count($items)])];

        foreach ($items as $index => $item) {
            $message[] = __('shop.bulk.lead_line', [
                'n' => $index + 1,
                'sku' => $item['product']->sku ?? $item['product']->supplier_code,
                'name' => $item['product']->name,
                'qty' => $item['qty'],
                'unit' => $item['product']->unit,
            ]);
        }

        return $this->createLead->handle([
            'type' => LeadType::PriceRequest->value,
            'name' => $user->name,
            'phone' => (string) ($user->phone ?? $user->company?->phone),
            'email' => $user->email,
            'product_id' => count($items) === 1 ? $items[0]['product']->id : null,
            'message' => implode("\n", $message),
        ], $meta);
    }
}
