<?php

use App\Services\Supplier\Import\PriceNormalizer;

beforeEach(function () {
    $this->prices = new PriceNormalizer;
});

it('reads both formats of the supplier price', function (string $raw, int $kopecks) {
    expect($this->prices->parse($raw)?->kopecks)->toBe($kopecks);
})->with([
    'каталог: неразрывный пробел и запятая' => ["48\u{00A0}605,8", 4_860_580],
    'каталог: обычный пробел' => ['383 995', 38_399_500],
    'остатки: десятичная точка' => ['72581.5', 7_258_150],
    'ноль' => ['0', 0],
    'с рублями' => ['2 948 руб.', 294_800],
]);

it('treats an empty price as no price at all', function (?string $raw) {
    expect($this->prices->parse($raw))->toBeNull();
})->with([null, '', '   ']);

it('refuses a value that is not a price', function (string $raw) {
    $this->prices->parse($raw);
})->throws(InvalidArgumentException::class)->with(['по запросу', '1.234', '12-15', 'NaN']);

it('reads a negative price so that the run can reject the record', function () {
    expect($this->prices->parse('-100')?->isNegative())->toBeTrue();
});
