<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CategoryImages;
use App\Services\Pricing\PriceResolver;
use App\Services\Settings\Settings;
use App\View\HomeCatalog;
use App\View\HomeHero;
use App\View\HomeShelf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Главная (ТЗ §8.1, облик «Свечение»): первый экран с цифрами каталога, плитки всех корневых разделов,
 * гармошка «В наличии», ленты местного склада, хитов и новинок, подборки и бренды. Пустая
 * лента не показывается. Строка поиска по артикулу живёт в самой странице, а не в подвале.
 */
class HomeController extends Controller
{
    private const int STRIP = 4;

    /**
     * Моделей в гармошке «В наличии»: шесть колонок — больше не умещаются в ряд, даже на широком экране.
     */
    private const int IN_STOCK = 6;

    /**
     * Brands listed on the home page: two rows of tiles; the rest are one click away on «Бренды».
     */
    private const int BRANDS = 10;

    public function __invoke(Request $request, CatalogQuery $catalog, CategoryImages $images, PriceResolver $prices, Settings $settings): View
    {
        $user = $request->user();
        $warehouse = (string) $settings->get('catalog.local_warehouse_name', '');
        $home = $catalog->homeSections();

        $strips = array_filter([
            'in_stock' => $catalog->inStockStrip($user, self::IN_STOCK),
            'local' => $warehouse === ''
                ? null
                : $catalog->localStockStrip($user, $warehouse, $settings->integer('catalog.local_strip_min_products', 12), self::STRIP),
            'hits' => $catalog->hitsStrip($user, self::STRIP),
            'new' => $catalog->newStrip($user, self::STRIP),
        ], fn (?Collection $strip): bool => $strip !== null && $strip->isNotEmpty());

        $resolved = $prices->forMany(collect($strips)->flatten(1), $user);

        return view('home.index', [
            'hero' => HomeHero::from($home, $resolved),
            'sections' => HomeCatalog::from($home['sections']),
            'images' => $images->for(array_column($home['sections'], 'id')),
            'shelves' => array_map(fn (Collection $strip): HomeShelf => HomeShelf::from($strip, $resolved), $strips),
            'collections' => $catalog->homeCollections(),
            'warehouse' => $warehouse,
            'brands' => $catalog->leadingBrands(self::BRANDS),
            'brandsTotal' => count($catalog->brandDirectory()),
        ]);
    }
}
