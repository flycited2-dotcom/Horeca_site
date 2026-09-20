<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Services\Pricing\PriceResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Каталог и листинг категории (ТЗ §8.2). Состояние фильтров живёт в адресе,
 * поэтому ссылку на выборку можно переслать.
 */
class CatalogController extends Controller
{
    public function index(CatalogQuery $catalog): View
    {
        return view('catalog.index', [
            'categories' => $catalog->rootCategoriesWithChildren(),
        ]);
    }

    public function show(Request $request, Category $category, CatalogQuery $catalog, PriceResolver $prices): View
    {
        if (! $category->is_active) {
            throw new NotFoundHttpException;
        }

        $filters = CatalogFilters::fromQuery($request->query());
        $scope = $catalog->inCategory($category);
        $products = $catalog->categoryProducts($category, $filters, $request->user(), (int) $request->query('page', 1));

        return view('catalog.show', [
            'category' => $category,
            'children' => $catalog->activeChildren($category),
            'filters' => $filters,
            'products' => $products,
            'prices' => $prices->forMany($products->getCollection(), $request->user()),
            'brands' => $catalog->brandFacet($scope),
            'priceRange' => $catalog->priceRange($scope),
            'sorts' => CatalogSort::cases(),
        ]);
    }
}
