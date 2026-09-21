<?php

use App\Support\Money;
use App\Support\Typography;

it('writes prices with non-breaking spaces', function (string $decimal, string $formatted) {
    expect(Typography::money(Money::fromDecimal($decimal)))->toBe($formatted);
})->with([
    ['383995', "383\u{00A0}995\u{00A0}₽"],
    ['48605.8', "48\u{00A0}605,80\u{00A0}₽"],
    ['1000000', "1\u{00A0}000\u{00A0}000\u{00A0}₽"],
    ['999', "999\u{00A0}₽"],
    ['0.05', "0,05\u{00A0}₽"],
    ['-12.5', "−12,50\u{00A0}₽"],
]);

it('groups the digits of a count with non-breaking spaces', function (int $value, string $formatted) {
    expect(Typography::number($value))->toBe($formatted);
})->with([
    [7021, "7\u{00A0}021"],
    [1_204_000, "1\u{00A0}204\u{00A0}000"],
    [412, '412'],
    [0, '0'],
    [-1500, "−1\u{00A0}500"],
]);
