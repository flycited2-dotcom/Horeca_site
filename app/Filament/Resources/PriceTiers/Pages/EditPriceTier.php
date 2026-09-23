<?php

namespace App\Filament\Resources\PriceTiers\Pages;

use App\Filament\Resources\PriceTiers\PriceTierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPriceTier extends EditRecord
{
    protected static string $resource = PriceTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PriceTierResource::getUrl('index');
    }
}
