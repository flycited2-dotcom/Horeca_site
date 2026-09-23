<?php

namespace App\Filament\Resources\PriceTiers\Schemas;

use App\Support\Money;
use App\Support\Percent;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Ценовая группа (ТЗ §5, §7): скидка — в процентах с двумя знаками («12.5»), хранится в
 * базисных пунктах; минимальная сумма — в рублях с копейками, хранится в копейках.
 * Дробные числа в расчёт не попадают.
 */
class PriceTierForm
{
    private const string PERCENT_PATTERN = '/^\d{1,2}(\.\d{1,2})?$/';

    private const string MONEY_PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('admin.price_tier.name'))
                    ->required()
                    ->maxLength(100),

                TextInput::make('slug')
                    ->label(__('admin.price_tier.slug'))
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['class' => 'font-mono']),

                TextInput::make('discount_percent')
                    ->label(__('admin.price_tier.discount_percent'))
                    ->helperText(__('admin.price_tier.discount_hint'))
                    ->required()
                    ->inputMode('decimal')
                    ->regex(self::PERCENT_PATTERN)
                    ->default('0.00')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof Percent ? $state->toDecimal() : (string) ($state ?? '0.00'))
                    ->dehydrateStateUsing(fn (mixed $state): Percent => Percent::fromDecimal((string) $state)),

                TextInput::make('min_order_amount')
                    ->label(__('admin.price_tier.min_order_amount'))
                    ->required()
                    ->inputMode('decimal')
                    ->regex(self::MONEY_PATTERN)
                    ->default('0.00')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof Money ? $state->toDecimal() : (string) ($state ?? '0.00'))
                    ->dehydrateStateUsing(fn (mixed $state): Money => Money::fromDecimal((string) $state)),

                Toggle::make('is_default')
                    ->label(__('admin.price_tier.is_default'))
                    ->helperText(__('admin.price_tier.default_hint')),

                TextInput::make('sort')
                    ->label(__('admin.price_tier.sort'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(32767)
                    ->default(0)
                    ->required(),
            ]);
    }
}
