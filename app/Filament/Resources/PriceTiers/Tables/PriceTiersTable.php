<?php

namespace App\Filament\Resources\PriceTiers\Tables;

use App\Models\PriceTier;
use App\Support\Typography;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Ценовые группы (ТЗ §7): скидка, минимальная сумма, группа по умолчанию и сколько компаний
 * в группе. Порядок — как в выпадающем списке при одобрении компании.
 */
class PriceTiersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.price_tier.name'))->searchable(),
                TextColumn::make('slug')->label(__('admin.price_tier.slug'))->fontFamily('mono'),
                TextColumn::make('discount_percent')
                    ->label(__('admin.price_tier.discount_percent'))
                    ->formatStateUsing(fn (PriceTier $record): string => str_replace('.', ',', $record->discount_percent->toDecimal()))
                    ->alignEnd(),
                TextColumn::make('min_order_amount')
                    ->label(__('admin.price_tier.min_order_amount'))
                    ->formatStateUsing(fn (PriceTier $record): string => Typography::money($record->min_order_amount))
                    ->alignEnd(),
                IconColumn::make('is_default')->label(__('admin.price_tier.is_default'))->boolean(),
                TextColumn::make('companies_count')->label(__('admin.price_tier.companies_count'))->alignEnd(),
                TextColumn::make('sort')->label(__('admin.price_tier.sort'))->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort');
    }
}
