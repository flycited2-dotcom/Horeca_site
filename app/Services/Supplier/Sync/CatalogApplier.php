<?php

namespace App\Services\Supplier\Sync;

use App\Actions\Catalog\RecalculateCategoryCounts;
use App\Enums\ImportEntity;
use App\Models\ImportProfile;
use App\Models\ImportRun;
use App\Services\Catalog\AvailabilityCalculator;
use App\Services\Settings\Settings;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Import\ImportCounters;
use App\Services\Supplier\Import\ImportLog;
use App\Services\Supplier\Import\StagingStats;

/**
 * Moves a checked snapshot from staging into the catalog (TZ §6.3, steps 6–8).
 *
 * The caller wraps this in one transaction: either the whole snapshot lands or nothing
 * does. That is also what makes a dry run exact — it is the same work, rolled back.
 */
final class CatalogApplier
{
    public function __construct(
        private readonly CategorySync $categories,
        private readonly BrandSync $brands,
        private readonly WarehouseSync $warehouses,
        private readonly ProductUpserter $products,
        private readonly StockUpserter $stocks,
        private readonly SnapshotFinalizer $finalizer,
        private readonly AvailabilityCalculator $availability,
        private readonly RecalculateCategoryCounts $categoryCounts,
        private readonly Settings $settings,
    ) {}

    public function apply(
        ImportRun $run,
        ImportProfile $profile,
        FeedCapabilities $capabilities,
        StagingStats $stats,
        ImportLog $log,
    ): ImportCounters {
        $supplier = $profile->supplier;
        $counters = new ImportCounters;
        $syncedAt = $run->started_at ?? now();

        if ($capabilities->provides(ImportEntity::Product)) {
            $categories = $capabilities->provides(ImportEntity::Category)
                ? $this->categories->sync($run, $supplier, $log)
                : new CategoryMap([], []);

            $brands = $this->brands->sync($supplier, $stats->brands(), $log);

            if ($capabilities->isFullSnapshot) {
                $this->finalizer->beforeCatalogApply($supplier);
            }

            $this->products->apply($run, $supplier, $capabilities, $categories, $brands, $log, $counters, $syncedAt);

            if ($capabilities->isFullSnapshot) {
                $this->finalizer->afterCatalogApply(
                    $supplier,
                    $this->settings->integer('catalog.discontinued_after_runs', 3),
                    $counters,
                );
            }
        }

        if ($capabilities->provides(ImportEntity::Stock)) {
            $warehouses = $this->warehouses->sync($supplier, $stats->warehouses(), $log);

            $this->stocks->apply($run, $supplier, $capabilities, $warehouses, $log, $counters, $syncedAt);
        }

        $this->availability->recalculateForSupplier($supplier->id);
        $this->categoryCounts->handle();

        $supplier->forceFill(['last_import_at' => now()])->save();

        return $counters;
    }
}
