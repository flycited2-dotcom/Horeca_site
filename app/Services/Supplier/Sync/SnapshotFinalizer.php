<?php

namespace App\Services\Supplier\Sync;

use App\Enums\Availability;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Supplier\Import\ImportCounters;

/**
 * Handles the products a full catalog snapshot no longer mentions (TZ §6.3, step 7).
 *
 * Products are never deleted: a card that disappeared for a few runs in a row is marked
 * "снят с производства" and stays in the shop for search engines and for the "подобрать
 * аналог" request. A card that comes back is put on sale again.
 *
 * Absence is counted instead of compared by timestamps: the counter is raised for the
 * whole supplier before the products are written, and every product of the snapshot
 * resets it to zero. Both steps run inside the single apply transaction.
 */
final class SnapshotFinalizer
{
    public function beforeCatalogApply(Supplier $supplier): void
    {
        Product::withTrashed()
            ->where('supplier_id', $supplier->id)
            ->toBase()
            ->increment('missing_runs');
    }

    public function afterCatalogApply(Supplier $supplier, int $discontinuedAfterRuns, ImportCounters $counters): void
    {
        Product::withTrashed()
            ->where('supplier_id', $supplier->id)
            ->where('missing_runs', 0)
            ->where('availability', Availability::Discontinued)
            ->toBase()
            ->update([
                'availability' => Availability::OnOrder->value,
                'availability_rank' => Availability::OnOrder->rank(),
            ]);

        $counters->discontinued = Product::withTrashed()
            ->where('supplier_id', $supplier->id)
            ->where('missing_runs', '>=', max(1, $discontinuedAfterRuns))
            ->where('availability', '!=', Availability::Discontinued)
            ->toBase()
            ->update([
                'availability' => Availability::Discontinued->value,
                'availability_rank' => Availability::Discontinued->rank(),
            ]);
    }
}
