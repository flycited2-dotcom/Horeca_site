<?php

namespace App\Livewire\Concerns;

use App\Models\Attribute;
use App\Models\Brand;
use App\Services\Catalog\AttributeFacet;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Support\Typography;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;

/**
 * The state of a listing with the filters of TZ §8.2, shared by the category page and the
 * search page: it lives in the address (pushState), so a link to a selection can be sent.
 * The names in the address are the names of the GET form, so the page works without scripts.
 */
trait FiltersListing
{
    /**
     * Brands shown before «Ещё N брендов».
     */
    public const int BRANDS_SHOWN = 6;

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

    /**
     * Characteristics: slug => ['min' => …, 'max' => …] or ['values' => […]], as typed —
     * CatalogFilters cleans them, so a half-typed «0,» never empties the field.
     *
     * @var array<string, array<string, mixed>>
     */
    #[Url(as: 'attr', history: true, except: [])]
    public array $attr = [];

    #[Url(history: true, except: 'popular')]
    public string $sort = 'popular';

    #[Url(history: true, except: 1)]
    public int $page = 1;

    /**
     * Pages on screen: «Показать ещё» appends the next one without leaving the address.
     */
    public int $pages = 1;

    /**
     * Brand search inside the filter panel; works only with scripts, so it stays out of the address.
     */
    public string $brandQuery = '';

    /**
     * «Ещё N брендов» pressed: the whole brand list is shown.
     */
    public bool $allBrands = false;

    /**
     * The address of the page in a given state: the link works without scripts and is the
     * one the customer may share.
     *
     * @param  array<string, mixed>  $extra
     */
    abstract protected function urlFor(CatalogFilters $state, array $extra = []): string;

    /**
     * Any filter change starts the listing from its first page.
     */
    public function updated(string $property): void
    {
        if (in_array(strtok($property, '.'), ['priceFrom', 'priceTo', 'inStock', 'brands', 'attr', 'sort', 'category'], true)) {
            $this->startOver();
        }

        if (strtok($property, '.') === 'attr') {
            $this->attr = self::withoutBlanks($this->attr);
        }
    }

    public function sortBy(string $sort): void
    {
        $this->sort = (CatalogSort::tryFrom($sort) ?? CatalogSort::Popular)->value;
        $this->startOver();
    }

    public function removeFilter(string $filter, ?string $key = null): void
    {
        if ($filter === 'price') {
            $this->priceFrom = '';
            $this->priceTo = '';
        } elseif ($filter === 'in_stock') {
            $this->inStock = false;
        } elseif ($filter === 'brand') {
            $this->brands = array_values(array_diff($this->brands, [$key]));
        } elseif ($filter === 'attr') {
            $this->attr = $key === null ? [] : array_diff_key($this->attr, [$key => true]);
        }

        $this->startOver();
    }

    /**
     * A ticked or unticked value of a text characteristic. Not wire:model: before the first
     * tick there is no list to bind a checkbox to, and Livewire would store «true» instead.
     */
    public function toggleAttributeValue(string $slug, string $value): void
    {
        if (preg_match('/^[a-z0-9-]{1,160}$/', $slug) !== 1) {
            return;
        }

        $values = (array) ($this->attr[$slug]['values'] ?? []);
        $values = in_array($value, $values, true) ? array_values(array_diff($values, [$value])) : [...$values, $value];

        $this->attr = self::withoutBlanks([...$this->attr, $slug => ['values' => $values]]);
        $this->startOver();
    }

    public function resetFilters(): void
    {
        $this->priceFrom = '';
        $this->priceTo = '';
        $this->inStock = false;
        $this->brands = [];
        $this->attr = [];
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

    /**
     * The mobile sheet and the form without scripts: the filters are already applied live.
     */
    public function applyFilters(): void
    {
        $this->startOver();
    }

    /**
     * The state of the listing, cleaned the same way as a query string from the address.
     */
    protected function filters(): CatalogFilters
    {
        return CatalogFilters::fromQuery([
            'price_from' => $this->priceFrom,
            'price_to' => $this->priceTo,
            'in_stock' => $this->inStock ? '1' : null,
            'brand' => $this->brands,
            'attr' => $this->attr,
            'sort' => $this->sort,
        ]);
    }

    /**
     * Cleared fields and unticked lists leave the address: no «attr[…][min]=» in a shared link.
     *
     * @param  array<string, mixed>  $attr
     * @return array<string, array<string, mixed>>
     */
    private static function withoutBlanks(array $attr): array
    {
        $clean = [];

        foreach ($attr as $slug => $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $condition = array_filter($condition, fn (mixed $part): bool => is_array($part) ? $part !== [] : trim((string) $part) !== '');

            if ($condition !== []) {
                $clean[$slug] = $condition;
            }
        }

        return $clean;
    }

    /**
     * Applied filters as chips over the list, each with the address that drops it.
     *
     * @param  Collection<int, Brand>  $facet
     * @param  list<AttributeFacet>  $attributeFacets
     * @return list<array{label: string, filter: string, key: ?string, url: string}>
     */
    protected function chips(CatalogFilters $filters, Collection $facet, array $attributeFacets = []): array
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

        if ($filters->attributes !== []) {
            $known = collect($attributeFacets)->keyBy('slug');
            $missing = array_diff(array_keys($filters->attributes), $known->keys()->all());
            $stored = $missing === [] ? collect() : Attribute::query()->where('is_filterable', true)->whereIn('slug', $missing)->get(['name', 'slug', 'unit'])->keyBy('slug');

            foreach ($filters->attributes as $slug => $condition) {
                $attribute = $known->get($slug) ?? $stored->get($slug);

                // A characteristic that is no longer a filter does not narrow the list either.
                if ($attribute !== null) {
                    $chips[] = $this->chip(AttributeFacet::chipLabel($attribute->name, $attribute->unit, $condition), $filters, 'attr', $slug);
                }
            }
        }

        return $chips;
    }

    /**
     * An empty result says which filter to drop and what that gives (layout — screen 2).
     *
     * @param  callable(CatalogFilters): int  $count  products left under the given filters
     * @param  list<array{label: string, filter: string, key: ?string, url: string}>  $chips
     * @return list<array{label: string, filter: string, key: ?string, url: string, count: int}>
     */
    protected function suggestions(callable $count, CatalogFilters $filters, array $chips): array
    {
        $suggestions = [];

        foreach (array_slice($chips, 0, 4) as $chip) {
            $left = $count($filters->without($chip['filter'], $chip['key']));

            if ($left > 0) {
                $suggestions[] = $chip + ['count' => $left];
            }
        }

        return $suggestions;
    }

    /**
     * @return array{label: string, filter: string, key: ?string, url: string}
     */
    private function chip(string $label, CatalogFilters $filters, string $filter, ?string $key = null): array
    {
        return [
            'label' => $label,
            'filter' => $filter,
            'key' => $key,
            'url' => $this->urlFor($filters->without($filter, $key)),
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

    private function startOver(): void
    {
        $this->page = 1;
        $this->pages = 1;
    }
}
