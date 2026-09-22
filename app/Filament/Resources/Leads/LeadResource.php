<?php

namespace App\Filament\Resources\Leads;

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Лиды (ТЗ §12): «Купить в 1 клик», запросы цены и срока, подбор аналога, «не нашли»,
 * «перезвоните». Создаёт их только витрина; менеджер меняет статус прямо в таблице.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('admin.lead.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.lead.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.sales');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Lead::query()->where('status', LeadStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    /**
     * @return Builder<Lead>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product:id,name,slug', 'manager:id,name']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
        ];
    }
}
