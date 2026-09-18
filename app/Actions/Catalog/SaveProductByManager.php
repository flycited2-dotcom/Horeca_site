<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Models\Product;
use App\Services\Catalog\CatalogCache;
use App\Services\Search\SearchTextBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Saves products changed by hand in the admin panel (TZ §6.6).
 *
 * Every field the import could overwrite is added to products.locked_fields when the
 * manager changes it, so the next import run leaves the manual value alone. The search
 * line is rebuilt, because it depends on the name, the codes and the brand (TZ §8.4).
 */
final class SaveProductByManager
{
    /**
     * Fields a supplier source writes (FeedCapabilities::PRODUCT_FIELDS without the stock
     * records) plus the retail price, which the import recalculates.
     */
    public const array LOCKABLE_FIELDS = [
        'name', 'model', 'sku', 'supplier_code', 'description', 'brand_id', 'category_id',
        'rrp_price', 'purchase_price', 'unit', 'retail_price',
    ];

    /**
     * Fields the search line is built from.
     */
    private const array SEARCH_FIELDS = ['name', 'model', 'sku', 'supplier_code', 'brand_id'];

    /**
     * Fields categories.products_count depends on.
     */
    private const array COUNT_FIELDS = ['category_id', 'is_visible'];

    public function __construct(
        private readonly SearchTextBuilder $search,
        private readonly CatalogCache $cache,
        private readonly RecalculateCategoryCounts $counts,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Product $product, array $attributes): Product
    {
        DB::transaction(function () use ($product, $attributes): void {
            if ($this->apply($product, $attributes)) {
                $this->counts->handle();
            }
        });

        $this->cache->bump();

        return $product;
    }

    /**
     * The same change for many products at once: a bulk action of the product table.
     *
     * @param  iterable<int, Product>  $products
     * @param  array<string, mixed>  $attributes
     */
    public function handleMany(iterable $products, array $attributes): void
    {
        DB::transaction(function () use ($products, $attributes): void {
            $recount = false;

            foreach ($products as $product) {
                $recount = $this->apply($product, $attributes) || $recount;
            }

            if ($recount) {
                $this->counts->handle();
            }
        });

        $this->cache->bump();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return bool whether the category counters need recalculating
     */
    private function apply(Product $product, array $attributes): bool
    {
        $product->fill($attributes);

        $changed = array_values(array_intersect(self::LOCKABLE_FIELDS, array_keys($product->getDirty())));

        if ($changed !== []) {
            $product->locked_fields = array_values(array_unique([...($product->locked_fields ?? []), ...$changed]));
        }

        if ($product->isDirty(self::SEARCH_FIELDS)) {
            $product->search_text = $this->search->build(
                $product->name,
                $product->model,
                $product->sku,
                $product->supplier_code,
                $product->brand_id === null ? null : Brand::query()->whereKey($product->brand_id)->value('name'),
            );
        }

        $recount = $product->isDirty(self::COUNT_FIELDS);

        $product->save();

        return $recount;
    }
}
