<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «Часто берут вместе» на карточке товара (ТЗ §8.3, §5: related_products): менеджер подбирает
 * сопутствующие товары — фильтр к кофемашине, подставку к пароконвектомату — и задаёт порядок
 * перетаскиванием. На витрине показываются первые три из тех, что на витрине (CatalogQuery).
 */
class RelatedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'relatedProducts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.product.related.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description(__('admin.product.related.description'))
            ->columns([
                TextColumn::make('name')->label(__('admin.product.name'))->limit(70),
                TextColumn::make('sku')->label(__('admin.product.sku'))->fontFamily('mono')->placeholder('—'),
                TextColumn::make('availability')->label(__('admin.product.availability'))->badge(),
            ])
            ->reorderable('related_products.sort')
            ->defaultSort('related_products.sort')
            ->paginated(false)
            ->headerActions([
                AttachAction::make()
                    ->label(__('admin.product.related.attach'))
                    ->recordSelectSearchColumns(['name', 'sku', 'supplier_code'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->whereKeyNot($this->getOwnerRecord()->getKey())->where('is_visible', true))
                    ->recordTitle(fn (Product $record): string => trim($record->name.($record->sku ? ' · '.$record->sku : '')))
                    ->multiple(),
            ])
            ->recordActions([
                DetachAction::make()->label(__('admin.product.related.detach')),
            ]);
    }
}
