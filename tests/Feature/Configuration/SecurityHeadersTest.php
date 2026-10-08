<?php

use App\Http\Controllers\CookieConsentController;
use App\Http\Middleware\AddSecurityHeaders;
use App\Models\Product;

it('tells browsers to keep to HTTPS and shuts the devices the shop does not need', function () {
    $this->get('https://localhost/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', AddSecurityHeaders::HSTS)
        ->assertHeader('Permissions-Policy', AddSecurityHeaders::PERMISSIONS);
});

it('does not send HSTS over plain HTTP, where a browser must ignore it', function () {
    $this->get('http://localhost/')
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security')
        ->assertHeader('Permissions-Policy', AddSecurityHeaders::PERMISSIONS);
});

it('sends the headers with errors too', function () {
    $this->get('https://localhost/no-such-page')
        ->assertNotFound()
        ->assertHeader('Strict-Transport-Security', AddSecurityHeaders::HSTS);
});

it('limits how often one address may change the cart, the comparison and the favorites', function () {
    $product = Product::factory()->create();

    foreach (['compare.add', 'favorites.add', 'cart.add'] as $route) {
        $statuses = collect(range(1, 125))->map(fn () => $this->postJson(route($route, $product))->status());

        expect($statuses->last())->toBe(429, $route)
            ->and($statuses->first())->not->toBe(429, $route);

        $this->flushSession();
        app('cache')->flush();
    }
});

it('limits the cookie consent form', function () {
    $statuses = collect(range(1, 35))->map(fn () => $this->post(route('cookie-consent'), ['consent' => CookieConsentController::ALL])->status());

    expect($statuses->last())->toBe(429)
        ->and($statuses->first())->not->toBe(429);
});
