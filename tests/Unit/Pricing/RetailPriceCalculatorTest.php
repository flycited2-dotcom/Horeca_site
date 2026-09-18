<?php

use App\Enums\PriceKind;
use App\Services\Pricing\RetailPriceCalculator;
use App\Support\Money;
use App\Support\Percent;

beforeEach(function () {
    $this->calculator = new RetailPriceCalculator;
});

it('takes the RRP as it is, rounded up to whole rubles', function () {
    $price = $this->calculator->calculate(
        PriceKind::Rrp,
        Percent::zero(),
        1,
        Money::fromDecimal('48605.80'),
        null,
    );

    expect($price?->toDecimal())->toBe('48606.00');
});

it('rounds the RRP up to the supplier step', function () {
    $price = $this->calculator->calculate(
        PriceKind::Rrp,
        Percent::zero(),
        10,
        Money::fromDecimal('48605.80'),
        null,
    );

    expect($price?->toDecimal())->toBe('48610.00');
});

it('adds the markup to the dealer price', function () {
    $price = $this->calculator->calculate(
        PriceKind::Dealer,
        Percent::fromDecimal('35'),
        1,
        Money::fromDecimal('48605.80'),
        Money::fromDecimal('1000'),
    );

    expect($price?->toDecimal())->toBe('1350.00');
});

it('asks for the price when the supplier has none', function (?string $rrp) {
    $price = $this->calculator->calculate(
        PriceKind::Rrp,
        Percent::zero(),
        1,
        $rrp === null ? null : Money::fromDecimal($rrp),
        null,
    );

    expect($price)->toBeNull();
})->with([null, '0', '-100']);

it('asks for the price when the dealer price is not known yet', function () {
    $price = $this->calculator->calculate(
        PriceKind::Dealer,
        Percent::fromDecimal('35'),
        1,
        Money::fromDecimal('48605.80'),
        null,
    );

    expect($price)->toBeNull();
});
