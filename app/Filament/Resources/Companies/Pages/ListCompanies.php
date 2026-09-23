<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Enums\CompanyStatus;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Компании вкладками по статусу (ТЗ §12): первой — «На проверке» с числом заявок.
 */
class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Company::query()
            ->toBase()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'status');

        $tabs = [];

        foreach (CompanyStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge($counts[$status->value] ?? null)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        $tabs['all'] = Tab::make(__('admin.company.tabs.all'));

        return $tabs;
    }
}
