<?php

namespace App\Filament\Resources\ImportRuns;

use App\Filament\Resources\ImportRuns\Pages\ListImportRuns;
use App\Filament\Resources\ImportRuns\Pages\ViewImportRun;
use App\Filament\Resources\ImportRuns\Schemas\ImportRunInfolist;
use App\Filament\Resources\ImportRuns\Tables\ImportRunsTable;
use App\Models\ImportRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The history of import runs. Runs are written by the importer only: nothing here is
 * created or edited by hand, so the resource has no form.
 */
class ImportRunResource extends Resource
{
    protected static ?string $model = ImportRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Импорт';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('admin.import_run.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.import_run.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.import');
    }

    public static function infolist(Schema $schema): Schema
    {
        return ImportRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportRunsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['profile.supplier', 'user']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportRuns::route('/'),
            'view' => ViewImportRun::route('/{record}'),
        ];
    }
}
