<?php

namespace App\Filament\Resources\ProductCollections\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductCollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('products'))
            ->columns([
                TextColumn::make('name')->label(__('admin.collection.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.collection.slug'))->fontFamily('mono'),
                TextColumn::make('products_count')->label(__('admin.collection.products_count'))->alignEnd(),
                IconColumn::make('is_active')->label(__('admin.collection.is_active'))->boolean(),
                TextColumn::make('sort')->label(__('admin.collection.sort'))->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort');
    }
}
