<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\SaveProductByManager;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Saving goes through SaveProductByManager, which locks the fields the manager changed
 * so that the next import keeps them (TZ §6.6).
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        return app(SaveProductByManager::class)->handle($record, $data);
    }
}
