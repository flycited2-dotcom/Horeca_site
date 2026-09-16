<?php

use App\Support\Percent;

it('parses decimals into basis points', function (string $decimal, int $basisPoints) {
    expect(Percent::fromDecimal($decimal)->basisPoints)->toBe($basisPoints);
})->with([
    ['35', 3_500],
    ['35.5', 3_550],
    ['10.00', 1_000],
    ['0.01', 1],
    ['999.99', 99_999],
]);

it('formats basis points back into a decimal string', function (int $basisPoints, string $decimal) {
    expect(Percent::ofBasisPoints($basisPoints)->toDecimal())->toBe($decimal);
})->with([
    [3_550, '35.50'],
    [0, '0.00'],
    [99_999, '999.99'],
]);

it('rejects negative, malformed or too large percents', function (string $value) {
    Percent::fromDecimal($value);
})->throws(InvalidArgumentException::class)->with(['-5', '10,5', '1000', '1.234', '']);

it('rejects basis points out of range', function (int $basisPoints) {
    Percent::ofBasisPoints($basisPoints);
})->throws(InvalidArgumentException::class)->with([-1, 100_000]);

it('picks the smaller of two percents', function () {
    expect(Percent::fromDecimal('15')->min(Percent::fromDecimal('10'))->basisPoints)->toBe(1_000)
        ->and(Percent::zero()->isZero())->toBeTrue()
        ->and(json_encode(Percent::fromDecimal('10')))->toBe('"10.00"');
});
