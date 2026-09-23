<?php

namespace App\Filament\Resources\Attributes;

use App\Enums\AttributeType;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Models\Attribute;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Характеристики (ТЗ §12, §5.2): по каким строить фильтры листинга (`is_filterable`), какие
 * показывать в блоке покупки и в «Критично для монтажа» (`is_main`), единица и порядок.
 * С API характеристики приходят сами и создаются нефильтруемыми (§18, спринт 7). Тип
 * характеристики, у которой уже есть значения у товаров, не меняется — значения лежат
 * в колонке своего типа.
 */
class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('admin.attribute.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.attribute.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.catalog');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label(__('admin.attribute.name'))->required()->maxLength(150),
                TextInput::make('slug')
                    ->label(__('admin.attribute.slug'))
                    ->helperText(__('admin.attribute.slug_hint'))
                    ->required()
                    ->maxLength(160)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['class' => 'font-mono']),
                Select::make('type')
                    ->label(__('admin.attribute.type'))
                    ->options(AttributeType::class)
                    ->required()
                    ->disabled(fn (?Attribute $record): bool => $record !== null && $record->products()->exists())
                    ->helperText(fn (?Attribute $record): ?string => $record !== null && $record->products()->exists() ? __('admin.attribute.type_locked') : null),
                TextInput::make('unit')->label(__('admin.attribute.unit'))->helperText(__('admin.attribute.unit_hint'))->maxLength(16),
                Toggle::make('is_filterable')->label(__('admin.attribute.is_filterable'))->helperText(__('admin.attribute.is_filterable_hint')),
                Toggle::make('is_main')->label(__('admin.attribute.is_main'))->helperText(__('admin.attribute.is_main_hint')),
                TextInput::make('sort')->label(__('admin.attribute.sort'))->integer()->minValue(0)->maxValue(32767)->default(0)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('products'))
            ->columns([
                TextColumn::make('name')->label(__('admin.attribute.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.attribute.slug'))->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')->label(__('admin.attribute.type'))->badge()->color('gray'),
                TextColumn::make('unit')->label(__('admin.attribute.unit'))->placeholder('—'),
                IconColumn::make('is_filterable')->label(__('admin.attribute.is_filterable_short'))->boolean(),
                IconColumn::make('is_main')->label(__('admin.attribute.is_main_short'))->boolean(),
                TextColumn::make('products_count')->label(__('admin.attribute.products_count'))->alignEnd()->sortable(),
                TextColumn::make('sort')->label(__('admin.attribute.sort'))->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_filterable')->label(__('admin.attribute.is_filterable_short')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
