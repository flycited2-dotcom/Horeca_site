<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('admin.warehouse.name'))
                    ->required()
                    ->maxLength(150),

                TextInput::make('city')
                    ->label(__('admin.warehouse.city'))
                    ->maxLength(150),

                TextInput::make('delivery_days_min')
                    ->label(__('admin.warehouse.delivery_days_min'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(365),

                TextInput::make('delivery_days_max')
                    ->label(__('admin.warehouse.delivery_days_max'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(365)
                    ->gte('delivery_days_min'),

                Toggle::make('is_visible')
                    ->label(__('admin.warehouse.is_visible')),

                TextInput::make('sort')
                    ->label(__('admin.warehouse.sort'))
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
