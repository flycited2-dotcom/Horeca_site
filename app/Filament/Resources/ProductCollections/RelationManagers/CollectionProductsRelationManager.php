<?php

namespace App\Filament\Resources\ProductCollections\RelationManagers;

use App\Models\Product;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Товары подборки: поиск по названию, артикулу и коду 1С, порядок перетаскиванием. На витрине
 * показываются только товары витрины (CatalogQuery::collectionProducts).
 */
class CollectionProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.collection.products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('admin.product.name'))->limit(70),
                TextColumn::make('sku')->label(__('admin.product.sku'))->fontFamily('mono')->placeholder('—'),
                TextColumn::make('availability')->label(__('admin.product.availability'))->badge(),
            ])
            ->reorderable('collection_product.sort')
            ->defaultSort('collection_product.sort')
            ->paginated(false)
            ->headerActions([
                AttachAction::make()
                    ->label(__('admin.product.related.attach'))
                    ->recordSelectSearchColumns(['name', 'sku', 'supplier_code'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->where('is_visible', true))
                    ->recordTitle(fn (Product $record): string => trim($record->name.($record->sku ? ' · '.$record->sku : '')))
                    ->multiple(),
            ])
            ->recordActions([
                DetachAction::make()->label(__('admin.product.related.detach')),
            ]);
    }
}
