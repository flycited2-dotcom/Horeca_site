<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * A search engine narrows a product query and orders it by relevance (TZ §8.4).
 * The database engine is enough for 15 000 products; the interface stays for a
 * dedicated engine later.
 */
interface SearchEngineInterface
{
    /**
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public function apply(Builder $products, NormalizedQuery $query): Builder;
}
