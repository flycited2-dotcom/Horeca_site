<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogQuery;
use App\Services\Favorites\FavoriteList;
use App\Services\Pricing\PriceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Избранное (ТЗ §8: /favorites, §11). Кнопки «В избранное» — обычные формы, как у
 * сравнения: без скриптов страница возвращается назад с уведомлением, скрипт витрины
 * получает JSON и меняет кнопку на месте. Вошедшему клиенту страница показывается
 * вкладкой кабинета.
 */
final class FavoriteController extends Controller
{
    public function index(Request $request, FavoriteList $favorites, CatalogQuery $catalog, PriceResolver $prices): View
    {
        /** @var User|null $user */
        $user = $request->user();
        $products = $catalog->favoriteProducts($favorites->ids($user), $user);
        $favorites->keepOnly($products->modelKeys(), $user);

        return view('favorites.index', [
            'user' => $user,
            'company' => $user?->company()->first(['id', 'legal_name', 'inn', 'status']),
            'products' => $products,
            'prices' => $prices->forMany($products, $user),
        ]);
    }

    public function store(Request $request, Product $product, FavoriteList $favorites): JsonResponse|RedirectResponse
    {
        if (! $product->is_visible) {
            throw new NotFoundHttpException;
        }

        $favorites->add($product->id, $request->user());

        return $this->respond($request, $favorites, $product, [
            'text' => __('shop.favorites.added', ['name' => Str::limit($product->name, 60)]),
            'href' => route('favorites'),
            'link' => __('shop.favorites.open'),
        ]);
    }

    public function destroy(Request $request, Product $product, FavoriteList $favorites): JsonResponse|RedirectResponse
    {
        $favorites->remove($product->id, $request->user());

        return $this->respond($request, $favorites, $product, [
            'text' => __('shop.favorites.removed', ['name' => Str::limit($product->name, 60)]),
        ]);
    }

    /**
     * @param  array{text: string, href?: string, link?: string}  $notice
     */
    private function respond(Request $request, FavoriteList $favorites, Product $product, array $notice): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($request->expectsJson()) {
            return response()->json([
                'product' => $product->id,
                'favorite' => $favorites->contains($product->id, $user),
                'count' => $favorites->count($user),
                'notice' => $notice,
            ]);
        }

        return redirect()->back(fallback: route('product', $product))->with('notice', $notice);
    }
}
