<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\Company;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Карточка компании (ТЗ §11, §12): что за организация, кто на связи — телефон ссылкой
 * tel:, ИНН копируется, — чем закончилась проверка и чьи кабинеты привязаны.
 */
class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('admin.company.sections.company'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('legal_name')->label(__('admin.company.legal_name')),
                        TextEntry::make('brand_name')->label(__('admin.company.brand_name'))->placeholder('—'),
                        TextEntry::make('inn')->label(__('admin.company.inn'))->fontFamily('mono')->copyable(),
                        TextEntry::make('kpp')->label(__('admin.company.kpp'))->fontFamily('mono')->placeholder('—'),
                        TextEntry::make('ogrn')->label(__('admin.company.ogrn'))->fontFamily('mono')->placeholder('—'),
                        TextEntry::make('segment')->label(__('admin.company.segment')),
                        TextEntry::make('legal_address')->label(__('admin.company.legal_address'))->placeholder('—'),
                    ]),

                Section::make(__('admin.company.sections.contact'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('contact_person')->label(__('admin.company.contact_person')),
                        TextEntry::make('phone')
                            ->label(__('admin.company.phone'))
                            ->fontFamily('mono')
                            ->url(fn (Company $record): string => 'tel:'.preg_replace('/[^+\d]/', '', $record->phone))
                            ->copyable(),
                        TextEntry::make('email')
                            ->label(__('admin.company.email'))
                            ->url(fn (Company $record): string => 'mailto:'.$record->email)
                            ->copyable(),
                        TextEntry::make('city')->label(__('admin.company.city'))->placeholder('—'),
                        TextEntry::make('delivery_address')->label(__('admin.company.delivery_address'))->placeholder('—'),
                    ]),

                Section::make(__('admin.company.sections.moderation'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')->label(__('admin.company.status'))->badge(),
                        TextEntry::make('priceTier.name')->label(__('admin.company.price_tier'))->placeholder('—'),
                        TextEntry::make('created_at')->label(__('admin.company.created_at'))->dateTime('d.m.Y H:i', 'Europe/Moscow'),
                        TextEntry::make('approved_at')->label(__('admin.company.approved'))->dateTime('d.m.Y H:i', 'Europe/Moscow')->placeholder('—'),
                        TextEntry::make('approver.name')->label(__('admin.company.approved_by'))->placeholder('—'),
                        TextEntry::make('manager_comment')->label(__('admin.company.manager_comment'))->placeholder('—'),
                    ]),

                Section::make(__('admin.company.sections.bank'))
                    ->columnSpan(1)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('bank_name')->label(__('admin.company.bank_name'))->placeholder('—'),
                        TextEntry::make('bik')->label(__('admin.company.bik'))->fontFamily('mono')->placeholder('—'),
                        TextEntry::make('account')->label(__('admin.company.account'))->fontFamily('mono')->placeholder('—'),
                        TextEntry::make('corr_account')->label(__('admin.company.corr_account'))->fontFamily('mono')->placeholder('—'),
                    ]),

                Section::make(__('admin.company.users'))
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('users')
                            ->hiddenLabel()
                            ->placeholder(__('admin.company.no_users'))
                            ->table([
                                TableColumn::make(__('admin.lead.name')),
                                TableColumn::make(__('admin.company.email')),
                                TableColumn::make(__('admin.company.phone')),
                            ])
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('email'),
                                TextEntry::make('phone')->fontFamily('mono')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
