<?php

namespace App\Services\Supplier\Sync;

use App\Enums\SupplierRefEntity;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier warehouses => warehouses (TZ §6.3, step 6).
 *
 * A new warehouse is visible at once: hiding one and filling in the delivery time
 * to Simferopol is the manager's job (TZ §12).
 */
final class WarehouseSync extends NamedRefSync
{
    protected function entity(): SupplierRefEntity
    {
        return SupplierRefEntity::Warehouse;
    }

    protected function createLocal(Supplier $supplier, string $name): Model
    {
        return Warehouse::query()->create([
            'supplier_id' => $supplier->id,
            'name' => $name,
            'slug' => Slugger::unique(
                $name,
                fn (string $slug): bool => Warehouse::query()->where('slug', $slug)->exists(),
                Slugger::CATEGORY_LIMIT,
            ),
            'is_visible' => true,
            'sort' => 0,
        ]);
    }

    protected function existingLocals(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Warehouse::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    protected function deletedMessage(string $name): string
    {
        return __('import.records.warehouse_deleted', ['name' => $name]);
    }
}
