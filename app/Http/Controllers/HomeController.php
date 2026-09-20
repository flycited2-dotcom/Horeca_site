<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogQuery;
use App\Services\Pricing\PriceResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Главная (ТЗ §8.1): панель подбора по корневым категориям и лента «В наличии».
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, CatalogQuery $catalog, PriceResolver $prices): View
    {
        $inStock = $catalog->inStockStrip($request->user());

        return view('home.index', [
            'categories' => $catalog->homeRootCategories(),
            'inStock' => $inStock,
            'prices' => $prices->forMany($inStock, $request->user()),
        ]);
    }
}
