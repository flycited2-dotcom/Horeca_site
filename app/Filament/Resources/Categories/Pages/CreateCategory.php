<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Services\Catalog\CatalogCache;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * The picture is attached after the record is saved: the cache is reset again.
     */
    protected function afterCreate(): void
    {
        app(CatalogCache::class)->bump();
    }
}
