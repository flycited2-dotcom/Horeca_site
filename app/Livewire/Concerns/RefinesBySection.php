<?php

namespace App\Livewire\Concerns;

use App\Models\Category;
use App\Services\Catalog\CatalogFilters;
use Livewire\Attributes\Url;

/**
 * Листинг, который сужается до раздела каталога: «Уточнить: Пароконвектоматы» на странице
 * поиска, «Разделы:» на странице бренда. Раздел живёт в адресе рядом с фильтрами, снимается
 * своим чипом и «Сбросить всё».
 */
trait RefinesBySection
{
    use FiltersListing {
        removeFilter as private removeListingFilter;
        resetFilters as private resetListingFilters;
    }

    #[Url(as: 'category', history: true, except: '')]
    public string $category = '';

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

    /**
     * The chosen section, when it is switched on.
     */
    protected function section(): ?Category
    {
        return $this->category === ''
            ? null
            : Category::query()->active()->where('slug', $this->category)->first(['id', 'name', 'slug']);
    }

    /**
     * The chips of the filters with the chosen section in front: it narrows the most.
     *
     * @param  list<array{label: string, filter: string, brand: ?string, url: string}>  $chips
     * @return list<array{label: string, filter: string, brand: ?string, url: string}>
     */
    protected function withSectionChip(array $chips, ?Category $section, CatalogFilters $filters): array
    {
        if ($section === null) {
            return $chips;
        }

        return [[
            'label' => $section->name,
            'filter' => 'category',
            'brand' => null,
            'url' => $this->urlFor($filters, ['category' => '']),
        ], ...$chips];
    }

    /**
     * What to drop when nothing is left: a filter, as in the listing, or the section — it is
     * not one of CatalogFilters, so it is counted here.
     *
     * @param  callable(CatalogFilters): int  $inSection  products left in the section under the given filters
     * @param  callable(CatalogFilters): int  $everywhere  products left without the section
     * @param  list<array{label: string, filter: string, brand: ?string, url: string}>  $chips
     * @return list<array{label: string, filter: string, brand: ?string, url: string, count: int}>
     */
    protected function sectionSuggestions(callable $inSection, callable $everywhere, CatalogFilters $filters, array $chips): array
    {
        $filterChips = array_values(array_filter($chips, fn (array $chip): bool => $chip['filter'] !== 'category'));
        $suggestions = $this->suggestions($inSection, $filters, $filterChips);

        if ($filterChips !== $chips) {
            $left = $everywhere($filters);

            if ($left > 0) {
                array_unshift($suggestions, $chips[0] + ['count' => $left]);
            }
        }

        return $suggestions;
    }

    /**
     * The address of a listing route in a given state; the chosen section stays unless $extra
     * drops it with an empty value.
     *
     * @param  array<string, mixed>  $route  the route parameters of the page
     * @param  array<string, mixed>  $extra
     */
    protected function sectionUrl(string $name, array $route, CatalogFilters $state, array $extra = []): string
    {
        $parameters = $extra + $state->toQuery() + $route + ['category' => $this->category];

        return route($name, array_filter($parameters, fn (mixed $value): bool => $value !== '' && $value !== null));
    }
}
