<?php

namespace App\Filament\Resources\Warehouses\Tables;

use App\Models\Warehouse;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.warehouse.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label(__('admin.warehouse.supplier'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('city')
                    ->label(__('admin.warehouse.city'))
                    ->placeholder('—'),

                TextColumn::make('delivery_days')
                    ->label(__('admin.warehouse.delivery_days'))
                    ->state(fn (Warehouse $record): string => match (true) {
                        $record->delivery_days_min !== null && $record->delivery_days_max !== null => $record->delivery_days_min.'–'.$record->delivery_days_max,
                        $record->delivery_days_max !== null => (string) $record->delivery_days_max,
                        $record->delivery_days_min !== null => (string) $record->delivery_days_min,
                        default => '—',
                    }),

                TextColumn::make('stocks_count')
                    ->label(__('admin.warehouse.stocks_count'))
                    ->counts('stocks')
                    ->numeric(),

                IconColumn::make('is_visible')
                    ->label(__('admin.warehouse.is_visible'))
                    ->boolean(),

                TextColumn::make('sort')
                    ->label(__('admin.warehouse.sort'))
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label(__('admin.warehouse.supplier'))
                    ->relationship('supplier', 'name'),

                TernaryFilter::make('is_visible')
                    ->label(__('admin.warehouse.is_visible')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
