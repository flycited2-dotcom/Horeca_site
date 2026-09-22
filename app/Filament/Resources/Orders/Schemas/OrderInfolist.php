<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Typography;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Бланк заявки (ТЗ §12): кто, что, куда и как платит — читается сверху вниз; телефон —
 * ссылкой tel:, состав копируется в буфер одной кнопкой; внизу журнал статусов.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('admin.order.order'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('number')->label(__('admin.order.number'))->fontFamily('mono')->copyable(),
                        TextEntry::make('created_at')->label(__('admin.order.created_at'))->dateTime('d.m.Y H:i', 'Europe/Moscow'),
                        TextEntry::make('status')->label(__('admin.order.status'))->badge(),
                        TextEntry::make('type')->label(__('admin.order.type'))->badge()->color(fn (OrderType $state): string => $state === OrderType::Wholesale ? 'info' : 'gray'),
                        TextEntry::make('manager.name')->label(__('admin.order.manager'))->placeholder('—'),
                        TextEntry::make('paid_at')->label(__('admin.order.paid_at'))->dateTime('d.m.Y H:i', 'Europe/Moscow')->placeholder('—'),
                    ]),

                Section::make(__('admin.order.customer'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('customer_name')->label(__('admin.order.name')),
                        TextEntry::make('phone')
                            ->label(__('admin.order.phone'))
                            ->fontFamily('mono')
                            ->url(fn (Order $record): string => 'tel:'.preg_replace('/[^+\d]/', '', $record->phone))
                            ->copyable(),
                        TextEntry::make('email')
                            ->label(__('admin.order.email'))
                            ->url(fn (Order $record): ?string => $record->email ? 'mailto:'.$record->email : null)
                            ->placeholder('—'),
                        TextEntry::make('company_name')->label(__('admin.order.company'))->placeholder('—'),
                        TextEntry::make('inn')->label(__('admin.order.inn'))->fontFamily('mono')->copyable()->placeholder('—'),
                    ]),

                Section::make(__('admin.order.delivery_payment'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('delivery_method')->label(__('admin.order.delivery')),
                        TextEntry::make('delivery_city')->label(__('admin.order.city'))->placeholder('—'),
                        TextEntry::make('tk_name')->label(__('admin.order.carrier'))->placeholder('—'),
                        TextEntry::make('delivery_address')->label(__('admin.order.address'))->placeholder('—'),
                        TextEntry::make('payment_method')->label(__('admin.order.payment')),
                        TextEntry::make('invoice_path')
                            ->label(__('admin.order.invoice'))
                            ->formatStateUsing(fn (?string $state): string => $state ? __('admin.order.invoice_attached_short') : '—')
                            ->placeholder('—'),
                    ]),

                Section::make(__('admin.order.items'))
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('admin.order.sku')),
                                TableColumn::make(__('admin.order.item_name')),
                                TableColumn::make(__('admin.order.availability')),
                                TableColumn::make(__('admin.order.qty')),
                                TableColumn::make(__('admin.order.price')),
                                TableColumn::make(__('admin.order.sum')),
                            ])
                            ->schema([
                                TextEntry::make('sku')->fontFamily('mono')->placeholder('—'),
                                TextEntry::make('name'),
                                TextEntry::make('availability')->badge()->color('gray'),
                                TextEntry::make('qty')->formatStateUsing(fn (OrderItem $record): string => $record->qty.' '.$record->unit),
                                TextEntry::make('price')->formatStateUsing(fn (OrderItem $record): string => Typography::money($record->price)),
                                TextEntry::make('sum')->formatStateUsing(fn (OrderItem $record): string => Typography::money($record->sum)),
                            ]),

                        TextEntry::make('composition')
                            ->label(__('admin.order.composition'))
                            ->state(fn (Order $record): string => self::composition($record))
                            ->copyable()
                            ->copyMessage(__('admin.order.composition_copied'))
                            ->extraAttributes(['class' => 'whitespace-pre-line']),

                        TextEntry::make('total')
                            ->label(__('admin.order.total'))
                            ->formatStateUsing(fn (Order $record): string => Typography::money($record->total))
                            ->helperText(fn (Order $record): ?string => $record->discount->isZero()
                                ? null
                                : __('admin.order.discount_note', ['subtotal' => Typography::money($record->subtotal), 'discount' => Typography::money($record->discount)]))
                            ->weight('bold'),
                    ]),

                Section::make(__('admin.order.comment_customer'))
                    ->columnSpanFull()
                    ->visible(fn (Order $record): bool => filled($record->comment))
                    ->schema([
                        TextEntry::make('comment')->hiddenLabel()->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),

                Section::make(__('admin.order.history'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('statusLogs')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('admin.order.when')),
                                TableColumn::make(__('admin.order.status')),
                                TableColumn::make(__('admin.order.who')),
                                TableColumn::make(__('admin.order.comment')),
                            ])
                            ->schema([
                                TextEntry::make('created_at')->dateTime('d.m.Y H:i', 'Europe/Moscow'),
                                TextEntry::make('to_status')->badge(),
                                TextEntry::make('user.name')->placeholder(__('admin.order.by_customer')),
                                TextEntry::make('comment')->placeholder('—'),
                            ]),
                    ]),

                Section::make(__('admin.order.source'))
                    ->columnSpanFull()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('utm')
                            ->label(__('admin.order.utm'))
                            ->state(fn (Order $record): string => collect($record->utm ?? [])->map(fn (string $value, string $key): string => $key.'='.$value)->implode(', ') ?: '—'),
                        TextEntry::make('ip')->label('IP')->fontFamily('mono')->placeholder('—'),
                        TextEntry::make('user_agent')->label(__('admin.order.user_agent'))->placeholder('—'),
                    ]),
            ]);
    }

    /**
     * «11000019106 · Пароконвектомат … — 2 шт × 383 995 ₽ = 767 990 ₽», line by line: what the
     * manager pastes into the supplier's order or a messenger.
     */
    public static function composition(Order $order): string
    {
        return $order->items
            ->map(fn (OrderItem $item): string => sprintf(
                '%s%s — %d %s × %s = %s',
                $item->sku ? $item->sku.' · ' : '',
                $item->name,
                $item->qty,
                $item->unit,
                Typography::money($item->price),
                Typography::money($item->sum),
            ))
            ->push(__('admin.order.composition_total', ['total' => Typography::money($order->total)]))
            ->implode("\n");
    }
}
