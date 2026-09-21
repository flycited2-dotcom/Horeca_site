<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use App\Services\Compare\CompareList;
use App\Services\Compare\CompareResult;
use App\Services\Pricing\PriceResolver;
use App\View\ComparisonTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Сравнение моделей (ТЗ §8.5, макет — экран 13). Кнопки «Сравнить» — обычные формы:
 * без скриптов страница возвращается назад с уведомлением, скрипт витрины получает JSON
 * и меняет кнопку на месте.
 */
class CompareController extends Controller
{
    public function index(Request $request, CompareList $compare, CatalogQuery $catalog, PriceResolver $prices): View
    {
        $user = $request->user();
        $products = $catalog->comparedProducts($compare->ids($user), $user);
        $compare->keepOnly($products->modelKeys(), $user);

        $categories = $products->pluck('category_id')->unique();
        $table = ComparisonTable::of($products);

        return view('compare.index', [
            'products' => $products,
            'prices' => $prices->forMany($products, $user),
            'table' => $table,
            // With one model, or models alike in everything, there is nothing to tell apart: every row is shown.
            'all' => $request->boolean('all') || $products->count() < 2 || $table->differences() === 0,
            'category' => $categories->count() === 1 && $categories->first() !== null
                ? Category::query()->active()->find($categories->first())
                : null,
        ]);
    }

    public function store(Request $request, Product $product, CompareList $compare): JsonResponse|RedirectResponse
    {
        if (! $product->is_visible) {
            throw new NotFoundHttpException;
        }

        return $this->respond($request, $compare, $product, $compare->add($product->id, $request->user()));
    }

    public function destroy(Request $request, Product $product, CompareList $compare): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $compare, $product, $compare->remove($product->id, $request->user()));
    }

    public function clear(Request $request, CompareList $compare): RedirectResponse
    {
        $compare->clear($request->user());

        return redirect()->route('compare')->with('notice', ['text' => __('shop.compare.cleared')]);
    }

    private function respond(Request $request, CompareList $compare, Product $product, CompareResult $result): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $count = $compare->count($user);
        $name = Str::limit($product->name, 60);

        $notice = match ($result) {
            CompareResult::Added => [
                'text' => __('shop.compare.added', ['name' => $name, 'count' => $count, 'limit' => CompareList::LIMIT]),
                'href' => route('compare'),
                'link' => __('shop.compare.open'),
            ],
            CompareResult::Full => [
                'text' => __('shop.compare.full', ['limit' => CompareList::LIMIT]),
                'href' => route('compare'),
                'link' => __('shop.compare.open'),
            ],
            CompareResult::Removed => ['text' => __('shop.compare.removed', ['name' => $name])],
        };

        if ($request->expectsJson()) {
            return response()->json([
                'product' => $product->id,
                'compared' => $compare->contains($product->id, $user),
                'count' => $count,
                'notice' => $notice,
            ]);
        }

        return redirect()->back(fallback: route('product', $product))->with('notice', $notice);
    }
}
