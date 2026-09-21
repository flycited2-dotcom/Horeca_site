<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogQuery;
use App\Services\Pricing\PriceResolver;
use App\Services\Settings\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Главная (ТЗ §8.1, макет — экран 4): плитки корневых разделов вместо баннеров, «Знаю
 * артикул» и ленты «В наличии», «Часто заказывают», «Новинки» и местного склада. Пустая
 * лента не показывается.
 */
class HomeController extends Controller
{
    private const int STRIP = 4;

    public function __invoke(Request $request, CatalogQuery $catalog, PriceResolver $prices, Settings $settings): View
    {
        $user = $request->user();
        $warehouse = (string) $settings->get('catalog.local_warehouse_name', '');

        $strips = array_filter([
            'in_stock' => $catalog->inStockStrip($user, self::STRIP),
            'local' => $warehouse === ''
                ? null
                : $catalog->localStockStrip($user, $warehouse, $settings->integer('catalog.local_strip_min_products', 12), self::STRIP),
            'hits' => $catalog->hitsStrip($user, self::STRIP),
            'new' => $catalog->newStrip($user, self::STRIP),
        ], fn ($strip) => $strip !== null && $strip->isNotEmpty());

        return view('home.index', [
            'home' => $catalog->homeSections(),
            'sectionsTotal' => count($catalog->navigationCategories()),
            'strips' => $strips,
            'warehouse' => $warehouse,
            'prices' => $prices->forMany(collect($strips)->flatten(1), $user),
        ]);
    }
}
