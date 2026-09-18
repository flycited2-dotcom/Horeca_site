<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a search found. When the query only matched in the other keyboard layout,
 * $query holds the switched text, so the page can say what it actually searched for.
 */
final readonly class SearchResult
{
    /**
     * @param  Collection<int, Product>|LengthAwarePaginator<int, Product>  $products
     * @param  Collection<int, Category>  $categories
     */
    public function __construct(
        public Collection|LengthAwarePaginator $products,
        public Collection $categories,
        public string $query,
        public bool $layoutSwitched = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->products->isEmpty() && $this->categories->isEmpty();
    }
}
