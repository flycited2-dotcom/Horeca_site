<?php

namespace App\Filament\Resources\ProductCollections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductCollectionForm
{
    /**
     * Pictograms of the design system (x-ui.equipment-icon) a collection may show.
     */
    public const array ICONS = ['thermal', 'refrigeration', 'neutral', 'dishwashing', 'electromechanical', 'ventilation'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('admin.collection.name'))
                    ->required()
                    ->maxLength(150),

                TextInput::make('slug')
                    ->label(__('admin.collection.slug'))
                    ->helperText(__('admin.collection.slug_hint'))
                    ->required()
                    ->maxLength(160)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['class' => 'font-mono']),

                Textarea::make('description')
                    ->label(__('admin.collection.description'))
                    ->helperText(__('admin.collection.description_hint'))
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),

                Select::make('icon')
                    ->label(__('admin.collection.icon'))
                    ->options(collect(self::ICONS)->mapWithKeys(fn (string $icon): array => [$icon => __("admin.collection.icons.{$icon}")])->all()),

                TextInput::make('sort')
                    ->label(__('admin.collection.sort'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(32767)
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->label(__('admin.collection.is_active'))
                    ->helperText(__('admin.collection.is_active_hint')),
            ]);
    }
}
