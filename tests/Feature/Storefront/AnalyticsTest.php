<?php

use App\Enums\LeadType;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Requests\LeadRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Queue::fake();
    $this->product = Product::factory()->create([
        'name' => 'Шкаф холодильный ШХс-0,7',
        'sku' => '11000018820',
        'category_id' => Category::factory()->create(['is_active' => true, 'name' => 'Шкафы'])->id,
        'retail_price' => Money::ofRubles(96_750),
    ]);
});

/**
 * The Metrica events a page hands to the storefront script.
 *
 * @return list<array<string, mixed>>
 */
function metrikaEvents(TestResponse $response): array
{
    preg_match('/<script type="application\/json" data-metrika-events>(.*?)<\/script>/s', $response->getContent(), $match);

    return isset($match[1]) ? json_decode($match[1], true) : [];
}

it('asks for cookie consent until the visitor chooses and remembers the choice for a year', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-cookie-banner', false)
        ->assertSee('Принять все')
        ->assertSee('Только необходимые');

    $this->post(route('cookie-consent'), ['consent' => 'necessary'])
        ->assertRedirect(route('home'))
        ->assertCookie(CookieConsentController::COOKIE, 'necessary', encrypted: false);

    $this->withUnencryptedCookie(CookieConsentController::COOKIE, 'necessary')
        ->get(route('home'))
        ->assertDontSee('data-cookie-banner', false)
        ->assertSee('data-consent="necessary"', false);

    $this->postJson(route('cookie-consent'), ['consent' => 'all'])
        ->assertOk()
        ->assertJson(['consent' => 'all'])
        ->assertCookie(CookieConsentController::COOKIE, 'all', encrypted: false);

    $this->post(route('cookie-consent'), ['consent' => 'everything'])->assertSessionHasErrors('consent');
});

it('gives the page no counter without a number or after «Только необходимые»', function () {
    $this->get(route('product', $this->product))->assertDontSee('name="metrika"', false);

    setting('analytics.metrika_id', '98765432');

    $this->withUnencryptedCookie(CookieConsentController::COOKIE, 'necessary')
        ->get(route('product', $this->product))
        ->assertDontSee('name="metrika"', false)
        ->assertDontSee('data-metrika-events', false);
});

it('describes the product view and the purchase for e-commerce', function () {
    setting('analytics.metrika_id', '98765432');

    $page = $this->withUnencryptedCookie(CookieConsentController::COOKIE, 'all')->get(route('product', $this->product));
    $page->assertSee('<meta name="metrika" content="98765432">', false);

    expect(metrikaEvents($page))->toBe([[
        'goal' => null,
        'params' => [],
        'ecommerce' => ['currencyCode' => 'RUB', 'detail' => ['products' => [[
            'id' => '11000018820',
            'name' => 'Шкаф холодильный ШХс-0,7',
            'brand' => $this->product->brand->name,
            'category' => 'Шкафы',
            'price' => '96750.00',
        ]]]],
    ]]);

    $order = Order::factory()->create(['number' => 'HR-230926-0001', 'total' => Money::ofRubles(193_500)]);
    OrderItem::factory()->create(['order_id' => $order->id, 'sku' => '11000018820', 'name' => 'Шкаф холодильный ШХс-0,7', 'qty' => 2, 'price' => Money::ofRubles(96_750)]);

    $success = $this->withUnencryptedCookie(CookieConsentController::COOKIE, 'all')
        ->withSession([CheckoutController::PLACED => ['HR-230926-0001']])
        ->get(route('checkout.success', 'HR-230926-0001'));

    expect(metrikaEvents($success))->toBe([[
        'goal' => 'order_created',
        'params' => [],
        'ecommerce' => ['currencyCode' => 'RUB', 'purchase' => [
            'actionField' => ['id' => 'HR-230926-0001', 'revenue' => '193500.00'],
            'products' => [['id' => '11000018820', 'name' => 'Шкаф холодильный ШХс-0,7', 'price' => '96750.00', 'quantity' => 2]],
        ]],
    ]]);
});

it('reports adding to the cart and a lead in the answer to the script and after a redirect', function () {
    setting('analytics.metrika_id', '98765432');

    $this->postJson(route('cart.add', $this->product->id), ['quantity' => 2])
        ->assertOk()
        ->assertJsonPath('metrika.0.goal', 'add_to_cart')
        ->assertJsonPath('metrika.0.ecommerce.add.products.0.quantity', 2);

    $this->postJson(route('leads.store'), [
        'type' => LeadType::Callback->value,
        'phone' => '+7 978 123-45-67',
        'consent' => '1',
        'started' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
        LeadRequest::HONEYPOT => '',
    ])->assertOk()->assertJsonPath('metrika.0', ['goal' => 'lead_created', 'params' => ['type' => 'callback'], 'ecommerce' => null]);

    $this->from(route('product', $this->product))->post(route('cart.add', $this->product->id), ['quantity' => 1]);

    $events = metrikaEvents($this->withUnencryptedCookie(CookieConsentController::COOKIE, 'all')->get(route('product', $this->product)));

    expect(collect($events)->pluck('goal')->all())->toBe(['add_to_cart', null]);
});
