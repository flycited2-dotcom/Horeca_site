<?php

namespace App\Services\Catalog;

/**
 * The state of a listing as it lives in the address bar (TZ §8.2): the filters survive
 * a reload and a shared link. Anything unexpected in the query string is dropped, never
 * turned into an error page.
 *
 * ?price_from=10000&price_to=50000&in_stock=1&brand[]=abat&attr[moshchnost-kvt][min]=3&sort=price_asc
 */
final readonly class CatalogFilters
{
    public const int PER_PAGE = 24;

    /**
     * @param  list<string>  $brands  brand slugs
     * @param  array<string, array{min?: string, max?: string, values?: list<string>}>  $attributes  attribute slug => condition
     */
    public function __construct(
        public ?int $priceFrom = null,
        public ?int $priceTo = null,
        public bool $inStockOnly = false,
        public array $brands = [],
        public array $attributes = [],
        public CatalogSort $sort = CatalogSort::Popular,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(array $query): self
    {
        return new self(
            priceFrom: self::rubles($query['price_from'] ?? null),
            priceTo: self::rubles($query['price_to'] ?? null),
            inStockOnly: in_array($query['in_stock'] ?? null, ['1', 1, true, 'true', 'on'], true),
            brands: self::slugs($query['brand'] ?? []),
            attributes: self::attributes($query['attr'] ?? []),
            sort: CatalogSort::tryFrom((string) ($query['sort'] ?? '')) ?? CatalogSort::Popular,
        );
    }

    /**
     * The same state with some filters dropped: facet counts ignore their own filter,
     * a chip removes one. Names: price, in_stock, brand, attr; a brand slug drops one brand.
     */
    public function without(string $filter, ?string $brand = null): self
    {
        return new self(
            priceFrom: $filter === 'price' ? null : $this->priceFrom,
            priceTo: $filter === 'price' ? null : $this->priceTo,
            inStockOnly: $filter === 'in_stock' ? false : $this->inStockOnly,
            brands: match (true) {
                $filter === 'brand' && $brand !== null => array_values(array_diff($this->brands, [$brand])),
                $filter === 'brand' => [],
                default => $this->brands,
            },
            attributes: $filter === 'attr' ? [] : $this->attributes,
            sort: $this->sort,
        );
    }

    public function withSort(CatalogSort $sort): self
    {
        return new self($this->priceFrom, $this->priceTo, $this->inStockOnly, $this->brands, $this->attributes, $sort);
    }

    /**
     * Only the sort order is kept: "Сбросить всё".
     */
    public function cleared(): self
    {
        return new self(sort: $this->sort);
    }

    /**
     * How many filters narrow the listing, for «Фильтры · N»: the price range counts once,
     * every brand counts on its own.
     */
    public function activeCount(): int
    {
        return (int) ($this->priceFrom !== null || $this->priceTo !== null)
            + (int) $this->inStockOnly
            + count($this->brands)
            + count($this->attributes);
    }

    public function isFiltered(): bool
    {
        return $this->activeCount() > 0;
    }

    /**
     * Back into a query string, without the defaults.
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'price_from' => $this->priceFrom,
            'price_to' => $this->priceTo,
            'in_stock' => $this->inStockOnly ? 1 : null,
            'brand' => $this->brands === [] ? null : $this->brands,
            'attr' => $this->attributes === [] ? null : $this->attributes,
            'sort' => $this->sort === CatalogSort::Popular ? null : $this->sort->value,
        ], fn (mixed $value): bool => $value !== null);
    }

    private static function rubles(mixed $value): ?int
    {
        $value = is_string($value) ? preg_replace('/[\s\x{00A0}]+/u', '', $value) : $value;

        if (! is_numeric($value) || (int) $value < 0 || (int) $value > 1_000_000_000) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    private static function slugs(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $slug): string => is_string($slug) ? trim($slug) : '', $values),
            fn (string $slug): bool => preg_match('/^[a-z0-9-]{1,160}$/', $slug) === 1,
        )));
    }

    /**
     * @return array<string, array{min?: string, max?: string, values?: list<string>}>
     */
    private static function attributes(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $conditions = [];

        foreach ($value as $slug => $condition) {
            if (! is_string($slug) || preg_match('/^[a-z0-9-]{1,160}$/', $slug) !== 1 || ! is_array($condition)) {
                continue;
            }

            $clean = [];

            foreach (['min', 'max'] as $bound) {
                if (isset($condition[$bound]) && is_numeric($condition[$bound])) {
                    $clean[$bound] = (string) $condition[$bound];
                }
            }

            if (isset($condition['values']) && is_array($condition['values'])) {
                $values = array_values(array_filter(
                    array_map(fn (mixed $item): string => is_scalar($item) ? mb_substr(trim((string) $item), 0, 255) : '', $condition['values']),
                    fn (string $item): bool => $item !== '',
                ));

                if ($values !== []) {
                    $clean['values'] = $values;
                }
            }

            if ($clean !== []) {
                $conditions[$slug] = $clean;
            }
        }

        return $conditions;
    }
}
