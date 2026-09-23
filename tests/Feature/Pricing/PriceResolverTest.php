<?php

use App\Enums\CompanyStatus;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Pricing\PriceResolver;
use App\Services\Settings\Settings;
use App\Support\Money;
use App\Support\Percent;

beforeEach(function () {
    $this->tier = PriceTier::factory()->create([
        'name' => 'Опт-1',
        'discount_percent' => Percent::fromDecimal('10'),
    ]);

    $this->product = Product::factory()->create([
        'retail_price' => Money::ofRubles(1000),
        'purchase_price' => null,
        'old_price' => null,
    ]);

    setting('pricing.max_discount_without_purchase', 0);
    setting('pricing.min_margin_percent', null);
    setting('pricing.show_tier_name', false);
});

it('gives a guest the retail price', function () {
    $price = app(PriceResolver::class)->for($this->product, null);

    expect($price?->amount->toDecimal())->toBe('1000.00')
        ->and($price->isWholesale)->toBeFalse();
});

it('asks for the price when there is no retail price', function () {
    $product = Product::factory()->priceOnRequest()->create();

    expect(app(PriceResolver::class)->for($product, wholesaleCustomer($this->tier)))->toBeNull();
});

it('keeps the retail price while the purchase price is unknown and no discount is allowed', function () {
    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('1000.00')
        ->and($price->isWholesale)->toBeTrue()
        ->and($price->hasDiscount())->toBeFalse();
});

it('caps the tier discount while the purchase price is unknown', function () {
    setting('pricing.max_discount_without_purchase', 5);

    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('950.00');
});

it('gives the full tier discount when the purchase price is known', function () {
    $this->product->forceFill(['purchase_price' => Money::ofRubles(500)])->save();

    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('900.00')
        ->and($price->hasDiscount())->toBeTrue();
});

it('never goes below the purchase price plus the minimal margin', function () {
    setting('pricing.min_margin_percent', '20');
    $this->product->forceFill(['purchase_price' => Money::ofRubles(800)])->save();

    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('960.00');
});

it('rounds a discounted price up to whole rubles', function () {
    $this->product->forceFill([
        'retail_price' => Money::fromDecimal('999.99'),
        'purchase_price' => Money::ofRubles(100),
    ])->save();

    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('900.00');
});

it('prefers the fixed price of the tier', function () {
    $this->product->forceFill(['purchase_price' => Money::ofRubles(500)])->save();
    ProductPrice::factory()->create([
        'product_id' => $this->product->id,
        'price_tier_id' => $this->tier->id,
        'price' => Money::ofRubles(700),
    ]);

    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('700.00');
});

it('holds the fixed price to the same floor', function () {
    ProductPrice::factory()->create([
        'product_id' => $this->product->id,
        'price_tier_id' => $this->tier->id,
        'price' => Money::ofRubles(700),
    ]);

    $price = app(PriceResolver::class)->for($this->product->load('prices'), wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('1000.00');
});

it('never charges a wholesale customer more than retail', function () {
    setting('pricing.min_margin_percent', '50');
    $this->product->forceFill(['purchase_price' => Money::ofRubles(900)])->save();

    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier));

    expect($price?->amount->toDecimal())->toBe('1000.00');
});

it('shows the retail price to a company that is still being checked', function () {
    setting('pricing.max_discount_without_purchase', 10);

    $price = app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier, CompanyStatus::Pending));

    expect($price?->amount->toDecimal())->toBe('1000.00')
        ->and($price->isWholesale)->toBeFalse();
});

it('hides the tier name unless the setting allows it', function () {
    $customer = wholesaleCustomer($this->tier);

    expect(app(PriceResolver::class)->for($this->product, $customer)?->tierName)->toBeNull();

    setting('pricing.show_tier_name', true);

    expect((new PriceResolver(new Settings))->for($this->product, $customer)?->tierName)->toBe('Опт-1');
});

it('shows the promotion price to retail customers only', function () {
    $this->product->forceFill(['old_price' => Money::ofRubles(1200)])->save();

    expect(app(PriceResolver::class)->for($this->product, null)?->oldPrice?->toDecimal())->toBe('1200.00')
        ->and(app(PriceResolver::class)->for($this->product, wholesaleCustomer($this->tier))?->oldPrice)->toBeNull();
});
