<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanySegment;
use App\Rules\Inn;
use App\Rules\RussianPhone;
use App\Support\Phone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Реквизиты компании для правки менеджером (ТЗ §5, §12): ИНН с проверкой контрольных цифр,
 * телефон в виде «+7 978 123-45-67», коды банка и счета — только цифрами нужной длины.
 */
class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('admin.company.sections.company'))
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('legal_name')->label(__('admin.company.legal_name'))->required()->maxLength(255),
                        TextInput::make('brand_name')->label(__('admin.company.brand_name'))->maxLength(255),
                        TextInput::make('inn')->label(__('admin.company.inn'))->required()->rule(new Inn)->extraInputAttributes(['class' => 'font-mono']),
                        TextInput::make('kpp')->label(__('admin.company.kpp'))->regex('/^\d{9}$/')->extraInputAttributes(['class' => 'font-mono']),
                        TextInput::make('ogrn')->label(__('admin.company.ogrn'))->regex('/^(\d{13}|\d{15})$/')->extraInputAttributes(['class' => 'font-mono']),
                        Select::make('segment')->label(__('admin.company.segment'))->options(CompanySegment::class)->native(false)->required(),
                        TextInput::make('legal_address')->label(__('admin.company.legal_address'))->maxLength(500),
                    ]),

                Section::make(__('admin.company.sections.contact'))
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('contact_person')->label(__('admin.company.contact_person'))->required()->maxLength(150),
                        TextInput::make('phone')
                            ->label(__('admin.company.phone'))
                            ->required()
                            ->tel()
                            ->rule(new RussianPhone)
                            ->dehydrateStateUsing(fn (?string $state): ?string => Phone::normalize($state) ?? $state),
                        TextInput::make('email')->label(__('admin.company.email'))->required()->email()->maxLength(150),
                        TextInput::make('city')->label(__('admin.company.city'))->maxLength(150),
                        TextInput::make('delivery_address')->label(__('admin.company.delivery_address'))->maxLength(500),
                    ]),

                Section::make(__('admin.company.sections.bank'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('bank_name')->label(__('admin.company.bank_name'))->maxLength(255),
                        TextInput::make('bik')->label(__('admin.company.bik'))->regex('/^\d{9}$/')->extraInputAttributes(['class' => 'font-mono']),
                        TextInput::make('account')->label(__('admin.company.account'))->regex('/^\d{20}$/')->extraInputAttributes(['class' => 'font-mono']),
                        TextInput::make('corr_account')->label(__('admin.company.corr_account'))->regex('/^\d{20}$/')->extraInputAttributes(['class' => 'font-mono']),
                    ]),
            ]);
    }
}
