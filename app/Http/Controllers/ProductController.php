<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use App\Services\Pricing\PriceResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Карточка товара (ТЗ §8.3). Снятый с производства товар страницу сохраняет — она нужна
 * поиску и подбору аналога (ТЗ §6.5).
 */
class ProductController extends Controller
{
    public function __invoke(Request $request, Product $product, CatalogQuery $catalog, PriceResolver $prices): View
    {
        if (! $product->is_visible) {
            throw new NotFoundHttpException;
        }

        $product->load(['brand', 'category', 'media', 'stocks.warehouse', 'attributeValues']);

        $similar = $catalog->similarProducts($product, $request->user());

        return view('product.show', [
            'product' => $product,
            'price' => $prices->for($product, $request->user()),
            'stocks' => $catalog->visibleStocks($product),
            'similar' => $similar,
            'similarPrices' => $prices->forMany($similar, $request->user()),
        ]);
    }
}
