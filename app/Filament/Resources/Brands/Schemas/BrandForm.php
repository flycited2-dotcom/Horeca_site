<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Support\Slugger;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('admin.brand.name'))
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                        if ($operation === 'create') {
                            $set('slug', Slugger::make((string) $state, Slugger::CATEGORY_LIMIT));
                        }
                    }),

                TextInput::make('slug')
                    ->label(__('admin.brand.slug'))
                    ->helperText(__('admin.brand.slug_hint'))
                    ->required()
                    ->maxLength(Slugger::CATEGORY_LIMIT)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),

                TextInput::make('country')
                    ->label(__('admin.brand.country'))
                    ->maxLength(64),

                Toggle::make('is_active')
                    ->label(__('admin.brand.is_active'))
                    ->default(true),

                Textarea::make('description')
                    ->label(__('admin.brand.description'))
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
