<?php

namespace App\Filament\Widgets;

use App\Enums\Availability;
use App\Enums\SupplierRefEntity;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierRef;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What the catalog still needs from the manager after an import (TZ §12): supplier
 * categories and brands without a match, switched-off categories that already have
 * products, and products that are shown as "Цена по запросу".
 */
class UnmappedSupplierRefs extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        return [
            Stat::make(__('admin.dashboard.products'), $this->number(
                Product::query()->where('is_visible', true)->where('availability', '!=', Availability::Discontinued)->count(),
            )),

            Stat::make(__('admin.dashboard.price_on_request'), $this->number(
                Product::query()->where('is_visible', true)->whereNull('retail_price')->count(),
            )),

            Stat::make(__('admin.dashboard.inactive_categories'), $this->number(
                Category::query()->where('is_active', false)->where('products_count', '>', 0)->count(),
            )),

            Stat::make(__('admin.dashboard.unmapped_categories'), $this->number($this->unmapped(SupplierRefEntity::Category))),

            Stat::make(__('admin.dashboard.unmapped_brands'), $this->number($this->unmapped(SupplierRefEntity::Brand))),
        ];
    }

    private function unmapped(SupplierRefEntity $entity): int
    {
        return SupplierRef::query()
            ->where('entity', $entity)
            ->whereNull('local_id')
            ->where('is_ignored', false)
            ->count();
    }

    private function number(int $value): string
    {
        return number_format($value, 0, ',', "\u{00A0}");
    }
}
