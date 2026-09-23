<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Typography;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Заявки компании (ТЗ §12) — только чтение: заявку открывают и ведут в разделе «Заявки».
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.company.orders');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label(__('admin.order.number'))->fontFamily('mono'),
                TextColumn::make('created_at')->label(__('admin.order.created_at'))->dateTime('d.m.Y H:i', 'Europe/Moscow')->sortable(),
                TextColumn::make('status')->label(__('admin.order.status'))->badge(),
                TextColumn::make('total')
                    ->label(__('admin.order.total'))
                    ->formatStateUsing(fn (Order $record): string => Typography::money($record->total))
                    ->alignEnd(),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
