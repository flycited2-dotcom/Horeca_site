<?php

use App\Support\Money;
use App\Support\Percent;

it('parses decimals into kopecks', function (string $decimal, int $kopecks) {
    expect(Money::fromDecimal($decimal)->kopecks)->toBe($kopecks);
})->with([
    ['383995', 38_399_500],
    ['383995.00', 38_399_500],
    ['48605.8', 4_860_580],
    ['0.05', 5],
    ['-12.50', -1_250],
]);

it('formats kopecks back into a decimal string', function (int $kopecks, string $decimal) {
    expect(Money::ofKopecks($kopecks)->toDecimal())->toBe($decimal);
})->with([
    [38_399_500, '383995.00'],
    [4_860_580, '48605.80'],
    [5, '0.05'],
    [-1_250, '-12.50'],
    [0, '0.00'],
]);

it('rejects values that are not exact decimals with a dot', function (string $value) {
    Money::fromDecimal($value);
})->throws(InvalidArgumentException::class)->with(['', '12,50', '1.234', '1e3', 'abc', '48 605.8']);

it('adds, subtracts and multiplies without float errors', function () {
    $tenKopecks = Money::fromDecimal('0.10');

    expect($tenKopecks->multiply(3)->toDecimal())->toBe('0.30')
        ->and($tenKopecks->add(Money::fromDecimal('0.20'))->toDecimal())->toBe('0.30')
        ->and(Money::ofRubles(1000)->subtract(Money::fromDecimal('0.01'))->toDecimal())->toBe('999.99');
});

it('applies a markup and a discount rounding fractions of a kopeck up', function () {
    expect(Money::ofRubles(1000)->withMarkup(Percent::fromDecimal('35'))->toDecimal())->toBe('1350.00')
        ->and(Money::ofRubles(1000)->withDiscount(Percent::fromDecimal('10'))->toDecimal())->toBe('900.00')
        ->and(Money::fromDecimal('48605.80')->withDiscount(Percent::fromDecimal('10'))->toDecimal())->toBe('43745.22')
        ->and(Money::fromDecimal('0.01')->withDiscount(Percent::fromDecimal('10'))->toDecimal())->toBe('0.01');
});

it('rounds up to whole rubles and to a larger step', function () {
    expect(Money::fromDecimal('383994.01')->roundUpToRubles()->toDecimal())->toBe('383995.00')
        ->and(Money::fromDecimal('383995.00')->roundUpToRubles()->toDecimal())->toBe('383995.00')
        ->and(Money::fromDecimal('383991.00')->roundUpToRubles(10)->toDecimal())->toBe('384000.00')
        ->and(Money::fromDecimal('-1.50')->roundUpToRubles()->toDecimal())->toBe('-1.00');
});

it('rejects a rounding step below one ruble', function () {
    Money::ofRubles(1)->roundUpToRubles(0);
})->throws(InvalidArgumentException::class);

it('compares amounts', function () {
    $small = Money::ofRubles(10);
    $large = Money::ofRubles(20);

    expect($small->lessThan($large))->toBeTrue()
        ->and($large->greaterThan($small))->toBeTrue()
        ->and($small->equals(Money::fromDecimal('10.00')))->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromDecimal('-0.01')->isNegative())->toBeTrue();
});

it('serializes to a decimal string', function () {
    expect(json_encode(['price' => Money::ofRubles(5)]))->toBe('{"price":"5.00"}')
        ->and((string) Money::ofKopecks(150))->toBe('1.50');
});
