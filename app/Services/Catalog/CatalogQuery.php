<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only catalog queries for the storefront.
 */
final class CatalogQuery
{
    /**
     * Active root categories marked for the home page.
     *
     * @return Collection<int, Category>
     */
    public function homeRootCategories(): Collection
    {
        return Category::query()
            ->active()
            ->roots()
            ->where('show_on_home', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'products_count']);
    }
}
