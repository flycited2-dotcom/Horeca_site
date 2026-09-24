<?php

namespace App\Livewire;

use App\Livewire\Concerns\RefinesBySection;
use App\Services\Catalog\AttributeFacets;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Services\Pricing\PriceResolver;
use App\Services\Search\ProductSearch;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Страница поиска (ТЗ §8.4, макет — экран 8): точное совпадение по артикулу отдельной
 * карточкой, «Уточнить:» по разделам, те же фильтры, что в листинге, и список строк,
 * а не сетка: в строку помещается то, чем близкие модели отличаются. Без скриптов
 * работают те же ссылки и GET-форма. Выборки — в ProductSearch и CatalogQuery.
 */
final class SearchListing extends Component
{
    use RefinesBySection;

    /**
     * Sections offered under «Уточнить:».
     */
    public const int REFINE_CATEGORIES = 5;

    #[Locked]
    public string $query = '';

    public function mount(string $query): void
    {
        $this->query = mb_substr(trim($query), 0, 200);
    }

    public function render(ProductSearch $search, CatalogQuery $catalog, AttributeFacets $facets, PriceResolver $prices): View
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

        $section = $this->section();

        // Counts and facets go by the ids of the matches ($base), the list keeps the relevance
        // order of the search query ($list).
        $base = $scope->matching();
        $list = $scope->products();

        if ($section !== null) {
            $catalog->inBranch($base, $section);
            $catalog->inBranch($list, $section);
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
        $attributes = $facets->for($base, $filters);
        $chips = $this->withSectionChip($this->chips($filters, $brands, $attributes), $section, $filters);

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
            'attributeFacets' => $attributes,
            'inStockCount' => $catalog->inStockCount($catalog->filtered(clone $base, $filters->without('in_stock'))),
            'priceRange' => $catalog->priceRange($base),
            'chips' => $chips,
            'suggestions' => $slice->total === 0 ? $this->sectionSuggestions(
                fn (CatalogFilters $state): int => $catalog->filtered(clone $rest, $state)->count(),
                fn (CatalogFilters $state): int => $catalog->filtered($exact === null ? $scope->matching() : $scope->matching()->whereKeyNot($exact->id), $state)->count(),
                $filters,
                $chips,
            ) : [],
            'sorts' => CatalogSort::cases(),
            'brandQuery' => $this->brandQuery,
            'allBrands' => $this->allBrands,
            'urlFor' => fn (CatalogFilters $state, array $extra = []): string => $this->urlFor($state, $extra),
        ]);
    }

    /**
     * The search address in a given state; the query and the chosen section stay unless
     * $extra drops them with an empty value.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function urlFor(CatalogFilters $state, array $extra = []): string
    {
        return $this->sectionUrl('search', ['q' => $this->query], $state, $extra);
    }
}
