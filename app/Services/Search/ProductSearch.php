<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\User;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Search of the storefront (TZ §8.4): the instant results under the header field and the
 * /search page with the listing filters. Discontinued products never show up. When a
 * query finds nothing, it is tried again in the other keyboard layout.
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
        return $this->firstFound($text, function (NormalizedQuery $query) use ($user): SearchResult {
            $products = $this->engine
                ->apply($this->catalog->withCardData($this->catalog->listed(), $user), $query)
                ->limit(self::INSTANT_PRODUCTS)
                ->get();

            return new SearchResult($products, $this->categories($query), $query->text);
        });
    }

    public function page(string $text, CatalogFilters $filters, ?User $user, int $page = 1): SearchResult
    {
        return $this->firstFound($text, function (NormalizedQuery $query) use ($filters, $user, $page): SearchResult {
            $products = $this->engine->apply(
                $this->catalog->filtered($this->catalog->withCardData($this->catalog->listed(), $user), $filters),
                $query,
            );

            // Relevance is the default order of search; a chosen sort replaces it.
            if ($filters->sort !== CatalogSort::Popular) {
                $products = $this->catalog->sorted($products->reorder(), $filters->sort);
            }

            return new SearchResult(
                $products->paginate(CatalogFilters::PER_PAGE, page: max(1, $page)),
                new Collection,
                $query->text,
            );
        });
    }

    /**
     * Runs the search as typed and, when it finds nothing, in the other keyboard layout.
     *
     * @param  callable(NormalizedQuery): SearchResult  $search
     */
    private function firstFound(string $text, callable $search): SearchResult
    {
        $query = $this->normalizer->normalize($text);

        if (! $query->isSearchable()) {
            return new SearchResult(new Collection, new Collection, $query->text);
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
            : new SearchResult($retry->products, $retry->categories, $retry->query, layoutSwitched: true);
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
