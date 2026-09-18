<?php

namespace App\Filament\Resources\SupplierRefs\Pages;

use App\Enums\SupplierRefEntity;
use App\Filament\Resources\SupplierRefs\SupplierRefResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSupplierRefs extends ListRecords
{
    protected static string $resource = SupplierRefResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('admin.supplier_ref.tabs.all')),
        ];

        foreach (SupplierRefEntity::cases() as $entity) {
            $tabs[$entity->value] = Tab::make(__("admin.supplier_ref.tabs.{$entity->value}"))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('entity', $entity))
                ->badge(fn (): int => SupplierRefResource::getEloquentQuery()->where('entity', $entity)->count());
        }

        return $tabs;
    }
}
