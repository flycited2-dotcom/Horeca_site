<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use App\Services\Analytics\Metrika;
use App\Services\Catalog\CatalogQuery;
use App\Services\Pricing\PriceResolver;
use App\Services\Seo\MetaBuilder;
use App\Services\Settings\Settings;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Карточка товара (ТЗ §8.3, макет — экран 3). Снятый с производства товар страницу
 * сохраняет — она нужна поиску и подбору аналога (ТЗ §6.5).
 */
class ProductController extends Controller
{
    /**
     * Pages the «Доставка и оплата» and «Гарантия» tabs link to, when the manager has switched them on.
     */
    private const array INFO_PAGES = ['dostavka', 'oplata', 'garantiya'];

    public function __invoke(Request $request, Product $product, CatalogQuery $catalog, PriceResolver $prices, Settings $settings, MetaBuilder $meta): View
    {
        if (! $product->is_visible) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        $product->load(['brand', 'category', 'media', 'attributeValues']);

        $price = $prices->for($product, $user);
        $similar = $catalog->similarProducts($product, $user);
        $related = $catalog->relatedProducts($product, $user);

        $pages = Page::query()
            ->where('is_active', true)
            ->whereIn('slug', self::INFO_PAGES)
            ->get(['slug', 'title'])
            ->keyBy('slug');

        $pickup = $settings->get('pickup.address');

        return view('product.show', [
            'product' => $product,
            'meta' => $meta->product($product),
            'analytics' => [Metrika::detail($product, $price)],
            'price' => $price,
            'wholesalePending' => (bool) $user?->hasPendingCompany(),
            'stocks' => $catalog->visibleStocks($product),
            'similar' => $similar,
            'related' => $related,
            'prices' => $prices->forMany($similar->concat($related), $user),
            'pickup' => is_string($pickup) && trim($pickup) !== '' ? trim($pickup) : null,
            'pages' => $pages,
            'structuredData' => StructuredData::product(
                $product,
                $price,
                $product->getFirstMediaUrl(Product::IMAGES, 'full') ?: null,
            ),
        ]);
    }
}
