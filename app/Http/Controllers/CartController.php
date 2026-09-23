<?php

namespace App\Http\Controllers;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ChangeCart;
use App\Http\Requests\Cart\CartQuantityRequest;
use App\Models\Product;
use App\Services\Analytics\Metrika;
use App\Services\Cart\CannotAddToCart;
use App\Services\Cart\CartReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Корзина (ТЗ §10.1, макет — экран 6). Кнопки «В корзину» и формы страницы корзины —
 * обычные формы: без скриптов страница возвращается с уведомлением, скрипт витрины
 * и Livewire делают то же без перезагрузки. Правила — в действиях корзины.
 */
class CartController extends Controller
{
    public function index(): View
    {
        return view('cart.index');
    }

    public function store(CartQuantityRequest $request, Product $product, AddToCart $add, CartReview $review): JsonResponse|RedirectResponse
    {
        try {
            $item = $add->handle($product, max(1, $request->quantity()), $request->user());
        } catch (CannotAddToCart $exception) {
            $notice = ['text' => $exception->getMessage()];

            return $request->expectsJson()
                ? response()->json(['notice' => $notice], 422)
                : redirect()->back(fallback: route('product', $product))->with('notice', $notice);
        }

        $notice = [
            'text' => __('shop.cart.added', ['name' => Str::limit($product->name, 60), 'quantity' => $item->qty, 'unit' => $product->unit]),
            'href' => route('cart'),
            'link' => __('shop.cart.open'),
        ];

        $metrika = Metrika::addToCart($product, $item->price, max(1, $request->quantity()));

        if ($request->expectsJson()) {
            return response()->json([
                'product' => $product->id,
                'quantity' => $item->qty,
                'headline' => $review->headline($request->user())->toArray(),
                'notice' => $notice,
                'metrika' => [$metrika],
            ]);
        }

        Metrika::flash($metrika);

        return redirect()->back(fallback: route('cart'))->with('notice', $notice);
    }

    /**
     * The quantity of a line on the cart page without scripts; zero removes it.
     */
    public function update(CartQuantityRequest $request, Product $product, ChangeCart $cart): RedirectResponse
    {
        if ($request->quantity() === 0) {
            return $this->destroy($request, $product, $cart);
        }

        $cart->setQuantity($product->id, $request->quantity(), $request->user());

        return redirect()->route('cart');
    }

    public function destroy(Request $request, Product $product, ChangeCart $cart): RedirectResponse
    {
        $removed = $cart->remove($product->id, $request->user());

        return redirect()->route('cart')->with('cart_removed', $removed === null ? null : $removed + ['name' => $product->name]);
    }

    /**
     * «Вернуть» on the cart page without scripts: the product goes back at today's price.
     */
    public function restore(CartQuantityRequest $request, Product $product, AddToCart $add): RedirectResponse
    {
        try {
            $add->handle($product, max(1, $request->quantity()), $request->user());
        } catch (CannotAddToCart $exception) {
            return redirect()->route('cart')->with('notice', ['text' => $exception->getMessage()]);
        }

        return redirect()->route('cart');
    }

    public function clear(Request $request, ChangeCart $cart): RedirectResponse
    {
        $cart->clear($request->user());

        return redirect()->route('cart')->with('notice', ['text' => __('shop.cart.cleared')]);
    }
}
