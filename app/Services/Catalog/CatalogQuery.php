<?php

namespace App\Services\Catalog;

use App\Enums\AttributeType;
use App\Enums\Availability;
use App\Enums\WarehouseStockStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

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
    /**
     * «Показать ещё» never loads more than this many pages at once.
     */
    public const int MAX_PAGES = 20;

    public function __construct(
        private readonly CategoryTree $tree,
        private readonly PriceResolver $prices,
        private readonly CatalogCache $cache,
    ) {}

    /**
     * Switched-on root categories with products for the header, the menu and the footer:
     * an empty section would lead to an empty listing. The layout shows them on every page,
     * so they are cached until the catalog changes: the import and the manager bump the
     * catalog cache version.
     *
     * @return list<array{id: int, name: string, slug: string, icon: ?string, show_on_home: bool, products_count: int}>
     */
    public function navigationCategories(): array
    {
        return Cache::remember($this->cache->key('navigation'), now()->addDay(), fn (): array => Category::query()
            ->active()
            ->roots()
            ->where('products_count', '>', 0)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'show_on_home', 'products_count'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'show_on_home' => $category->show_on_home,
                'products_count' => $category->products_count,
            ])
            ->all());
    }

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
     * The tiles of the home page (TZ §8.1, layout — screen 4): the root sections marked for
     * the home page with the number of products, how many of them are in stock and the
     * biggest subsections. Cached until the catalog changes, like the navigation.
     *
     * @return array{products: int, brands: int, sections: list<array{id: int, name: string, slug: string, icon: ?string, products_count: int, in_stock: int, children: list<string>}>}
     */
    public function homeSections(): array
    {
        return Cache::remember($this->cache->key('home'), now()->addDay(), function (): array {
            $roots = $this->homeRootCategories();
            $inStock = [];

            $counts = $this->listed()
                ->whereIn('availability', [Availability::InStock, Availability::Low])
                ->toBase()
                ->groupBy('category_id')
                ->selectRaw('category_id, COUNT(*) AS aggregate')
                ->pluck('aggregate', 'category_id');

            foreach ($counts as $categoryId => $count) {
                $root = $this->tree->rootOf((int) $categoryId);

                if ($root !== null) {
                    $inStock[$root] = ($inStock[$root] ?? 0) + (int) $count;
                }
            }

            $children = Category::query()
                ->active()
                ->whereIn('parent_id', $roots->modelKeys())
                ->where('products_count', '>', 0)
                ->orderByDesc('products_count')
                ->get(['parent_id', 'name'])
                ->groupBy('parent_id');

            return [
                'products' => $this->listed()->count(),
                'brands' => $this->listed()->whereNotNull('brand_id')->distinct()->count('brand_id'),
                'sections' => $roots->map(fn (Category $root): array => [
                    'id' => $root->id,
                    'name' => $root->name,
                    'slug' => $root->slug,
                    'icon' => $root->icon,
                    'products_count' => $root->products_count,
                    'in_stock' => $inStock[$root->id] ?? 0,
                    'children' => $children->get($root->id, collect())->take(3)->pluck('name')->all(),
                ])->all(),
            ];
        });
    }

    /**
     * Switched-on brands with products on the storefront, by name, with the number of those
     * products: the page «Бренды» and the list on the home page (TZ §8.1). Cached until the
     * catalog changes, like the navigation.
     *
     * @return list<array{name: string, slug: string, products_count: int}>
     */
    public function brandDirectory(): array
    {
        return Cache::remember($this->cache->key('brands'), now()->addDay(), fn (): array => $this->brandFacet($this->listed())
            ->map(fn (Brand $brand): array => [
                'name' => $brand->name,
                'slug' => $brand->slug,
                'products_count' => (int) $brand->products_count,
            ])
            ->values()
            ->all());
    }

    /**
     * The brands with the most products on the storefront, still by name: the list of brands
     * on the home page (TZ §8.1).
     *
     * @return list<array{name: string, slug: string, products_count: int}>
     */
    public function leadingBrands(int $limit): array
    {
        $directory = collect($this->brandDirectory());
        $leading = $directory->sortByDesc('products_count')->take($limit)->keys()->all();

        return $directory->only($leading)->values()->all();
    }

    /**
     * «Часто заказывают» — products the manager marked as hits (TZ §8.1).
     *
     * @return Collection<int, Product>
     */
    public function hitsStrip(?User $user, int $limit = 4): Collection
    {
        return $this->sorted($this->withCardData($this->listed()->where('is_hit', true), $user), CatalogSort::Popular)
            ->limit($limit)
            ->get();
    }

    /**
     * «Новинки» — products the manager marked as new, newest first (TZ §8.1).
     *
     * @return Collection<int, Product>
     */
    public function newStrip(?User $user, int $limit = 4): Collection
    {
        return $this->sorted($this->withCardData($this->listed()->where('is_new', true), $user), CatalogSort::Newest)
            ->limit($limit)
            ->get();
    }

    /**
     * Products in stock at the local warehouse (TZ §8.1): the strip appears only when there
     * are at least $minimum of them, otherwise it would look like an empty shop.
     *
     * @return Collection<int, Product>
     */
    public function localStockStrip(?User $user, string $warehouse, int $minimum, int $limit = 4): Collection
    {
        $products = $this->listed()->whereHas('stocks', fn (Builder $stocks) => $stocks
            ->whereIn('status', [WarehouseStockStatus::InStock, WarehouseStockStatus::Low])
            ->whereHas('warehouse', fn (Builder $warehouses) => $warehouses->where('name', $warehouse)->where('is_visible', true)));

        if ((clone $products)->count() < $minimum) {
            return new Collection;
        }

        return $this->sorted($this->withCardData($products, $user), CatalogSort::Popular)->limit($limit)->get();
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
     * Switched-on subcategories with products of a category page: an empty one would lead
     * to an empty listing.
     *
     * @return Collection<int, Category>
     */
    public function activeChildren(Category $category): Collection
    {
        return $category->children()
            ->active()
            ->where('products_count', '>', 0)
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
     * «Часто берут вместе» on a product page (TZ §8.3): the related products the manager
     * linked, only those the storefront may show.
     *
     * @return Collection<int, Product>
     */
    public function relatedProducts(Product $product, ?User $user, int $limit = 3): Collection
    {
        return $this->withCardData($this->listed(), $user)
            ->whereIn('id', $product->relatedProducts()->reorder()->select('products.id'))
            ->orderBy('availability_rank')
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
     * Pages $page … $page + $pages − 1 of a category page (TZ §8.2): the category and its
     * switched-on subcategories.
     */
    public function categorySlice(Category $category, CatalogFilters $filters, ?User $user, int $page = 1, int $pages = 1): ListingSlice
    {
        return $this->slice($this->sorted($this->filtered($this->inCategory($category), $filters), $filters->sort), $user, $page, $pages);
    }

    /**
     * Pages $page … $page + $pages − 1 of an ordered listing: «Показать ещё» adds pages to
     * those on screen; a page past the end shows the last one. At most MAX_PAGES are loaded
     * at once.
     *
     * @param  Builder<Product>  $ordered
     */
    public function slice(Builder $ordered, ?User $user, int $page = 1, int $pages = 1): ListingSlice
    {
        $total = (clone $ordered)->count();

        $pageCount = max(1, (int) ceil($total / CatalogFilters::PER_PAGE));
        $first = min(max(1, $page), $pageCount);
        $last = min($pageCount, $first + min(max(1, $pages), self::MAX_PAGES) - 1);

        $items = $total === 0 ? new Collection : $this->withCardData($ordered, $user)
            ->skip(($first - 1) * CatalogFilters::PER_PAGE)
            ->take(($last - $first + 1) * CatalogFilters::PER_PAGE)
            ->get();

        return new ListingSlice($items, $total, $first, $last);
    }

    /**
     * How many products the filters leave in a category: «Снять «Abat» — 34 позиции».
     */
    public function countInCategory(Category $category, CatalogFilters $filters): int
    {
        return $this->filtered($this->inCategory($category), $filters)->count();
    }

    /**
     * Products in stock among the given ones: the number next to «Только в наличии».
     *
     * @param  Builder<Product>  $products
     */
    public function inStockCount(Builder $products): int
    {
        return (clone $products)->whereIn('availability', [Availability::InStock, Availability::Low])->count();
    }

    /**
     * Products of the category page before the customer's filters: the base for facets.
     *
     * @return Builder<Product>
     */
    public function inCategory(Category $category): Builder
    {
        return $this->inBranch($this->listed(), $category);
    }

    /**
     * Products of a brand page before the customer's filters.
     *
     * @return Builder<Product>
     */
    public function ofBrand(Brand $brand): Builder
    {
        return $this->listed()->where('brand_id', $brand->id);
    }

    /**
     * The given products narrowed to a category and its switched-on subcategories: a section
     * chosen on the search page or on a brand page.
     *
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public function inBranch(Builder $products, Category $category): Builder
    {
        return $products->whereIn('category_id', $this->tree->activeBranch($category->id));
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
     * Switched-on categories the given products belong to, most products first, with the
     * number of those products: «Уточнить: Пароконвектоматы · 34» on the search page.
     *
     * @param  Builder<Product>  $products
     * @return Collection<int, Category>
     */
    public function categoryFacet(Builder $products, int $limit = 5): Collection
    {
        $counts = (clone $products)
            ->toBase()
            ->reorder()
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) AS aggregate')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->pluck('aggregate', 'category_id');

        $categories = Category::query()
            ->active()
            ->whereKey($counts->keys()->all())
            ->get(['id', 'name', 'slug'])
            ->each(fn (Category $category) => $category->setAttribute('products_count', (int) $counts[$category->id]));

        return $categories->sortByDesc('products_count')->values();
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
