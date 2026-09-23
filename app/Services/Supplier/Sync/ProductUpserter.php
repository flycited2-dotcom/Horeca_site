<?php

namespace App\Services\Supplier\Sync;

use App\Enums\ImportEntity;
use App\Models\Brand;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Pricing\RetailPriceCalculator;
use App\Services\Search\SearchTextBuilder;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Import\ImportCounters;
use App\Services\Supplier\Import\ImportLog;
use App\Services\Supplier\Import\SupplierText;
use App\Support\Money;
use App\Support\Slugger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Creates and updates products from staging (TZ §6.3, step 6).
 *
 * A product is found by (supplier_id, external_id) — the supplier GUID, not the article:
 * articles are empty on 22% of the cards and repeat (TZ §6.1, fact 3).
 *
 * Only fields the profile owns are written, and never a field the manager has changed:
 * products.locked_fields is sacred (TZ §6.6).
 */
final class ProductUpserter
{
    /**
     * Payload key => product column. Derived columns (retail price, search text) are
     * recalculated instead of being copied.
     */
    private const array FIELDS = [
        'name' => 'name',
        'model' => 'model',
        'sku' => 'sku',
        'supplier_code' => 'supplier_code',
        'description' => 'description',
        'rrp_price' => 'rrp_price',
        'purchase_price' => 'purchase_price',
        'unit' => 'unit',
    ];

    /**
     * Names of brands the manager pinned by hand, read once per run.
     *
     * @var array<int, string|null>
     */
    private array $brandNames = [];

    public function __construct(
        private readonly RetailPriceCalculator $prices,
        private readonly SearchTextBuilder $search,
    ) {}

    /**
     * @param  array<string, ResolvedRef>  $brands
     */
    public function apply(
        ImportRun $run,
        Supplier $supplier,
        FeedCapabilities $capabilities,
        CategoryMap $categories,
        array $brands,
        ImportLog $log,
        ImportCounters $counters,
        Carbon $syncedAt,
    ): void {
        $slugs = [];

        ImportRow::query()
            ->where('import_run_id', $run->id)
            ->where('entity', ImportEntity::Product)
            ->where('is_valid', true)
            ->orderBy('id')
            ->chunk((int) config('import.chunk_size'), function (Collection $rows) use (
                $supplier,
                $capabilities,
                $categories,
                $brands,
                $log,
                $counters,
                $syncedAt,
                &$slugs
            ): void {
                $this->chunk($rows, $supplier, $capabilities, $categories, $brands, $log, $counters, $syncedAt, $slugs);
            });
    }

    /**
     * @param  Collection<int, ImportRow>  $rows
     * @param  array<string, ResolvedRef>  $brands
     * @param  array<string, int>  $slugs
     */
    private function chunk(
        Collection $rows,
        Supplier $supplier,
        FeedCapabilities $capabilities,
        CategoryMap $categories,
        array $brands,
        ImportLog $log,
        ImportCounters $counters,
        Carbon $syncedAt,
        array &$slugs,
    ): void {
        $existing = Product::withTrashed()
            ->where('supplier_id', $supplier->id)
            ->whereIn('external_id', $rows->pluck('external_id')->all())
            ->get()
            ->keyBy('external_id');

        $unchanged = [];

        foreach ($rows as $row) {
            $product = $existing->get($row->external_id);

            if ($product !== null && $product->source_hash === $row->hash) {
                $unchanged[] = $product->id;
                $counters->unchanged++;

                continue;
            }

            $payload = $row->payload;
            $brand = $this->brand($brands, $payload['brand_name'] ?? null);
            $categoryId = $categories->resolve($payload['category_external_id'] ?? null, $payload['category_name'] ?? null, $log);

            if ($product === null) {
                $product = $this->make($supplier, $payload, $slugs);
                $counters->created++;
            } else {
                $counters->updated++;
            }

            $this->fill($product, $capabilities, $payload, $brand, $categoryId);
            $this->finish($product, $supplier, $brand, $row->hash, $syncedAt);
        }

        if ($unchanged !== []) {
            // Nothing about the product changed, so updated_at stays as it was (TZ §6.3, step 6).
            Product::withTrashed()->whereIn('id', $unchanged)->toBase()->update([
                'last_synced_at' => $syncedAt,
                'missing_runs' => 0,
            ]);
        }
    }

    /**
     * A product the supplier has not sent before. Its address is built once and never
     * changed by the import (TZ §6.6).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $slugs
     */
    private function make(Supplier $supplier, array $payload, array &$slugs): Product
    {
        $product = new Product;
        $product->supplier_id = $supplier->id;
        $product->external_id = (string) $payload['external_id'];
        $product->slug = $this->slug($payload, $slugs);

        return $product;
    }

    /**
     * Writes the fields of this profile, skipping the ones the manager has changed.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fill(Product $product, FeedCapabilities $capabilities, array $payload, ?ResolvedRef $brand, ?int $categoryId): void
    {
        $locked = $this->locked($product);

        foreach (self::FIELDS as $key => $column) {
            $writes = $capabilities->owns($column) || (! $product->exists && $capabilities->seeds($column));

            if (! $writes || isset($locked[$column]) || ! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($column === 'unit' && blank($value)) {
                continue;
            }

            $product->setAttribute($column, match ($column) {
                'rrp_price', 'purchase_price' => $value === null ? null : Money::fromDecimal((string) $value),
                default => $value,
            });
        }

        if ($capabilities->owns('brand_id') && ! isset($locked['brand_id'])) {
            $product->brand_id = $brand?->id;
        }

        if ($capabilities->owns('category_id') && ! isset($locked['category_id'])) {
            $product->category_id = $categoryId;
        }
    }

    /**
     * Recalculates what depends on the fields just written and saves the product.
     */
    private function finish(Product $product, Supplier $supplier, ?ResolvedRef $brand, string $hash, Carbon $syncedAt): void
    {
        $locked = $this->locked($product);

        if (! isset($locked['retail_price'])) {
            $product->retail_price = $this->prices->forSupplier($supplier, $product->rrp_price, $product->purchase_price);
        }

        $product->search_text = $this->search->build(
            $product->name,
            $product->model,
            $product->sku,
            $product->supplier_code,
            $this->brandName($product, $brand),
        );

        $product->source_hash = $hash;
        $product->missing_runs = 0;
        $product->last_synced_at = $syncedAt;
        $product->save();
    }

    /**
     * The brand the product actually has: a manager may have pinned another one, and the
     * search line must match what the customer sees.
     */
    private function brandName(Product $product, ?ResolvedRef $brand): ?string
    {
        if ($product->brand_id === null) {
            return null;
        }

        if ($brand?->id === $product->brand_id) {
            return $brand->name;
        }

        return $this->brandNames[$product->brand_id] ??= Brand::query()->whereKey($product->brand_id)->value('name');
    }

    /**
     * @param  array<string, ResolvedRef>  $brands
     */
    private function brand(array $brands, ?string $name): ?ResolvedRef
    {
        if ($name === null) {
            return null;
        }

        return $brands[SupplierText::key($name)] ?? null;
    }

    /**
     * @return array<string, true>
     */
    private function locked(Product $product): array
    {
        return array_fill_keys($product->locked_fields ?? [], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $slugs
     */
    private function slug(array $payload, array &$slugs): string
    {
        $slug = Slugger::unique(
            (string) $payload['name'],
            fn (string $candidate): bool => isset($slugs[$candidate]) || Product::withTrashed()->where('slug', $candidate)->exists(),
            Slugger::PRODUCT_LIMIT,
            $payload['supplier_code'] ?? $payload['external_id'],
        );

        $slugs[$slug] = 1;

        return $slug;
    }
}
