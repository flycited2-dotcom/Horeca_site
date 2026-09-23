<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Seo\MetaBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Каталог и листинг категории (ТЗ §8.2). Листинг с фильтрами — компонент
 * App\Livewire\CategoryListing: состояние фильтров живёт в адресе, поэтому ссылку
 * на выборку можно переслать.
 */
class CatalogController extends Controller
{
    public function index(CatalogQuery $catalog): View
    {
        return view('catalog.index', [
            'categories' => $catalog->rootCategoriesWithChildren(),
        ]);
    }

    public function show(Request $request, Category $category, CatalogQuery $catalog, MetaBuilder $meta): View
    {
        if (! $category->is_active) {
            throw new NotFoundHttpException;
        }

        return view('catalog.show', [
            'category' => $category,
            'meta' => $meta->category($category, CatalogFilters::fromQuery($request->query()), max(1, $request->integer('page', 1))),
            'subcategories' => $catalog->activeChildren($category)
                ->map(fn (Category $child): array => [
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'products_count' => $child->products_count,
                ])
                ->all(),
        ]);
    }
}
