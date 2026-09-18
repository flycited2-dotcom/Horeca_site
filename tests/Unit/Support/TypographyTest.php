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
