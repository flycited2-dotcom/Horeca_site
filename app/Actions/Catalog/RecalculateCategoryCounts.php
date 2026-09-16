<?php

namespace App\Actions\Catalog;

use App\Enums\Availability;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Recalculates categories.products_count: visible, not discontinued products
 * of the category and all of its descendants.
 */
final class RecalculateCategoryCounts
{
    public function handle(): void
    {
        /** @var Collection<int, int> $direct */
        $direct = Product::query()
            ->where('is_visible', true)
            ->where('availability', '!=', Availability::Discontinued)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'category_id')
            ->map(fn (mixed $count): int => (int) $count);

        $categories = Category::query()->get(['id', 'parent_id', 'products_count']);
        $childrenByParent = $categories->groupBy(fn (Category $category): int => (int) $category->parent_id);

        $totals = [];

        foreach ($categories as $category) {
            $total = $this->total($category, $direct, $childrenByParent, $totals, []);

            if ($category->products_count !== $total) {
                $category->products_count = $total;
                $category->save();
            }
        }
    }

    /**
     * @param  Collection<int, int>  $direct
     * @param  Collection<int, \Illuminate\Database\Eloquent\Collection<int, Category>>  $childrenByParent
     * @param  array<int, int>  $totals
     * @param  array<int, true>  $path
     */
    private function total(Category $category, Collection $direct, Collection $childrenByParent, array &$totals, array $path): int
    {
        if (isset($totals[$category->id])) {
            return $totals[$category->id];
        }

        if (isset($path[$category->id])) {
            throw new LogicException("Category tree has a cycle at category [{$category->id}].");
        }

        $path[$category->id] = true;
        $total = $direct->get($category->id, 0);

        foreach ($childrenByParent->get($category->id, []) as $child) {
            $total += $this->total($child, $direct, $childrenByParent, $totals, $path);
        }

        return $totals[$category->id] = $total;
    }
}
