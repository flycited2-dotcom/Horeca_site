<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\WarehouseStockStatus;
use App\Models\ProductStock;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Warehouse stocks of the product, read only: they belong to the stock import (TZ §6.5, §12).
 */
class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.product.stocks.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('warehouse'))
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label(__('admin.product.stocks.warehouse')),

                TextColumn::make('status')
                    ->label(__('admin.product.stocks.status'))
                    ->badge()
                    ->color(fn (WarehouseStockStatus $state): string => match ($state) {
                        WarehouseStockStatus::InStock, WarehouseStockStatus::Low => 'success',
                        WarehouseStockStatus::Out => 'gray',
                    }),

                TextColumn::make('raw_value')
                    ->label(__('admin.product.stocks.raw_value'))
                    ->placeholder('—'),

                TextColumn::make('quantity')
                    ->label(__('admin.product.stocks.quantity'))
                    ->placeholder('—'),

                TextColumn::make('unit')
                    ->label(__('admin.product.stocks.unit'))
                    ->placeholder('—'),

                IconColumn::make('warehouse.is_visible')
                    ->label(__('admin.warehouse.is_visible'))
                    ->boolean()
                    ->tooltip(fn (ProductStock $record): ?string => $record->warehouse?->is_visible ? null : __('admin.product.stocks.hidden')),

                TextColumn::make('synced_at')
                    ->label(__('admin.product.stocks.synced_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('status')
            ->paginated(false);
    }
}
