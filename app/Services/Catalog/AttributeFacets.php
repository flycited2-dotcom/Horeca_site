<?php

namespace App\Services\Catalog;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Characteristics in the filter panel of a listing (TZ §8.2). A characteristic the manager
 * marked as a filter is shown where it means something: it is known for at least a quarter
 * of the products on the page and they differ in it. At most eight, in the manager's order;
 * one the customer has chosen always stays, so it can be taken off.
 *
 * Text values are counted under the other filters, the characteristic's own choice aside —
 * a count never promises an empty list. A number shows the smallest and largest value of
 * the page as hints for «от — до». Yes/no characteristics are not shown: the supplier has none.
 */
final class AttributeFacets
{
    public const int MAX_SHOWN = 8;

    public const int MIN_SHARE_PERCENT = 25;

    /**
     * Values that stand for "no value" in older imports.
     */
    private const array BLANK = ['', '-', '—', '–'];

    public function __construct(private readonly CatalogQuery $catalog) {}

    /**
     * @param  Builder<Product>  $scope  products of the page before the customer's filters
     * @return list<AttributeFacet>
     */
    public function for(Builder $scope, CatalogFilters $filters): array
    {
        $attributes = $this->catalog->filterableAttributes()
            ->whereIn('type', [AttributeType::Number, AttributeType::Text])
            ->values();

        if ($attributes->isEmpty()) {
            return [];
        }

        $total = (clone $scope)->toBase()->reorder()->count();
        $coverage = $this->values($attributes->modelKeys(), $scope)
            ->groupBy('attribute_id')
            ->selectRaw('attribute_id, COUNT(*) AS products, COUNT(DISTINCT value_string) AS strings, MIN(value_number) AS min_number, MAX(value_number) AS max_number')
            ->get()
            ->keyBy('attribute_id');

        $shown = $this->shown($attributes, $coverage, $filters, max(2, (int) ceil($total * self::MIN_SHARE_PERCENT / 100)));
        $counts = $this->textCounts($shown->where('type', AttributeType::Text), $scope, $filters);

        return $shown->map(function (Attribute $attribute) use ($coverage, $counts, $filters): AttributeFacet {
            $condition = $filters->attributes[$attribute->slug] ?? [];
            $row = $coverage->get($attribute->id);

            return $attribute->type === AttributeType::Number
                ? new AttributeFacet($attribute->slug, $attribute->name, $attribute->unit, $attribute->type, min: $row?->min_number, max: $row?->max_number, condition: $condition)
                : new AttributeFacet($attribute->slug, $attribute->name, $attribute->unit, $attribute->type, options: self::options($counts->get($attribute->id, []), $condition['values'] ?? []), condition: $condition);
        })->values()->all();
    }

    /**
     * @param  Collection<int, Attribute>  $attributes
     * @param  Collection<int|string, object>  $coverage
     * @return Collection<int, Attribute>
     */
    private function shown(Collection $attributes, Collection $coverage, CatalogFilters $filters, int $needed): Collection
    {
        $others = 0;

        return $attributes->filter(function (Attribute $attribute) use ($coverage, $filters, $needed, &$others): bool {
            if (isset($filters->attributes[$attribute->slug])) {
                return true;
            }

            $row = $coverage->get($attribute->id);
            $differs = $row !== null && ($attribute->type === AttributeType::Number
                ? $row->min_number !== null && $row->min_number !== $row->max_number
                : (int) $row->strings > 1);

            if (! $differs || (int) $row->products < $needed || $others >= self::MAX_SHOWN) {
                return false;
            }

            $others++;

            return true;
        })->values();
    }

    /**
     * Products per value of each text characteristic. The ones nobody has chosen share one
     * query under all the filters; a chosen one is counted without its own choice.
     *
     * @param  Collection<int, Attribute>  $attributes
     * @param  Builder<Product>  $scope
     * @return Collection<int|string, array<string, int>> attribute id => value => products
     */
    private function textCounts(Collection $attributes, Builder $scope, CatalogFilters $filters): Collection
    {
        [$chosen, $plain] = $attributes->partition(fn (Attribute $attribute): bool => isset($filters->attributes[$attribute->slug]));
        $rows = collect();

        if ($plain->isNotEmpty()) {
            $rows = $rows->concat($this->valueCounts($plain->modelKeys(), $this->catalog->filtered(clone $scope, $filters)));
        }

        foreach ($chosen as $attribute) {
            $rows = $rows->concat($this->valueCounts([$attribute->id], $this->catalog->filtered(clone $scope, $filters->without('attr', $attribute->slug))));
        }

        return $rows->groupBy('attribute_id')->map(fn (Collection $values): array => $values->mapWithKeys(
            fn (object $row): array => [(string) $row->value_string => (int) $row->aggregate],
        )->all());
    }

    /**
     * @param  list<int>  $ids
     * @param  Builder<Product>  $products
     * @return Collection<int, object>
     */
    private function valueCounts(array $ids, Builder $products): Collection
    {
        return $this->values($ids, $products)
            ->whereNotNull('value_string')
            ->groupBy('attribute_id', 'value_string')
            ->selectRaw('attribute_id, value_string, COUNT(*) AS aggregate')
            ->get();
    }

    /**
     * Known values of the given characteristics among the given products.
     *
     * @param  list<int>  $ids
     * @param  Builder<Product>  $products
     */
    private function values(array $ids, Builder $products): QueryBuilder
    {
        return DB::table('attribute_product')
            ->whereIn('attribute_id', $ids)
            ->whereIn('product_id', (clone $products)->toBase()->reorder()->select('products.id'))
            ->where(fn (QueryBuilder $query) => $query->whereNotNull('value_number')->orWhereNotIn('value_string', self::BLANK));
    }

    /**
     * Values in natural order — «2, 4, 6», «220В, 380В» — with a chosen value kept even when
     * the other filters leave nothing with it, so it can be unticked.
     *
     * @param  array<string, int>  $counts
     * @param  list<string>  $selected
     * @return list<array{value: string, count: int, selected: bool}>
     */
    private static function options(array $counts, array $selected): array
    {
        foreach ($selected as $value) {
            $counts[$value] ??= 0;
        }

        uksort($counts, fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return array_map(
            fn (string $value, int $count): array => ['value' => $value, 'count' => $count, 'selected' => in_array($value, $selected, true)],
            array_keys($counts),
            array_values($counts),
        );
    }
}
