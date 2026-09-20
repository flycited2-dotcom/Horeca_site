<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogSort;
use App\Services\Pricing\PriceResolver;
use App\Services\Search\ProductSearch;
use App\Services\Search\QueryNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Страница поиска (ТЗ §8.4): те же фильтры, что в листинге, и подсказка,
 * если запрос нашёлся только в другой раскладке.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, ProductSearch $search, PriceResolver $prices): View
    {
        $query = trim((string) $request->query('q', ''));
        $filters = CatalogFilters::fromQuery($request->query());

        $result = $search->page($query, $filters, $request->user(), (int) $request->query('page', 1));

        return view('search.index', [
            'query' => $query,
            'tooShort' => mb_strlen($query) < QueryNormalizer::MIN_LENGTH,
            'filters' => $filters,
            'result' => $result,
            'prices' => $prices->forMany($result->products->getCollection(), $request->user()),
            'sorts' => CatalogSort::cases(),
        ]);
    }
}
