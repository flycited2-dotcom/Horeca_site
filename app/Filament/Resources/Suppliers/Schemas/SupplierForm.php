<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use App\Enums\PriceKind;
use App\Support\Percent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * The supplier and how its price becomes ours (TZ §7).
 *
 * suppliers.config holds API tokens and is never shown in a form: it is encrypted
 * and edited only through the environment (TZ §15).
 */
class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.supplier.name'))
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state, language: 'ru'))),

                TextInput::make('slug')
                    ->label(__('admin.supplier.slug'))
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),

                Select::make('price_kind')
                    ->label(__('admin.supplier.price_kind'))
                    ->options(PriceKind::class)
                    ->default(PriceKind::Rrp->value)
                    ->required()
                    ->live(),

                TextInput::make('markup_percent')
                    ->label(__('admin.supplier.markup_percent'))
                    ->helperText(__('admin.supplier.markup_hint'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999.99)
                    ->step(0.01)
                    ->required()
                    ->default('0.00')
                    ->visible(fn (callable $get): bool => $get('price_kind') === PriceKind::Dealer->value)
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof Percent ? $state->toDecimal() : (string) ($state ?? '0.00'))
                    ->dehydrateStateUsing(fn (mixed $state): Percent => Percent::fromDecimal(number_format((float) $state, 2, '.', ''))),

                TextInput::make('retail_round_to')
                    ->label(__('admin.supplier.retail_round_to'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->required()
                    ->default(1),

                Toggle::make('is_active')
                    ->label(__('admin.supplier.is_active'))
                    ->default(true),
            ]);
    }
}
