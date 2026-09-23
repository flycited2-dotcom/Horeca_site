<?php

namespace App\Livewire;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ChangeCart;
use App\Models\Product;
use App\Services\Cart\CannotAddToCart;
use App\Services\Cart\CartReview;
use App\Services\Settings\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Страница корзины (ТЗ §10.1, макет — экран 6): количество ± и вручную, удаление
 * с «Вернуть» на 10 секунд, очистка, итог, вес, путь до бесплатной доставки, сверка цен
 * и позиций при каждом открытии. Без скриптов те же действия делают формы
 * CartController. Правила — в действиях корзины, здесь только состояние страницы.
 */
final class CartPage extends Component
{
    /**
     * The line just removed, for «Вернуть».
     *
     * @var array{product_id: int, qty: int, name: string}|null
     */
    public ?array $removed = null;

    /**
     * Why «Вернуть» did not bring the product back.
     */
    public ?string $problem = null;

    public function mount(): void
    {
        $removed = session('cart_removed');

        if (is_array($removed) && isset($removed['product_id'], $removed['qty'], $removed['name'])) {
            $this->removed = ['product_id' => (int) $removed['product_id'], 'qty' => (int) $removed['qty'], 'name' => (string) $removed['name']];
        }
    }

    public function setQuantity(int $productId, mixed $quantity, ChangeCart $cart): void
    {
        if (is_numeric($quantity) && (int) $quantity <= 0) {
            $this->remove($productId, $cart);

            return;
        }

        $cart->setQuantity($productId, $quantity, request()->user());
    }

    public function remove(int $productId, ChangeCart $cart): void
    {
        $removed = $cart->remove($productId, request()->user());

        if ($removed !== null) {
            $this->removed = $removed + ['name' => (string) Product::query()->whereKey($productId)->value('name')];
            $this->problem = null;
        }
    }

    public function restore(AddToCart $add): void
    {
        $product = $this->removed === null ? null : Product::query()->find($this->removed['product_id']);

        if ($product !== null) {
            try {
                $add->handle($product, $this->removed['qty'], request()->user());
            } catch (CannotAddToCart $exception) {
                $this->problem = $exception->getMessage();
            }
        }

        $this->removed = null;
    }

    public function forgetRemoved(): void
    {
        $this->removed = null;
    }

    public function clear(ChangeCart $cart): void
    {
        $cart->clear(request()->user());
        $this->removed = null;
    }

    public function render(CartReview $review, Settings $settings): View
    {
        $user = request()->user();
        $summary = $review->review($user);

        // The header lives outside the component: it learns the new count from this event.
        $this->dispatch('cart-updated', headline: $review->headline($user)->toArray());

        return view('livewire.cart-page', [
            'summary' => $summary,
            'vat' => $settings->get('seller.vat_mode'),
            'wholesalePending' => (bool) $user?->hasPendingCompany(),
        ]);
    }
}
