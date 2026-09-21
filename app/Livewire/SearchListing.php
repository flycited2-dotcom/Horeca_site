<?php

namespace App\Livewire;

use App\Livewire\Concerns\FiltersListing;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Services\Catalog\CategoryTree;
use App\Services\Pricing\PriceResolver;
use App\Services\Search\ProductSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Страница поиска (ТЗ §8.4, макет — экран 8): точное совпадение по артикулу отдельной
 * карточкой, «Уточнить:» по разделам, те же фильтры, что в листинге, и список строк,
 * а не сетка: в строку помещается то, чем близкие модели отличаются. Без скриптов
 * работают те же ссылки и GET-форма. Выборки — в ProductSearch и CatalogQuery.
 */
final class SearchListing extends Component
{
    use FiltersListing {
        removeFilter as private removeListingFilter;
        resetFilters as private resetListingFilters;
    }

    /**
     * Sections offered under «Уточнить:».
     */
    public const int REFINE_CATEGORIES = 5;

    #[Locked]
    public string $query = '';

    /**
     * «Уточнить: Пароконвектоматы» narrows the results to a section and its subsections.
     */
    #[Url(as: 'category', history: true, except: '')]
    public string $category = '';

    public function mount(string $query): void
    {
        $this->query = mb_substr(trim($query), 0, 200);
    }

    public function removeFilter(string $filter, ?string $brand = null): void
    {
        if ($filter === 'category') {
            $this->category = '';
            $this->startOver();

            return;
        }

        $this->removeListingFilter($filter, $brand);
    }

    public function resetFilters(): void
    {
        $this->category = '';
        $this->resetListingFilters();
    }

    public function render(ProductSearch $search, CatalogQuery $catalog, PriceResolver $prices, CategoryTree $tree): View
    {
        $scope = $search->scope($this->query);

        if ($scope === null) {
            return view('livewire.search-listing', ['tooShort' => true]);
        }

        $user = request()->user();
        $filters = $this->filters();
        $total = $scope->total();

        if ($total === 0) {
            return view('livewire.search-listing', [
                'tooShort' => false,
                'total' => 0,
                'popular' => collect($catalog->navigationCategories())->sortByDesc('products_count')->take(6)->values()->all(),
            ]);
        }

        $section = $this->category === ''
            ? null
            : Category::query()->active()->where('slug', $this->category)->first(['id', 'name', 'slug']);

        // Counts and facets go by the ids of the matches ($base), the list keeps the relevance
        // order of the search query ($list).
        $base = $scope->matching();
        $list = $scope->products();

        if ($section !== null) {
            $branch = $tree->activeBranch($section->id);
            $base->whereIn('category_id', $branch);
            $list->whereIn('category_id', $branch);
        }

        $exact = $search->exact($scope, $user);
        $rest = $exact === null ? clone $base : (clone $base)->whereKeyNot($exact->id);

        $ordered = $catalog->filtered($exact === null ? $list : $list->whereKeyNot($exact->id), $filters);

        // Relevance is the default order of search; a chosen sort replaces it.
        if ($filters->sort !== CatalogSort::Popular) {
            $ordered = $catalog->sorted($ordered->reorder(), $filters->sort);
        }

        $slice = $catalog->slice($ordered, $user, $this->page, $this->pages);
        $brands = $catalog->brandFacet($catalog->filtered(clone $base, $filters->without('brand')));
        $chips = $this->chips($filters, $brands);

        if ($section !== null) {
            array_unshift($chips, [
                'label' => $section->name,
                'filter' => 'category',
                'brand' => null,
                'url' => $this->urlFor($filters, ['category' => '']),
            ]);
        }

        $listed = $exact === null ? $slice->products : $slice->products->concat([$exact]);

        return view('livewire.search-listing', [
            'tooShort' => false,
            'total' => $total,
            'scope' => $scope,
            'exact' => $exact,
            'filters' => $filters,
            'slice' => $slice,
            'prices' => $prices->forMany($listed, $user),
            'totalInStock' => $catalog->inStockCount($scope->matching()),
            'categoriesCount' => $scope->matching()->toBase()->distinct()->count('category_id'),
            'refine' => $catalog->categoryFacet($scope->matching(), self::REFINE_CATEGORIES),
            'section' => $section,
            'brandOptions' => $brands,
            'inStockCount' => $catalog->inStockCount($catalog->filtered(clone $base, $filters->without('in_stock'))),
            'priceRange' => $catalog->priceRange($base),
            'chips' => $chips,
            'suggestions' => $slice->total === 0 ? $this->searchSuggestions($catalog, $scope->matching(), $rest, $exact?->id, $filters, $chips) : [],
            'sorts' => CatalogSort::cases(),
            'brandQuery' => $this->brandQuery,
            'allBrands' => $this->allBrands,
            'urlFor' => fn (CatalogFilters $state, array $extra = []): string => $this->urlFor($state, $extra),
        ]);
    }

    /**
     * What to drop when the filters leave nothing: a filter, as in the listing, or the
     * chosen section — the section is not one of CatalogFilters, so it is counted here.
     *
     * @param  Builder<Product>  $everything  all matches, without the section
     * @param  Builder<Product>  $rest  matches of the section, without the exact one
     * @param  list<array{label: string, filter: string, brand: ?string, url: string}>  $chips
     * @return list<array{label: string, filter: string, brand: ?string, url: string, count: int}>
     */
    private function searchSuggestions(CatalogQuery $catalog, Builder $everything, Builder $rest, ?int $exactId, CatalogFilters $filters, array $chips): array
    {
        $filterChips = array_values(array_filter($chips, fn (array $chip): bool => $chip['filter'] !== 'category'));
        $suggestions = $this->suggestions(fn (CatalogFilters $state): int => $catalog->filtered(clone $rest, $state)->count(), $filters, $filterChips);

        if ($filterChips !== $chips) {
            $everywhere = $catalog->filtered($exactId === null ? $everything : $everything->whereKeyNot($exactId), $filters)->count();

            if ($everywhere > 0) {
                array_unshift($suggestions, $chips[0] + ['count' => $everywhere]);
            }
        }

        return $suggestions;
    }

    /**
     * The search address in a given state; the query and the chosen section stay unless
     * $extra drops them with an empty value.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function urlFor(CatalogFilters $state, array $extra = []): string
    {
        $parameters = $extra + $state->toQuery() + ['q' => $this->query, 'category' => $this->category];

        return route('search', array_filter($parameters, fn (mixed $value): bool => $value !== '' && $value !== null));
    }
}
