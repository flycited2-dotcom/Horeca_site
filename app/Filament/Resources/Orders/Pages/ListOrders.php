<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Заявки с быстрым фильтром по статусу вкладками (ТЗ §12): «Новые» с числом, «В работе»
 * и остальные. Удалённые администратором — отдельной вкладкой.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Order::query()
            ->toBase()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'status');

        $tabs = [
            'active' => Tab::make(__('admin.order.tabs.active'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Canceled])),
        ];

        foreach (OrderStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge($counts[$status->value] ?? null)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()->where('status', $status));
        }

        $tabs['all'] = Tab::make(__('admin.order.tabs.all'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed());

        $tabs['trashed'] = Tab::make(__('admin.order.tabs.trashed'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed());

        return $tabs;
    }
}
