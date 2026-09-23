<?php

namespace App\Filament\Resources\ProductCollections;

use App\Filament\Resources\ProductCollections\Pages\CreateProductCollection;
use App\Filament\Resources\ProductCollections\Pages\EditProductCollection;
use App\Filament\Resources\ProductCollections\Pages\ListProductCollections;
use App\Filament\Resources\ProductCollections\RelationManagers\CollectionProductsRelationManager;
use App\Filament\Resources\ProductCollections\Schemas\ProductCollectionForm;
use App\Filament\Resources\ProductCollections\Tables\ProductCollectionsTable;
use App\Models\ProductCollection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Подборки «Соберём кухню под задачу» (ТЗ §8.1, §12): на главной — три первые включённые,
 * в которых есть товары на витрине; у каждой своя страница /collections/{slug}.
 */
class ProductCollectionResource extends Resource
{
    protected static ?string $model = ProductCollection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('admin.collection.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.collection.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.content');
    }

    public static function form(Schema $schema): Schema
    {
        return ProductCollectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCollectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CollectionProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCollections::route('/'),
            'create' => CreateProductCollection::route('/create'),
            'edit' => EditProductCollection::route('/{record}/edit'),
        ];
    }
}
