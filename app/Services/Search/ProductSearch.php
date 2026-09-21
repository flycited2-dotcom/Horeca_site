<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Search of the storefront (TZ §8.4): the instant results under the header field and the
 * products of the /search page, which the listing filters then narrow. Discontinued
 * products never show up. When a query finds nothing, it is tried again in the other
 * keyboard layout.
 */
final class ProductSearch
{
    public const int INSTANT_PRODUCTS = 6;

    public const int INSTANT_CATEGORIES = 3;

    public function __construct(
        private readonly QueryNormalizer $normalizer,
        private readonly SearchEngineInterface $engine,
        private readonly CatalogQuery $catalog,
    ) {}

    public function instant(string $text, ?User $user): SearchResult
    {
        return $this->firstFound($text, fn (NormalizedQuery $query): SearchResult => new SearchResult(
            new Collection,
            new Collection,
            $query->text,
        ), function (NormalizedQuery $query) use ($user): SearchResult {
            $products = $this->engine
                ->apply($this->catalog->withCardData($this->catalog->listed(), $user), $query)
                ->limit(self::INSTANT_PRODUCTS)
                ->get();

            // «Показать все N результатов»: counted only when the first six are not all there is.
            $total = $products->count() < self::INSTANT_PRODUCTS
                ? $products->count()
                : $this->engine->apply($this->catalog->listed(), $query)->count();

            return new SearchResult($products, $this->categories($query), $query->text, total: $total);
        });
    }

    /**
     * The products the /search page works with: the query as typed or, when that finds
     * nothing, in the other keyboard layout. Null for a query too short to search for.
     */
    public function scope(string $text): ?SearchScope
    {
        $query = $this->normalizer->normalize($text);

        if (! $query->isSearchable()) {
            return null;
        }

        $scope = $this->scopeOf($query);

        if ($scope->total() > 0) {
            return $scope;
        }

        $switched = $this->normalizer->normalize($this->normalizer->switchLayout($text));

        if ($switched->text === $query->text || ! $switched->isSearchable()) {
            return $scope;
        }

        $retry = $this->scopeOf($switched, layoutSwitched: true);

        return $retry->total() > 0 ? $retry : $scope;
    }

    /**
     * The product whose article, 1C code or model is exactly the query (layout — screen 8),
     * when there is exactly one: an article from somebody's order has one answer.
     */
    public function exact(SearchScope $scope, ?User $user): ?Product
    {
        $found = $this->engine
            ->exact($this->catalog->withCardData($scope->matching(), $user), $scope->query)
            ->limit(2)
            ->get();

        return $found->count() === 1 ? $found->first() : null;
    }

    private function scopeOf(NormalizedQuery $query, bool $layoutSwitched = false): SearchScope
    {
        $products = $this->engine->apply($this->catalog->listed(), $query);

        return new SearchScope(
            $products,
            $query,
            (clone $products)->pluck('products.id')->map(fn (mixed $id): int => (int) $id)->all(),
            $layoutSwitched,
        );
    }

    /**
     * Runs the search as typed and, when it finds nothing, in the other keyboard layout.
     * A query too short to search for gives the same shape of result, only empty.
     *
     * @param  callable(NormalizedQuery): SearchResult  $empty
     * @param  callable(NormalizedQuery): SearchResult  $search
     */
    private function firstFound(string $text, callable $empty, callable $search): SearchResult
    {
        $query = $this->normalizer->normalize($text);

        if (! $query->isSearchable()) {
            return $empty($query);
        }

        $result = $search($query);

        if (! $result->isEmpty()) {
            return $result;
        }

        $switched = $this->normalizer->normalize($this->normalizer->switchLayout($text));

        if ($switched->text === $query->text || ! $switched->isSearchable()) {
            return $result;
        }

        $retry = $search($switched);

        return $retry->isEmpty()
            ? $result
            : new SearchResult($retry->products, $retry->categories, $retry->query, layoutSwitched: true, total: $retry->total);
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(NormalizedQuery $query): Collection
    {
        return Category::query()
            ->active()
            ->where(function (Builder $where) use ($query): void {
                foreach ($query->words as $word) {
                    $where->whereRaw("REPLACE(LOWER(name), 'ё', 'е') LIKE ?", ['%'.addcslashes($word, '\%_').'%']);
                }
            })
            ->orderByDesc('products_count')
            ->limit(self::INSTANT_CATEGORIES)
            ->get(['id', 'name', 'slug', 'products_count']);
    }
}
