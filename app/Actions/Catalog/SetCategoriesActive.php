<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Services\Catalog\CatalogCache;

/**
 * Switches storefront categories on or off in one go: after the first import all 324
 * supplier categories arrive switched off and the manager opens them in batches (TZ §12).
 */
final class SetCategoriesActive
{
    public function __construct(private readonly CatalogCache $cache) {}

    /**
     * @param  list<int>  $categoryIds
     * @return int categories whose state changed
     */
    public function handle(array $categoryIds, bool $active): int
    {
        $changed = Category::query()
            ->whereKey($categoryIds)
            ->where('is_active', '!=', $active)
            ->update(['is_active' => $active]);

        if ($changed > 0) {
            $this->cache->bump();
        }

        return $changed;
    }
}
