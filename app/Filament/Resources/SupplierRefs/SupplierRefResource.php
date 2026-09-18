<?php

namespace App\Filament\Resources\SupplierRefs;

use App\Filament\Resources\SupplierRefs\Pages\ListSupplierRefs;
use App\Filament\Resources\SupplierRefs\Tables\SupplierRefsTable;
use App\Models\SupplierRef;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * What the supplier calls things versus what we call them (TZ §12).
 *
 * Rows appear by themselves during an import. The manager does two things here: links a
 * supplier entity to our record and switches off the ones the shop does not need.
 */
class SupplierRefResource extends Resource
{
    protected static ?string $model = SupplierRef::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Импорт';

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('admin.supplier_ref.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.supplier_ref.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.import');
    }

    public static function table(Table $table): Table
    {
        return SupplierRefsTable::configure($table);
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
            'index' => ListSupplierRefs::route('/'),
        ];
    }
}
