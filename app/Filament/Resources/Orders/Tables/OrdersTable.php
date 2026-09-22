<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\DeliveryMethod;
use App\Enums\OrderType;
use App\Filament\Resources\Orders\OrderActions;
use App\Models\Order;
use App\Support\Typography;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Список заявок (ТЗ §12): номер, дата по Москве, клиент, телефон, тип, статус цветным
 * бейджем, сумма, число позиций, получение и менеджер.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('admin.order.number'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin.order.created_at'))
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label(__('admin.order.customer'))
                    ->description(fn (Order $record): ?string => $record->company_name)
                    ->searchable(),

                TextColumn::make('phone')
                    ->label(__('admin.order.phone'))
                    ->fontFamily('mono')
                    ->url(fn (Order $record): string => 'tel:'.preg_replace('/[^+\d]/', '', $record->phone))
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('admin.order.type'))
                    ->badge()
                    ->color(fn (OrderType $state): string => $state === OrderType::Wholesale ? 'info' : 'gray'),

                TextColumn::make('status')
                    ->label(__('admin.order.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('total')
                    ->label(__('admin.order.total'))
                    ->formatStateUsing(fn (Order $record): string => Typography::money($record->total))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label(__('admin.order.items_count'))
                    ->alignEnd(),

                TextColumn::make('delivery_method')
                    ->label(__('admin.order.delivery'))
                    ->description(fn (Order $record): ?string => $record->delivery_city)
                    ->toggleable(),

                TextColumn::make('manager.name')
                    ->label(__('admin.order.manager'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.order.type'))
                    ->options(OrderType::class),

                SelectFilter::make('delivery_method')
                    ->label(__('admin.order.delivery'))
                    ->options(DeliveryMethod::class),
            ])
            ->recordActions([
                ViewAction::make(),
                OrderActions::called(),
                OrderActions::changeStatus(),
            ])
            ->paginated([25, 50, 100]);
    }
}
