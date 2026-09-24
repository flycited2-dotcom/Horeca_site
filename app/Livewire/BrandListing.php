<?php

namespace App\Livewire;

use App\Livewire\Concerns\ChoosesView;
use App\Livewire\Concerns\RefinesBySection;
use App\Models\Brand;
use App\Services\Catalog\AttributeFacets;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Services\Pricing\PriceResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Листинг бренда (ТЗ §8): те же фильтры, сортировка и вид, что в листинге категории, но без
 * фильтра по бренду — вместо него «Разделы:», в которых у бренда больше всего моделей.
 * Состояние живёт в адресе, без скриптов работают те же ссылки и GET-форма.
 */
final class BrandListing extends Component
{
    use ChoosesView;
    use RefinesBySection {
        filters as private listingFilters;
    }

    /**
     * Sections offered under «Разделы:».
     */
    public const int SECTIONS = 8;

    #[Locked]
    public Brand $brand;

    public function mount(Brand $brand): void
    {
        $this->brand = $brand;
        $this->chooseView();
    }

    public function render(CatalogQuery $catalog, AttributeFacets $facets, PriceResolver $prices): View
    {
        $user = request()->user();
        $filters = $this->filters();
        $section = $this->section();

        $scope = $catalog->ofBrand($this->brand);
        $base = $section === null ? clone $scope : $catalog->inBranch(clone $scope, $section);

        $slice = $catalog->slice($catalog->sorted($catalog->filtered(clone $base, $filters), $filters->sort), $user, $this->page, $this->pages);
        $attributes = $facets->for($base, $filters);
        $chips = $this->withSectionChip($this->chips($filters, new Collection, $attributes), $section, $filters);
        $narrowed = $filters->isFiltered() || $section !== null;

        return view('livewire.brand-listing', [
            'filters' => $filters,
            'section' => $section,
            'slice' => $slice,
            'prices' => $prices->forMany($slice->products, $user),
            'brandTotal' => $narrowed ? (clone $scope)->count() : $slice->total,
            'brandInStock' => $catalog->inStockCount($scope),
            'narrowed' => $narrowed,
            'sections' => $catalog->categoryFacet($scope, self::SECTIONS),
            'inStockCount' => $catalog->inStockCount($catalog->filtered(clone $base, $filters->without('in_stock'))),
            'priceRange' => $catalog->priceRange($base),
            'attributeFacets' => $attributes,
            'chips' => $chips,
            'suggestions' => $slice->total === 0 && $narrowed ? $this->sectionSuggestions(
                fn (CatalogFilters $state): int => $catalog->filtered(clone $base, $state)->count(),
                fn (CatalogFilters $state): int => $catalog->filtered(clone $scope, $state)->count(),
                $filters,
                $chips,
            ) : [],
            'sorts' => CatalogSort::cases(),
            'hidden' => ['category' => $this->category],
            'urlFor' => fn (CatalogFilters $state, array $extra = []): string => $this->urlFor($state, $extra),
        ]);
    }

    /**
     * The brand is fixed by the page: a brand from the address is ignored.
     */
    protected function filters(): CatalogFilters
    {
        return $this->listingFilters()->without('brand');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function urlFor(CatalogFilters $state, array $extra = []): string
    {
        return $this->sectionUrl('brand', ['brand' => $this->brand], $state, $extra);
    }
}
