<?php

namespace App\Filament\Resources\Companies;

use App\Enums\CompanyStatus;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\Companies\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Companies\Schemas\CompanyForm;
use App\Filament\Resources\Companies\Schemas\CompanyInfolist;
use App\Filament\Resources\Companies\Tables\CompaniesTable;
use App\Models\Company;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Клиенты-юрлица и модерация заявок на опт (ТЗ §11, §12): проверка реквизитов, ценовая
 * группа, одобрение, отказ, блокировка, заявки компании. Компании создаёт только витрина.
 */
class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'legal_name';

    public static function getModelLabel(): string
    {
        return __('admin.company.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.company.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.sales');
    }

    /**
     * Applications waiting for a manager — the number to look at first.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Company::query()->where('status', CompanyStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['legal_name', 'brand_name', 'inn', 'email', 'phone'];
    }

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompanyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    /**
     * @return Builder<Company>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['priceTier:id,name']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'view' => ViewCompany::route('/{record}'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
