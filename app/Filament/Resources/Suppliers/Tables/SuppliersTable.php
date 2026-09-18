<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.supplier.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price_kind')
                    ->label(__('admin.supplier.price_kind'))
                    ->badge(),

                TextColumn::make('retail_round_to')
                    ->label(__('admin.supplier.retail_round_to'))
                    ->numeric(),

                TextColumn::make('products_count')
                    ->label(__('admin.supplier.products_count'))
                    ->counts('products')
                    ->numeric(),

                IconColumn::make('is_active')
                    ->label(__('admin.supplier.is_active'))
                    ->boolean(),

                TextColumn::make('last_import_at')
                    ->label(__('admin.supplier.last_import_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
