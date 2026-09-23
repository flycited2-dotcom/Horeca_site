<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyActions;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Карточка компании (ТЗ §11, §12): реквизиты, контакты, кабинеты, проверка и действия
 * модерации; ниже — заявки компании.
 */
class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['users:id,company_id,name,email,phone,last_login_at', 'approver:id,name']);
    }

    protected function getHeaderActions(): array
    {
        return [
            CompanyActions::approve(),
            CompanyActions::reject(),
            CompanyActions::block(),
            CompanyActions::toPending(),
            EditAction::make(),
        ];
    }
}
