<?php

use App\Enums\Availability;
use App\Livewire\CartPage;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Services\Cart\CartStore;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Пароконвектоматы', 'slug' => 'parokonvektomaty']);
});

function cartProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes + [
        'category_id' => test()->category->id,
        'retail_price' => Money::ofRubles(2_948),
        'unit' => 'шт',
    ]);
}

it('puts a product into the cart for the script and tells the header what is inside', function () {
    $product = cartProduct(['name' => 'Средство Abat Decalc']);

    $this->postJson(route('cart.add', $product->id), ['quantity' => 2])
        ->assertOk()
        ->assertJson([
            'product' => $product->id,
            'quantity' => 2,
            'headline' => ['positions' => 1, 'label' => '1 позиция', 'total' => "5\u{00A0}896\u{00A0}₽"],
            'notice' => ['text' => '«Средство Abat Decalc» — в корзине, 2 шт', 'href' => route('cart'), 'link' => 'Перейти в корзину'],
        ])
        ->assertCookie(CartStore::COOKIE);
});

it('explains why a product can not go into the cart', function () {
    $this->postJson(route('cart.add', cartProduct(['retail_price' => null])->id))
        ->assertStatus(422)
        ->assertJson(['notice' => ['text' => 'У товара цена по запросу — менеджер пришлёт цену.']]);
});

it('goes back with a notice when scripts are off', function () {
    $product = cartProduct();

    $this->from(route('product', $product))
        ->post(route('cart.add', $product->id))
        ->assertRedirect(route('product', $product))
        ->assertSessionHas('notice.href', route('cart'));
});

it('offers «В корзину» only where a product can be bought', function () {
    $sellable = cartProduct();
    $onRequest = cartProduct(['retail_price' => null]);

    $this->get(route('category', $this->category))
        ->assertOk()
        ->assertSee('action="'.route('cart.add', $sellable->id).'"', false)
        ->assertDontSee('action="'.route('cart.add', $onRequest->id).'"', false)
        ->assertSee('Запросить цену');

    $this->get(route('product', $sellable))
        ->assertSee('name="quantity"', false)
        ->assertSee('Добавить в корзину');
});

it('shows the positions and the sum of the cart in the header', function () {
    $product = cartProduct(['retail_price' => Money::ofRubles(1_500)]);
    putInCart($product, 3);

    $this->get('/')
        ->assertSeeInOrder(['data-cart-link', '1 позиция', "4\u{00A0}500\u{00A0}₽"], false);
});

it('lists the cart with the sum, the notes and the way to checkout', function () {
    $inStock = cartProduct(['name' => 'Шкаф в наличии', 'availability' => Availability::InStock, 'retail_price' => Money::ofRubles(1_000), 'weight_kg' => '10']);
    $onOrder = cartProduct(['name' => 'Шкаф под заказ', 'availability' => Availability::OnOrder, 'retail_price' => Money::ofRubles(500), 'weight_kg' => '2.5']);
    putInCart($inStock, 2);
    putInCart($onOrder);

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSeeInOrder(['Корзина', '2 позиции · 3 единицы', 'Шкаф в наличии', 'Шкаф под заказ', 'Срок поставки уточнит менеджер'])
        ->assertSee("2\u{00A0}500\u{00A0}₽", false)
        ->assertSee("22,5\u{00A0}кг", false)
        ->assertSee(route('checkout'));
});

it('changes the quantity, removes and brings back a line without scripts', function () {
    $product = cartProduct(['name' => 'Шкаф холодильный']);
    putInCart($product);

    $this->patch(route('cart.update', $product->id), ['quantity' => 4])->assertRedirect(route('cart'));
    $this->get(route('cart'))->assertSee('value="4"', false);

    $this->delete(route('cart.remove', $product->id))->assertRedirect(route('cart'))->assertSessionHas('cart_removed.qty', 4);
    $this->get(route('cart'))->assertSee('«Шкаф холодильный» удалён из корзины.')->assertSee('Вернуть');

    $this->post(route('cart.restore', $product->id), ['quantity' => 4])->assertRedirect(route('cart'));
    $this->get(route('cart'))->assertSee('value="4"', false)->assertDontSee('удалён из корзины');
});

it('removes a line at zero and brings it back on the spot', function () {
    $product = cartProduct(['name' => 'Плита электрическая']);
    putInCart($product, 2);

    Livewire::withCookie(CartStore::COOKIE, (string) Cart::query()->value('session_id'))
        ->test(CartPage::class)
        ->call('setQuantity', $product->id, '5')
        ->assertSee('value="5"', false)
        ->call('setQuantity', $product->id, '0')
        ->assertSet('removed.qty', 5)
        ->assertSee('«Плита электрическая» удалён из корзины.')
        ->call('restore')
        ->assertSet('removed', null)
        ->assertSee('value="5"', false)
        ->assertDispatched('cart-updated', headline: ['positions' => 1, 'label' => '1 позиция', 'total' => "14\u{00A0}740\u{00A0}₽"]);
});

it('holds the checkout while a line can no longer be bought', function () {
    $product = cartProduct(['name' => 'Снятая модель']);
    putInCart($product);
    $product->update(['availability' => Availability::Discontinued]);

    $this->get(route('cart'))
        ->assertSee('Товар снят с производства — удалите позицию, чтобы отправить заявку.')
        ->assertSee('aria-disabled="true"', false)
        ->assertDontSee('href="'.route('checkout').'"', false);

    $this->get(route('checkout'))->assertRedirect(route('cart'));
});

it('tells about a changed price when the cart is opened', function () {
    $product = cartProduct(['retail_price' => Money::ofRubles(1_000)]);
    putInCart($product);
    $product->update(['retail_price' => Money::ofRubles(1_100)]);

    $this->get(route('cart'))
        ->assertSee('С момента добавления изменились цены')
        ->assertSee("Цена изменилась: было 1\u{00A0}000\u{00A0}₽, стало 1\u{00A0}100\u{00A0}₽.", false);
});

it('clears the cart', function () {
    putInCart(cartProduct());

    $this->delete(route('cart.clear'))->assertRedirect(route('cart'))->assertSessionHas('notice.text', 'Корзина очищена.');
    $this->get(route('cart'))->assertSee('В корзине пока пусто');
});

it('opens the cart with a fixed number of queries', function () {
    foreach (range(1, 10) as $index) {
        putInCart(cartProduct());
    }

    DB::enableQueryLog();
    $this->get(route('cart'))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(20);
});
