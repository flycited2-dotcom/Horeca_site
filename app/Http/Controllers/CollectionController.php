<?php

namespace App\Http\Controllers;

use App\Models\ProductCollection;
use App\Services\Catalog\CatalogQuery;
use App\Services\Pricing\PriceResolver;
use App\Services\Seo\MetaBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Подборка «Соберём кухню под задачу» (ТЗ §8.1): описание и товары витрины в порядке,
 * который задал менеджер. Выключенная подборка не открывается.
 */
final class CollectionController extends Controller
{
    public function __invoke(Request $request, ProductCollection $collection, CatalogQuery $catalog, PriceResolver $prices, MetaBuilder $meta): View
    {
        if (! $collection->is_active) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        $products = $catalog->collectionProducts($collection, $user);

        return view('collections.show', [
            'collection' => $collection,
            'products' => $products,
            'prices' => $prices->forMany($products, $user),
            'meta' => $meta->collection($collection),
        ]);
    }
}
