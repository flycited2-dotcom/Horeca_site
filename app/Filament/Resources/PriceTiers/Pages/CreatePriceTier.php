<?php

namespace App\Filament\Resources\PriceTiers\Pages;

use App\Filament\Resources\PriceTiers\PriceTierResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePriceTier extends CreateRecord
{
    protected static string $resource = PriceTierResource::class;

    protected function getRedirectUrl(): string
    {
        return PriceTierResource::getUrl('index');
    }
}
