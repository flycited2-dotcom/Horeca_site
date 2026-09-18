<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Actions\Catalog\RecalculateCategoryCounts;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Moving a category under another parent or deleting it changes the product counts of
 * every ancestor, so they are recalculated at once instead of waiting for the next import.
 */
class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn (RecalculateCategoryCounts $counts) => $counts->handle()),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->getRecord()->wasChanged('parent_id')) {
            app(RecalculateCategoryCounts::class)->handle();
        }
    }
}
