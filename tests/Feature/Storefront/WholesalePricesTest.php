<?php

use App\Enums\CompanyStatus;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Support\Money;
use App\Support\Percent;

beforeEach(function () {
    // Wholesale prices open only when the customer has set how far below retail they may go (TZ §7).
    setting('pricing.max_discount_without_purchase', 20);
    $this->tier = PriceTier::factory()->create(['name' => 'Опт-1', 'discount_percent' => Percent::fromDecimal('10')]);
    $this->category = Category::factory()->create(['is_active' => true]);
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Шкаф холодильный',
        'retail_price' => Money::ofRubles(100_000),
    ]);
});

it('shows an approved customer the struck retail price and his own everywhere', function () {
    $this->actingAs(wholesaleCustomer($this->tier));

    $this->get(route('category', $this->category))
        ->assertOk()
        ->assertSee('100'."\u{00A0}".'000'."\u{00A0}".'₽')
        ->assertSee('90'."\u{00A0}".'000'."\u{00A0}".'₽')
        ->assertSee('Ваша цена');

    $this->get(route('product', $this->product))
        ->assertOk()
        ->assertSee('Ваша цена')
        ->assertDontSee('Оптовые цены откроются после проверки заявки.');

    $this->post(route('cart.add', $this->product->id), ['quantity' => 2]);

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('<s aria-label="Розничная цена за единицу">100'."\u{00A0}".'000'."\u{00A0}".'₽</s>', false)
        ->assertSee('180'."\u{00A0}".'000'."\u{00A0}".'₽');
});

it('keeps retail prices for a pending company and says when wholesale ones open', function () {
    $this->actingAs(wholesaleCustomer($this->tier, CompanyStatus::Pending));

    $this->get(route('product', $this->product))
        ->assertOk()
        ->assertDontSee('Ваша цена')
        ->assertSee('Оптовые цены откроются после проверки заявки.')
        ->assertSee('href="'.route('wholesale').'"', false);

    $this->post(route('cart.add', $this->product->id));

    $this->get(route('cart'))
        ->assertSee('Оптовые цены откроются после проверки заявки.')
        ->assertDontSee('<s aria-label="Розничная цена за единицу">', false);
});

it('shows a guest and a customer without a company only the retail price', function () {
    $this->get(route('product', $this->product))
        ->assertDontSee('Ваша цена')
        ->assertDontSee('Оптовые цены откроются после проверки заявки.');
});
