<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Actions\Leads\ChangeLeadStatus;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Lead;
use App\Models\User;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Лиды по типам и статусам (ТЗ §12): статус меняется прямо в строке, телефон — ссылкой
 * tel:, товар — ссылкой на его страницу на витрине.
 */
class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.lead.created_at'))
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('admin.lead.type'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label(__('admin.lead.name'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label(__('admin.lead.phone'))
                    ->fontFamily('mono')
                    ->url(fn (Lead $record): string => 'tel:'.preg_replace('/[^+\d]/', '', $record->phone))
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label(__('admin.lead.product'))
                    ->url(fn (Lead $record): ?string => $record->product ? route('product', $record->product) : null, shouldOpenInNewTab: true)
                    ->limit(50)
                    ->placeholder('—'),

                TextColumn::make('message')
                    ->label(__('admin.lead.message'))
                    ->limit(80)
                    ->tooltip(fn (Lead $record): ?string => $record->message)
                    ->placeholder('—')
                    ->wrap(),

                SelectColumn::make('status')
                    ->label(__('admin.lead.status'))
                    ->options(LeadStatus::class)
                    ->selectablePlaceholder(false)
                    ->updateStateUsing(function (Lead $record, mixed $state, ChangeLeadStatus $change): string {
                        $status = $state instanceof LeadStatus ? $state : LeadStatus::from((string) $state);

                        /** @var User $manager */
                        $manager = auth()->user();
                        $change->handle($record, $status, $manager);

                        return $status->value;
                    }),

                TextColumn::make('manager.name')
                    ->label(__('admin.lead.manager'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.lead.type'))
                    ->options(LeadType::class),

                SelectFilter::make('status')
                    ->label(__('admin.lead.status'))
                    ->options(LeadStatus::class),
            ])
            ->paginated([25, 50, 100]);
    }
}
