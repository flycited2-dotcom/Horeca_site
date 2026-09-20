<?php

namespace App\Services\Catalog;

use App\Enums\AttributeType;
use App\Enums\Availability;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Read-only catalog queries for the storefront (TZ §4: every storefront selection goes
 * through here).
 *
 * A product is on the storefront when the manager has not hidden it, it is not
 * discontinued and its category is switched on. A discontinued product keeps its own
 * page (TZ §6.5), but never appears in listings or search.
 */
final class CatalogQuery
{
    public function __construct(
        private readonly CategoryTree $tree,
        private readonly PriceResolver $prices,
    ) {}

    /**
     * Active root categories marked for the home page.
     *
     * @return Collection<int, Category>
     */
    public function homeRootCategories(): Collection
    {
        return Category::query()
            ->active()
            ->roots()
            ->where('show_on_home', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'products_count']);
    }

    /**
     * The "В наличии" strip of the home page (TZ §8.1), most popular first.
     *
     * @return Collection<int, Product>
     */
    public function inStockStrip(?User $user, int $limit = 8): Collection
    {
        return $this->sorted(
            $this->withCardData($this->listed()->where('availability', Availability::InStock), $user),
            CatalogSort::Popular,
        )->limit($limit)->get();
    }

    /**
     * The catalog page: switched-on roots with their switched-on subcategories.
     *
     * @return Collection<int, Category>
     */
    public function rootCategoriesWithChildren(): Collection
    {
        return Category::query()
            ->active()
            ->roots()
            ->with(['children' => fn (HasMany $children) => $children->active()->orderBy('sort')->orderBy('name')])
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'products_count']);
    }

    /**
     * Switched-on subcategories of a category page.
     *
     * @return Collection<int, Category>
     */
    public function activeChildren(Category $category): Collection
    {
        return $category->children()
            ->active()
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'products_count']);
    }

    /**
     * Warehouses the customer may see, with the delivery time the manager filled in
     * (TZ §6.5). The number of items is never shown.
     *
     * @return Collection<int, ProductStock>
     */
    public function visibleStocks(Product $product): Collection
    {
        return $product->stocks()
            ->whereHas('warehouse', fn (Builder $query) => $query->where('is_visible', true))
            ->with('warehouse')
            ->get()
            ->sortBy([
                fn (ProductStock $stock): int => $stock->warehouse?->sort ?? 0,
                fn (ProductStock $stock): string => $stock->warehouse?->name ?? '',
            ])
            ->values();
    }

    /**
     * "Похожие товары" (TZ §8.3): the same category and a price within ±30%; without a
     * price of its own, the same brand.
     *
     * @return Collection<int, Product>
     */
    public function similarProducts(Product $product, ?User $user, int $limit = 4): Collection
    {
        if ($product->category_id === null) {
            return new Collection;
        }

        $similar = $this->listed()
            ->whereKeyNot($product->id)
            ->where('category_id', $product->category_id);

        if ($product->retail_price !== null) {
            $similar
                ->whereNotNull('retail_price')
                ->whereBetween('retail_price', [
                    Money::ofKopecks(intdiv($product->retail_price->kopecks * 7, 10))->toDecimal(),
                    Money::ofKopecks(intdiv($product->retail_price->kopecks * 13, 10))->toDecimal(),
                ]);
        } elseif ($product->brand_id !== null) {
            $similar->where('brand_id', $product->brand_id);
        }

        return $this->sorted($this->withCardData($similar, $user), CatalogSort::Popular)
            ->limit($limit)
            ->get();
    }

    /**
     * Products a listing or a search may show.
     *
     * @return Builder<Product>
     */
    public function listed(): Builder
    {
        return Product::query()
            ->where('is_visible', true)
            ->where('availability', '!=', Availability::Discontinued)
            ->whereIn('category_id', Category::query()->active()->select('id'));
    }

    /**
     * Everything a product card needs, loaded up front: no query per card (TZ, N+1 запрещён).
     *
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public function withCardData(Builder $products, ?User $user): Builder
    {
        $tier = $this->prices->tierOf($user);

        return $products
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug,icon',
                'media' => fn (MorphMany $query) => $query->where('collection_name', Product::IMAGES),
            ])
            ->when($tier !== null, fn (Builder $query) => $query->with([
                'prices' => fn (HasMany $prices) => $prices->where('price_tier_id', $tier->id),
            ]));
    }

    /**
     * A category page: the category and its switched-on subcategories (TZ §8.2).
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function categoryProducts(Category $category, CatalogFilters $filters, ?User $user, int $page = 1): LengthAwarePaginator
    {
        $products = $this->filtered($this->inCategory($category), $filters);

        return $this->sorted($this->withCardData($products, $user), $filters->sort)
            ->paginate(CatalogFilters::PER_PAGE, page: max(1, $page));
    }

    /**
     * Products of the category page before the customer's filters: the base for facets.
     *
     * @return Builder<Product>
     */
    public function inCategory(Category $category): Builder
    {
        return $this->listed()->whereIn('category_id', $this->tree->activeBranch($category->id));
    }

    /**
     * Applies the filters of the listing (TZ §8.2). Products with the price on request
     * never match a price range.
     *
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public function filtered(Builder $products, CatalogFilters $filters): Builder
    {
        if ($filters->priceFrom !== null) {
            $products->where('retail_price', '>=', Money::ofRubles($filters->priceFrom)->toDecimal());
        }

        if ($filters->priceTo !== null) {
            $products->where('retail_price', '<=', Money::ofRubles($filters->priceTo)->toDecimal());
        }

        if ($filters->inStockOnly) {
            $products->whereIn('availability', [Availability::InStock, Availability::Low]);
        }

        if ($filters->brands !== []) {
            $products->whereIn('brand_id', Brand::query()->whereIn('slug', $filters->brands)->select('id'));
        }

        if ($filters->attributes !== []) {
            $this->filterByAttributes($products, $filters->attributes);
        }

        return $products;
    }

    /**
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public function sorted(Builder $products, CatalogSort $sort): Builder
    {
        return match ($sort) {
            CatalogSort::Popular => $products->orderBy('availability_rank')->orderByDesc('popularity')->orderBy('name'),
            // "Цена по запросу" goes last in both directions.
            CatalogSort::PriceAsc => $products->orderByRaw('retail_price IS NULL')->orderBy('retail_price')->orderBy('name'),
            CatalogSort::PriceDesc => $products->orderByRaw('retail_price IS NULL')->orderByDesc('retail_price')->orderBy('name'),
            CatalogSort::Newest => $products->orderByDesc('created_at')->orderByDesc('id'),
            CatalogSort::Name => $products->orderBy('name'),
        };
    }

    /**
     * Brands of the listing with the number of products each: the brand filter.
     *
     * @param  Builder<Product>  $products
     * @return Collection<int, Brand>
     */
    public function brandFacet(Builder $products): Collection
    {
        $counts = (clone $products)
            ->whereNotNull('brand_id')
            ->toBase()
            ->reorder()
            ->groupBy('brand_id')
            ->selectRaw('brand_id, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'brand_id');

        return Brand::query()
            ->whereKey($counts->keys()->all())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->each(fn (Brand $brand) => $brand->setAttribute('products_count', (int) $counts[$brand->id]));
    }

    /**
     * The cheapest and the dearest price of the listing, or null when every product is
     * "Цена по запросу".
     *
     * @param  Builder<Product>  $products
     * @return array{min: Money, max: Money}|null
     */
    public function priceRange(Builder $products): ?array
    {
        $range = (clone $products)
            ->toBase()
            ->reorder()
            ->whereNotNull('retail_price')
            ->selectRaw('MIN(retail_price) AS min_price, MAX(retail_price) AS max_price')
            ->first();

        if ($range === null || $range->min_price === null) {
            return null;
        }

        return [
            'min' => Money::fromDecimal((string) $range->min_price),
            'max' => Money::fromDecimal((string) $range->max_price),
        ];
    }

    /**
     * Only characteristics marked as filterable take part (TZ §8.2).
     *
     * @param  Builder<Product>  $products
     * @param  array<string, array{min?: string, max?: string, values?: list<string>}>  $conditions
     */
    private function filterByAttributes(Builder $products, array $conditions): void
    {
        $attributes = Attribute::query()
            ->where('is_filterable', true)
            ->whereIn('slug', array_keys($conditions))
            ->get(['id', 'slug', 'type']);

        foreach ($attributes as $attribute) {
            $condition = $conditions[$attribute->slug];

            $products->whereHas('attributeValues', function (Builder $query) use ($attribute, $condition): void {
                $query->where('attributes.id', $attribute->id);

                match ($attribute->type) {
                    AttributeType::Number => $query
                        ->when(isset($condition['min']), fn (Builder $q) => $q->where('attribute_product.value_number', '>=', $condition['min']))
                        ->when(isset($condition['max']), fn (Builder $q) => $q->where('attribute_product.value_number', '<=', $condition['max'])),
                    AttributeType::Text => $query
                        ->when(isset($condition['values']), fn (Builder $q) => $q->whereIn('attribute_product.value_string', $condition['values'])),
                    AttributeType::Boolean => $query
                        ->when(isset($condition['values']), fn (Builder $q) => $q->whereIn('attribute_product.value_bool', array_map(
                            fn (string $value): int => in_array($value, ['1', 'true', 'да'], true) ? 1 : 0,
                            $condition['values'],
                        ))),
                };
            });
        }
    }
}
