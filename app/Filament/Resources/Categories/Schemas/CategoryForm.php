<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Services\Catalog\CategoryTree;
use App\Support\Slugger;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()->columns(2)->schema([
                    TextInput::make('name')
                        ->label(__('admin.category.name'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                            if ($operation === 'create') {
                                $set('slug', Slugger::make((string) $state, Slugger::CATEGORY_LIMIT));
                            }
                        }),

                    TextInput::make('slug')
                        ->label(__('admin.category.slug'))
                        ->helperText(__('admin.category.slug_hint'))
                        ->required()
                        ->maxLength(Slugger::CATEGORY_LIMIT)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    // A category cannot be moved under itself or under its own child:
                    // the tree would loop.
                    Select::make('parent_id')
                        ->label(__('admin.category.parent'))
                        ->placeholder(__('admin.category.parent_none'))
                        ->options(fn (?Category $record, CategoryTree $tree): array => $tree->parentOptions($record?->id))
                        ->searchable(),

                    TextInput::make('sort')
                        ->label(__('admin.category.sort'))
                        ->integer()
                        ->default(0)
                        ->required(),

                    TextInput::make('icon')
                        ->label(__('admin.category.icon'))
                        ->helperText(__('admin.category.icon_hint'))
                        ->maxLength(64)
                        ->alphaDash(),

                    Grid::make(2)->schema([
                        Toggle::make('is_active')->label(__('admin.category.is_active')),
                        Toggle::make('show_on_home')->label(__('admin.category.show_on_home')),
                    ]),

                    Textarea::make('description')
                        ->label(__('admin.category.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

                Section::make(__('admin.category.seo'))->collapsible()->schema([
                    TextInput::make('meta_title')->label(__('admin.category.meta_title'))->maxLength(255),
                    Textarea::make('meta_description')->label(__('admin.category.meta_description'))->maxLength(500)->rows(2),
                    TextInput::make('h1')->label(__('admin.category.h1'))->maxLength(255),
                    Textarea::make('seo_text')->label(__('admin.category.seo_text'))->rows(6),
                ]),
            ]);
    }
}
