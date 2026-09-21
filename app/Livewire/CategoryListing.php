<?php

namespace App\Livewire;

use App\Models\Brand;
use App\Models\Category;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Services\Pricing\PriceResolver;
use App\Support\Typography;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Листинг категории (ТЗ §8.2, макет — экраны 2, 10 и 14): фильтры применяются без
 * перезагрузки, состояние живёт в адресе (pushState), поэтому ссылкой на выборку можно
 * поделиться. Страница рисуется на сервере целиком: без скриптов работают те же ссылки
 * и GET-форма. Выборки — в CatalogQuery, здесь только состояние листинга.
 */
final class CategoryListing extends Component
{
    public const string VIEW_COOKIE = 'listing_view';

    public const array VIEWS = ['grid', 'list'];

    /**
     * Brands shown before «Ещё N брендов».
     */
    public const int BRANDS_SHOWN = 6;

    /**
     * «Снять «…» — N позиций» in an empty result: no more suggestions than this.
     */
    private const int SUGGESTIONS = 4;

    #[Locked]
    public Category $category;

    #[Locked]
    public string $title = '';

    /**
     * Switched-on subcategories as plain data: they do not change with the filters.
     *
     * @var list<array{name: string, slug: string, products_count: int}>
     */
    #[Locked]
    public array $subcategories = [];

    #[Url(as: 'price_from', history: true, except: '')]
    public string $priceFrom = '';

    #[Url(as: 'price_to', history: true, except: '')]
    public string $priceTo = '';

    #[Url(as: 'in_stock', history: true, except: false)]
    public bool $inStock = false;

    /**
     * @var list<string>
     */
    #[Url(as: 'brand', history: true, except: [])]
    public array $brands = [];

    #[Url(history: true, except: 'popular')]
    public string $sort = 'popular';

    #[Url(history: true, except: 1)]
    public int $page = 1;

    /**
     * Pages on screen: «Показать ещё» appends the next one without leaving the address.
     */
    public int $pages = 1;

    public string $view = 'grid';

    /**
     * Brand search inside the filter panel; works only with scripts, so it stays out of the address.
     */
    public string $brandQuery = '';

    /**
     * «Ещё N брендов» pressed: the whole brand list is shown.
     */
    public bool $allBrands = false;

    /**
     * @param  list<array{name: string, slug: string, products_count: int}>  $subcategories
     */
    public function mount(Category $category, string $title, array $subcategories = []): void
    {
        $this->category = $category;
        $this->title = $title;
        $this->subcategories = $subcategories;

        // A link with ?view= (scripts off) switches the view and remembers it.
        $requested = request()->query('view');

        if (is_string($requested) && in_array($requested, self::VIEWS, true)) {
            $this->rememberView($requested);
        } else {
            $saved = request()->cookie(self::VIEW_COOKIE);
            $this->view = is_string($saved) && in_array($saved, self::VIEWS, true) ? $saved : 'grid';
        }
    }

    /**
     * Any filter change starts the listing from its first page.
     */
    public function updated(string $property): void
    {
        if (in_array(strtok($property, '.'), ['priceFrom', 'priceTo', 'inStock', 'brands', 'sort'], true)) {
            $this->startOver();
        }
    }

    public function sortBy(string $sort): void
    {
        $this->sort = (CatalogSort::tryFrom($sort) ?? CatalogSort::Popular)->value;
        $this->startOver();
    }

    public function removeFilter(string $filter, ?string $brand = null): void
    {
        if ($filter === 'price') {
            $this->priceFrom = '';
            $this->priceTo = '';
        } elseif ($filter === 'in_stock') {
            $this->inStock = false;
        } elseif ($filter === 'brand') {
            $this->brands = array_values(array_diff($this->brands, [$brand]));
        }

        $this->startOver();
    }

    public function resetFilters(): void
    {
        $this->priceFrom = '';
        $this->priceTo = '';
        $this->inStock = false;
        $this->brands = [];
        $this->startOver();
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->pages = 1;
    }

    public function loadMore(): void
    {
        $this->pages = min($this->pages + 1, CatalogQuery::MAX_PAGES);
    }

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->rememberView($view);
        }
    }

    /**
     * The mobile sheet and the form without scripts: the filters are already applied live.
     */
    public function applyFilters(): void
    {
        $this->startOver();
    }

    public function render(CatalogQuery $catalog, PriceResolver $prices): View
    {
        $user = request()->user();
        $filters = $this->filters();
        $scope = $catalog->inCategory($this->category);

        $slice = $catalog->categorySlice($this->category, $filters, $user, $this->page, $this->pages);
        // Each facet is counted under the other filters, so a count never promises an empty list.
        $brands = $catalog->brandFacet($catalog->filtered(clone $scope, $filters->without('brand')));
        $chips = $this->chips($filters, $brands);

        return view('livewire.category-listing', [
            'filters' => $filters,
            'slice' => $slice,
            'prices' => $prices->forMany($slice->products, $user),
            'brandOptions' => $brands,
            'inStockCount' => $catalog->inStockCount($catalog->filtered(clone $scope, $filters->without('in_stock'))),
            'priceRange' => $catalog->priceRange($scope),
            'categoryTotal' => $filters->isFiltered() ? $catalog->countInCategory($this->category, $filters->cleared()) : $slice->total,
            'chips' => $chips,
            'suggestions' => $slice->total === 0 && $filters->isFiltered() ? $this->suggestions($catalog, $filters, $chips) : [],
            'sorts' => CatalogSort::cases(),
            'brandQuery' => $this->brandQuery,
            'allBrands' => $this->allBrands,
            'baseUrl' => route('category', $this->category),
            'urlFor' => fn (CatalogFilters $state, array $extra = []): string => $this->urlFor($state, $extra),
        ]);
    }

    /**
     * The state of the listing, cleaned the same way as a query string from the address.
     */
    private function filters(): CatalogFilters
    {
        return CatalogFilters::fromQuery([
            'price_from' => $this->priceFrom,
            'price_to' => $this->priceTo,
            'in_stock' => $this->inStock ? '1' : null,
            'brand' => $this->brands,
            'sort' => $this->sort,
        ]);
    }

    /**
     * Applied filters as chips over the grid, each with the address that drops it.
     *
     * @param  Collection<int, Brand>  $facet
     * @return list<array{label: string, filter: string, brand: ?string, url: string}>
     */
    private function chips(CatalogFilters $filters, Collection $facet): array
    {
        $chips = [];

        if ($filters->inStockOnly) {
            $chips[] = $this->chip(__('shop.catalog.in_stock_only'), $filters, 'in_stock');
        }

        if ($filters->priceFrom !== null || $filters->priceTo !== null) {
            $chips[] = $this->chip($this->priceLabel($filters), $filters, 'price');
        }

        if ($filters->brands !== []) {
            $names = $facet->pluck('name', 'slug');
            $missing = array_diff($filters->brands, $names->keys()->all());

            if ($missing !== []) {
                $names = $names->merge(Brand::query()->whereIn('slug', $missing)->pluck('name', 'slug'));
            }

            foreach ($filters->brands as $slug) {
                $chips[] = $this->chip($names[$slug] ?? $slug, $filters, 'brand', $slug);
            }
        }

        return $chips;
    }

    /**
     * @return array{label: string, filter: string, brand: ?string, url: string}
     */
    private function chip(string $label, CatalogFilters $filters, string $filter, ?string $brand = null): array
    {
        return [
            'label' => $label,
            'filter' => $filter,
            'brand' => $brand,
            'url' => $this->urlFor($filters->without($filter, $brand)),
        ];
    }

    /**
     * «от 20 000 ₽», «до 400 000 ₽» or «20 000 — 400 000 ₽».
     */
    private function priceLabel(CatalogFilters $filters): string
    {
        $rubles = fn (int $value): string => Typography::number($value);

        return match (true) {
            $filters->priceFrom !== null && $filters->priceTo !== null => __('shop.catalog.chip_price_range', ['from' => $rubles($filters->priceFrom), 'to' => $rubles($filters->priceTo)]),
            $filters->priceFrom !== null => __('shop.catalog.chip_price_from', ['from' => $rubles($filters->priceFrom)]),
            default => __('shop.catalog.chip_price_to', ['to' => $rubles((int) $filters->priceTo)]),
        };
    }

    /**
     * An empty result says which filter to drop and what that gives (layout — screen 2).
     *
     * @param  list<array{label: string, filter: string, brand: ?string, url: string}>  $chips
     * @return list<array{label: string, filter: string, brand: ?string, url: string, count: int}>
     */
    private function suggestions(CatalogQuery $catalog, CatalogFilters $filters, array $chips): array
    {
        $suggestions = [];

        foreach (array_slice($chips, 0, self::SUGGESTIONS) as $chip) {
            $count = $catalog->countInCategory($this->category, $filters->without($chip['filter'], $chip['brand']));

            if ($count > 0) {
                $suggestions[] = $chip + ['count' => $count];
            }
        }

        return $suggestions;
    }

    /**
     * The address of the category page in a given state: the link works without scripts
     * and is the one the customer may share.
     *
     * @param  array<string, mixed>  $extra
     */
    private function urlFor(CatalogFilters $state, array $extra = []): string
    {
        return route('category', ['category' => $this->category] + $state->toQuery() + $extra);
    }

    private function startOver(): void
    {
        $this->page = 1;
        $this->pages = 1;
    }

    private function rememberView(string $view): void
    {
        $this->view = $view;
        Cookie::queue(self::VIEW_COOKIE, $view, 60 * 24 * 365);
    }
}
