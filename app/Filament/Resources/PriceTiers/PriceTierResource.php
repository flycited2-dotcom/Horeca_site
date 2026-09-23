<?php

namespace App\Filament\Resources\PriceTiers;

use App\Filament\Resources\PriceTiers\Pages\CreatePriceTier;
use App\Filament\Resources\PriceTiers\Pages\EditPriceTier;
use App\Filament\Resources\PriceTiers\Pages\ListPriceTiers;
use App\Filament\Resources\PriceTiers\Schemas\PriceTierForm;
use App\Filament\Resources\PriceTiers\Tables\PriceTiersTable;
use App\Models\PriceTier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ценовые группы оптовиков (ТЗ §7, §12): скидка от розницы и минимальная сумма заявки.
 * Менять может только администратор (политика PriceTierPolicy).
 */
class PriceTierResource extends Resource
{
    protected static ?string $model = PriceTier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('admin.price_tier.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.price_tier.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.sales');
    }

    public static function form(Schema $schema): Schema
    {
        return PriceTierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceTiersTable::configure($table);
    }

    /**
     * @return Builder<PriceTier>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('companies');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceTiers::route('/'),
            'create' => CreatePriceTier::route('/create'),
            'edit' => EditPriceTier::route('/{record}/edit'),
        ];
    }
}
