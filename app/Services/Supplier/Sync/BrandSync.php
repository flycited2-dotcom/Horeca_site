<?php

namespace App\Services\Supplier\Sync;

use App\Enums\SupplierRefEntity;
use App\Models\Brand;
use App\Models\Supplier;
use App\Services\Supplier\Import\SupplierText;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier trade marks => brands (TZ §6.3, step 6).
 */
final class BrandSync extends NamedRefSync
{
    protected function entity(): SupplierRefEntity
    {
        return SupplierRefEntity::Brand;
    }

    /**
     * The shop may already know this brand under the same name: the manager created it or
     * the match was lost. Then the products go to it instead of to a second "Abat".
     */
    protected function findLocal(Supplier $supplier, string $name): ?Model
    {
        return Brand::query()
            ->whereRaw('LOWER(name) = ?', [SupplierText::key($name)])
            ->orderBy('id')
            ->first();
    }

    protected function createLocal(Supplier $supplier, string $name): Model
    {
        return Brand::query()->create([
            'name' => $name,
            'slug' => Slugger::unique(
                $name,
                fn (string $slug): bool => Brand::query()->where('slug', $slug)->exists(),
                Slugger::CATEGORY_LIMIT,
            ),
            'is_active' => true,
        ]);
    }

    protected function existingLocals(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Brand::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    protected function deletedMessage(string $name): string
    {
        return __('import.records.brand_deleted', ['name' => $name]);
    }
}
