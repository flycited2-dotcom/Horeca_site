<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the /search page works with (TZ §8.4): the products that match the query, before the
 * customer's filters. When the query only matched in the other keyboard layout, $query is
 * the switched one.
 *
 * The LIKE over search_text runs once for $ids; counts and facets go by those ids through
 * the primary key, and only the list itself repeats the LIKE for its relevance order.
 */
final readonly class SearchScope
{
    /**
     * @param  Builder<Product>  $products  matching products, ordered by relevance
     * @param  list<int>  $ids  the same products
     */
    public function __construct(
        public Builder $products,
        public NormalizedQuery $query,
        public array $ids,
        public bool $layoutSwitched = false,
    ) {}

    public function total(): int
    {
        return count($this->ids);
    }

    /**
     * A fresh copy of the relevance-ordered query, for the list itself.
     *
     * @return Builder<Product>
     */
    public function products(): Builder
    {
        return clone $this->products;
    }

    /**
     * The matching products by their ids, without the LIKE: for counts and facets.
     *
     * @return Builder<Product>
     */
    public function matching(): Builder
    {
        return Product::query()->whereKey($this->ids);
    }
}
