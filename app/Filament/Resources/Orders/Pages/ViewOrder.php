<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Бланк заявки с действиями менеджера (ТЗ §12). Удалить и вернуть может только
 * администратор — это решает политика заказа.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * The blank shows every line and the whole history: loaded once, not per entry.
     */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['items', 'statusLogs.user:id,name']);
    }

    public function getTitle(): string
    {
        return __('admin.order.title', ['number' => $this->getRecord()->getAttribute('number')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::called(),
            OrderActions::changeStatus(),
            OrderActions::attachInvoice(),
            OrderActions::downloadInvoice(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
