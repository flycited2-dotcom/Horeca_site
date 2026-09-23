<?php

/*
 * Клиент видит и меняет только своё (ТЗ §15.5). Заявку и счёт открывают по номеру — чужие
 * закрыты ответом 403 (OrderPolicy). Реквизиты правятся только у своей компании. Корзина и
 * избранное адресуются не номером записи, а владельцем: запрос другого клиента просто не
 * находит чужие строки и меняет только его собственные.
 */

use App\Enums\CompanyStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->product = Product::factory()->create([
        'name' => 'Шкаф чужой корзины',
        'category_id' => Category::factory()->create(['is_active' => true])->id,
    ]);
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
});

it('closes another customer\'s order, its repeat and its invoice with 403', function () {
    $order = Order::factory()->create(['user_id' => $this->owner->id]);
    $order->forceFill(['invoice_path' => 'invoices/schet.pdf'])->save();

    $this->actingAs($this->stranger)->get(route('account.order', $order))->assertForbidden();
    $this->actingAs($this->stranger)->post(route('account.order.repeat', $order))->assertForbidden();
    $this->actingAs($this->stranger)->get(route('account.order.invoice', $order))->assertForbidden();
    $this->actingAs($this->stranger)->get(route('account.orders'))->assertOk()->assertDontSee($order->number);
});

it('lets a customer edit only the company of their own account', function () {
    $owner = wholesaleCustomer(PriceTier::factory()->create());
    $stranger = wholesaleCustomer(PriceTier::factory()->create(), CompanyStatus::Pending);
    $legalName = $owner->company->legal_name;

    $this->actingAs($stranger)->get(route('account.company'))->assertOk()->assertDontSee($legalName);

    expect($stranger->can('update', $owner->company))->toBeFalse()
        ->and($owner->company->refresh()->legal_name)->toBe($legalName);
});

it('keeps each customer\'s cart apart', function () {
    $this->actingAs($this->owner)->post(route('cart.add', $this->product->id), ['quantity' => 3]);
    // The test client carries the session between requests: the notice «…в корзине» must not
    // reach the other customer's page.
    $this->flushSession();

    $this->actingAs($this->stranger)->get(route('cart'))->assertOk()->assertDontSee('Шкаф чужой корзины');
    $this->actingAs($this->stranger)->patch(route('cart.update', $this->product->id), ['quantity' => 1]);
    $this->actingAs($this->stranger)->delete(route('cart.remove', $this->product->id));
    $this->actingAs($this->stranger)->delete(route('cart.clear'));

    $cart = Cart::query()->where('user_id', $this->owner->id)->sole();

    expect($cart->items()->sole()->qty)->toBe(3)
        ->and($this->stranger->can('view', $cart))->toBeFalse();
});

it('keeps each customer\'s favorites apart', function () {
    Favorite::query()->create(['user_id' => $this->owner->id, 'product_id' => $this->product->id]);

    $this->actingAs($this->stranger)->get(route('favorites'))->assertOk()->assertDontSee('Шкаф чужой корзины');
    $this->actingAs($this->stranger)->delete(route('favorites.remove', $this->product->id));

    expect(Favorite::query()->where('user_id', $this->owner->id)->count())->toBe(1);
});
