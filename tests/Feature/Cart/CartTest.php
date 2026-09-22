<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ChangeCart;
use App\Enums\Availability;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cart\CannotAddToCart;
use App\Services\Cart\CartBlock;
use App\Services\Cart\CartReview;
use App\Services\Cart\CartStore;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

function sellable(array $attributes = []): Product
{
    return Product::factory()->create($attributes + ['retail_price' => Money::ofRubles(1_000)]);
}

function addToCart(Product $product, int $quantity = 1, ?User $user = null): CartItem
{
    return app(AddToCart::class)->handle($product, $quantity, $user);
}

/**
 * A new visitor: no cart cookie on the request.
 */
function newVisitor(): void
{
    request()->cookies->remove(CartStore::COOKIE);
}

it('puts a product in with its price of the moment and adds to what is there', function () {
    $product = sellable(['retail_price' => Money::ofRubles(2_948)]);

    addToCart($product, 2);
    $item = addToCart($product, 3);

    expect($item->qty)->toBe(5)
        ->and($item->price->equals(Money::ofRubles(2_948)))->toBeTrue()
        ->and(CartItem::query()->count())->toBe(1);

    expect(addToCart($product, 20_000)->qty)->toBe(9999);
});

it('refuses what can not be bought', function (array $attributes, CartBlock $block) {
    $product = Product::factory()->create($attributes);

    expect(fn () => addToCart($product))->toThrow(fn (CannotAddToCart $e) => expect($e->block)->toBe($block));
    expect(Cart::query()->count())->toBe(0);
})->with([
    'price on request' => [['retail_price' => null], CartBlock::PriceOnRequest],
    'discontinued' => [['retail_price' => Money::ofRubles(10), 'availability' => Availability::Discontinued], CartBlock::Discontinued],
    'hidden' => [['retail_price' => Money::ofRubles(10), 'is_visible' => false], CartBlock::Hidden],
]);

it('keeps a guest cart for 30 days behind a cookie of its own', function () {
    newVisitor();
    addToCart(sellable());

    $cart = Cart::query()->sole();
    $cookie = Cookie::queued(CartStore::COOKIE);

    expect($cart->user_id)->toBeNull()
        ->and($cart->session_id)->toBe($cookie->getValue())
        ->and($cart->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()))->toBeTrue()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp());

    newVisitor();
    expect(app(CartStore::class)->guest())->toBeNull();
});

it('tells about a changed price once and takes the new one', function () {
    $product = sellable(['retail_price' => Money::ofRubles(1_000)]);
    addToCart($product);
    $product->update(['retail_price' => Money::ofRubles(1_200)]);

    $first = app(CartReview::class)->review(null);
    $second = app(CartReview::class)->review(null);

    expect($first->hasChangedPrices())->toBeTrue()
        ->and($first->lines[0]->previousPrice->equals(Money::ofRubles(1_000)))->toBeTrue()
        ->and($first->total()->equals(Money::ofRubles(1_200)))->toBeTrue()
        ->and($second->hasChangedPrices())->toBeFalse();
});

it('holds the checkout while a line can no longer be bought', function () {
    $gone = sellable(['retail_price' => Money::ofRubles(500)]);
    $kept = sellable(['retail_price' => Money::ofRubles(700)]);
    addToCart($gone, 2);
    addToCart($kept);
    $gone->update(['availability' => Availability::Discontinued]);

    $summary = app(CartReview::class)->review(null);

    expect($summary->canCheckout())->toBeFalse()
        ->and($summary->lines[0]->block)->toBe(CartBlock::Discontinued)
        ->and($summary->total()->equals(Money::ofRubles(700)))->toBeTrue()
        ->and($summary->positions())->toBe(2)
        ->and($summary->units())->toBe(3);
});

it('counts the weight only when every line has one and the way to free delivery', function () {
    Setting::query()->create(['key' => 'delivery.free_city_from', 'value' => 5000]);
    $light = sellable(['retail_price' => Money::ofRubles(1_000), 'weight_kg' => '18.500']);
    $heavy = sellable(['retail_price' => Money::ofRubles(1_000), 'weight_kg' => '2']);
    addToCart($light, 2);
    addToCart($heavy);

    $summary = app(CartReview::class)->review(null);

    expect($summary->weightGrams())->toBe(39_000)
        ->and($summary->freeDeliveryLeft()->equals(Money::ofRubles(2_000)))->toBeTrue();

    addToCart(sellable(['weight_kg' => null]));

    expect(app(CartReview::class)->review(null)->weightGrams())->toBeNull();
});

it('merges the guest cart on sign-in by the price of the customer', function () {
    $user = User::factory()->create();
    $both = sellable(['retail_price' => Money::ofRubles(1_000)]);
    $mine = sellable();
    $gone = sellable();

    addToCart($mine, 1, $user);
    addToCart($both, 1, $user);

    newVisitor();
    addToCart($both, 2);
    addToCart($gone);
    $gone->update(['is_visible' => false]);
    $both->update(['retail_price' => Money::ofRubles(900)]);

    Auth::login($user);

    $items = Cart::query()->where('user_id', $user->id)->sole()->items()->get()->keyBy('product_id');

    expect(Cart::query()->whereNull('user_id')->count())->toBe(0)
        ->and($items->keys()->sort()->values()->all())->toBe(collect([$mine->id, $both->id])->sort()->values()->all())
        ->and($items[$both->id]->qty)->toBe(3)
        ->and($items[$both->id]->price->equals(Money::ofRubles(900)))->toBeTrue()
        ->and(request()->cookie(CartStore::COOKIE))->toBeNull();
});

it('changes only lines of its own cart', function () {
    $product = sellable();

    newVisitor();
    addToCart($product, 4);
    $strangers = Cart::query()->sole();

    newVisitor();
    $changes = app(ChangeCart::class);

    expect($changes->setQuantity($product->id, 1, null))->toBeNull()
        ->and($changes->remove($product->id, null))->toBeNull()
        ->and($strangers->items()->sole()->qty)->toBe(4);
});

it('removes a line and gives what «Вернуть» needs', function () {
    $product = sellable();
    addToCart($product, 3);

    expect(app(ChangeCart::class)->setQuantity($product->id, '0', null)->qty)->toBe(1)
        ->and(app(ChangeCart::class)->remove($product->id, null))->toBe(['product_id' => $product->id, 'qty' => 1])
        ->and(app(CartReview::class)->review(null)->isEmpty())->toBeTrue();
});

it('gives the header the positions and the sum in one query', function () {
    addToCart(sellable(['retail_price' => Money::ofRubles(1_500)]), 2);
    addToCart(sellable(['retail_price' => Money::ofRubles(250)]));

    $headline = app(CartReview::class)->headline(null);

    expect($headline->positions)->toBe(2)
        ->and($headline->total->equals(Money::ofRubles(3_250)))->toBeTrue()
        ->and($headline->label())->toBe('2 позиции')
        ->and($headline->totalLabel())->toBe('3 250 ₽');
});

it('prunes expired guest carts and keeps the customers ones', function () {
    $user = User::factory()->create();
    Cart::factory()->create(['expires_at' => now()->subDay()]);
    Cart::factory()->create(['user_id' => $user->id, 'session_id' => null, 'expires_at' => now()->subDay()]);
    $fresh = Cart::factory()->create(['expires_at' => now()->addDay()]);

    $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

    expect(Cart::query()->pluck('id')->sort()->values()->all())->toHaveCount(2)
        ->and(Cart::query()->whereKey($fresh->id)->exists())->toBeTrue()
        ->and(Cart::query()->where('user_id', $user->id)->exists())->toBeTrue();
});
