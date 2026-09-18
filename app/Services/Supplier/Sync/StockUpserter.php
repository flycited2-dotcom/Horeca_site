<?php

namespace App\Services\Supplier\Sync;

use App\Enums\ImportEntity;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Supplier;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Import\ImportCounters;
use App\Services\Supplier\Import\ImportLog;
use App\Services\Supplier\Import\SupplierText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Replaces the warehouse stocks of the products in the snapshot (TZ §6.3, steps 6–7).
 *
 * The stock feed is a full snapshot, so the supplier stocks are wiped first and written
 * anew: a product that left the feed loses its stocks and falls back to "под заказ".
 * 60% of the catalog is never in the stock file, and 4 596 stock positions have no card
 * in the catalog — those are counted and reported, not created (TZ §6.1, facts 10–11).
 */
final class StockUpserter
{
    /**
     * @param  array<string, ResolvedRef>  $warehouses
     */
    public function apply(
        ImportRun $run,
        Supplier $supplier,
        FeedCapabilities $capabilities,
        array $warehouses,
        ImportLog $log,
        ImportCounters $counters,
        Carbon $syncedAt,
    ): void {
        if ($capabilities->isFullSnapshot) {
            $this->clearSupplierStocks($supplier);
        }

        $notInCatalog = 0;

        ImportRow::query()
            ->where('import_run_id', $run->id)
            ->where('entity', ImportEntity::Stock)
            ->where('is_valid', true)
            ->orderBy('id')
            ->chunk((int) config('import.chunk_size'), function (Collection $rows) use (
                $supplier,
                $capabilities,
                $warehouses,
                $counters,
                $syncedAt,
                &$notInCatalog
            ): void {
                $notInCatalog += $this->chunk($rows, $supplier, $capabilities, $warehouses, $counters, $syncedAt);
            });

        if ($notInCatalog > 0) {
            $log->add(__('import.records.products_not_in_catalog', ['count' => $notInCatalog]));
        }
    }

    /**
     * @param  Collection<int, ImportRow>  $rows
     * @param  array<string, ResolvedRef>  $warehouses
     * @return int stock positions whose product is not in the catalog
     */
    private function chunk(
        Collection $rows,
        Supplier $supplier,
        FeedCapabilities $capabilities,
        array $warehouses,
        ImportCounters $counters,
        Carbon $syncedAt,
    ): int {
        $products = Product::withTrashed()
            ->where('supplier_id', $supplier->id)
            ->whereIn('external_id', $rows->pluck('external_id')->all())
            ->get(['id', 'external_id', 'unit', 'locked_fields'])
            ->keyBy('external_id');

        $stocks = [];
        $units = [];
        $notInCatalog = 0;

        foreach ($rows as $row) {
            $product = $products->get($row->external_id);

            if ($product === null) {
                $notInCatalog++;

                continue;
            }

            $payload = $row->payload;
            $counters->updated++;

            foreach ($payload['entries'] ?? [] as $entry) {
                $warehouse = $warehouses[SupplierText::key($entry['warehouse_name'])] ?? null;

                if ($warehouse?->id === null) {
                    continue;
                }

                $stocks[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'status' => $entry['status'],
                    'quantity' => $entry['quantity'],
                    'raw_value' => $entry['raw_value'],
                    'unit' => $payload['unit'] ?? null,
                    'synced_at' => $syncedAt,
                ];
            }

            if ($capabilities->owns('unit') && filled($payload['unit'] ?? null) && $this->acceptsUnit($product, $payload['unit'])) {
                $units[$payload['unit']][] = $product->id;
            }
        }

        if (! $capabilities->isFullSnapshot) {
            ProductStock::query()->whereIn('product_id', $products->pluck('id')->all())->delete();
        }

        foreach (array_chunk($stocks, (int) config('import.chunk_size')) as $batch) {
            ProductStock::query()->insert($batch);
        }

        foreach ($units as $unit => $ids) {
            Product::withTrashed()->whereIn('id', $ids)->toBase()->update(['unit' => $unit]);
        }

        return $notInCatalog;
    }

    /**
     * The unit is written only when it really changes and the manager has not fixed it by hand.
     */
    private function acceptsUnit(Product $product, string $unit): bool
    {
        return $product->unit !== $unit && ! in_array('unit', $product->locked_fields ?? [], true);
    }

    private function clearSupplierStocks(Supplier $supplier): void
    {
        ProductStock::query()
            ->whereIn('product_id', Product::withTrashed()->where('supplier_id', $supplier->id)->select('id'))
            ->delete();
    }
}
