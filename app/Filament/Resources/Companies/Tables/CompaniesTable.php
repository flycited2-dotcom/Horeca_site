<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Filament\Resources\Companies\CompanyActions;
use App\Models\Company;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Компании (ТЗ §12): название с вывеской, ИНН, тип заведения, город, статус, ценовая группа,
 * контакт и когда пришла заявка. Поиск — по названию, ИНН, почте и телефону.
 */
class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('legal_name')
                    ->label(__('admin.company.legal_name'))
                    ->description(fn (Company $record): ?string => $record->brand_name)
                    ->searchable(['legal_name', 'brand_name'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('inn')
                    ->label(__('admin.company.inn'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('segment')
                    ->label(__('admin.company.segment'))
                    ->toggleable(),

                TextColumn::make('city')
                    ->label(__('admin.company.city'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.company.status'))
                    ->badge(),

                TextColumn::make('priceTier.name')
                    ->label(__('admin.company.price_tier'))
                    ->placeholder('—'),

                TextColumn::make('contact_person')
                    ->label(__('admin.company.contact_person'))
                    ->description(fn (Company $record): string => $record->phone)
                    ->searchable(['contact_person', 'phone', 'email']),

                TextColumn::make('created_at')
                    ->label(__('admin.company.created_at'))
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('price_tier_id')
                    ->label(__('admin.company.price_tier'))
                    ->relationship('priceTier', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    CompanyActions::approve(),
                    CompanyActions::reject(),
                    CompanyActions::block(),
                    CompanyActions::toPending(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
