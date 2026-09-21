<?php

namespace App\Livewire;

use App\Livewire\Concerns\FiltersListing;
use App\Models\Category;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Services\Pricing\PriceResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Листинг категории (ТЗ §8.2, макет — экраны 2, 10 и 14): фильтры применяются без
 * перезагрузки, состояние живёт в адресе (pushState), поэтому ссылкой на выборку можно
 * поделиться. Страница рисуется на сервере целиком: без скриптов работают те же ссылки
 * и GET-форма. Выборки — в CatalogQuery, здесь только состояние листинга.
 */
final class CategoryListing extends Component
{
    use FiltersListing;

    public const string VIEW_COOKIE = 'listing_view';

    public const array VIEWS = ['grid', 'list'];

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

    public string $view = 'grid';

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

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->rememberView($view);
        }
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
            'suggestions' => $slice->total === 0 && $filters->isFiltered()
                ? $this->suggestions(fn (CatalogFilters $state): int => $catalog->countInCategory($this->category, $state), $filters, $chips)
                : [],
            'sorts' => CatalogSort::cases(),
            'brandQuery' => $this->brandQuery,
            'allBrands' => $this->allBrands,
            'baseUrl' => route('category', $this->category),
            'urlFor' => fn (CatalogFilters $state, array $extra = []): string => $this->urlFor($state, $extra),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function urlFor(CatalogFilters $state, array $extra = []): string
    {
        return route('category', ['category' => $this->category] + $state->toQuery() + $extra);
    }

    private function rememberView(string $view): void
    {
        $this->view = $view;
        Cookie::queue(self::VIEW_COOKIE, $view, 60 * 24 * 365);
    }
}
