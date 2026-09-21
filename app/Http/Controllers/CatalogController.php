<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Catalog\CatalogQuery;
use Illuminate\Contracts\View\View;
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

    public function show(Category $category, CatalogQuery $catalog): View
    {
        if (! $category->is_active) {
            throw new NotFoundHttpException;
        }

        return view('catalog.show', [
            'category' => $category,
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
