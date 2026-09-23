<?php

namespace App\Filament\Resources\ProductCollections\Pages;

use App\Filament\Resources\ProductCollections\ProductCollectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCollection extends CreateRecord
{
    protected static string $resource = ProductCollectionResource::class;
}
