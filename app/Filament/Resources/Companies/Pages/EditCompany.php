<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Правка реквизитов менеджером (ТЗ §12). Статус и ценовая группа здесь не меняются — только
 * действиями модерации; поэтому правка менеджера не возвращает компанию на проверку.
 */
class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getRedirectUrl(): string
    {
        return CompanyResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
