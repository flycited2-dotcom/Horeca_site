<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Schemas\WarehouseForm;
use App\Filament\Resources\Warehouses\Tables\WarehousesTable;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Supplier warehouses. They are created by the import; the manager decides which ones the
 * customer sees and how long delivery to Simferopol takes (TZ §6.5, §12).
 */
class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Импорт';

    protected static ?int $navigationSort = 50;

    public static function getModelLabel(): string
    {
        return __('admin.warehouse.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.warehouse.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.import');
    }

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('supplier');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
