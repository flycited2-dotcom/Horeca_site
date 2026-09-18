<?php

use App\Enums\WarehouseStockStatus;
use App\Services\Supplier\Import\StockValueMapper;

beforeEach(function () {
    $this->values = new StockValueMapper([
        'много' => 'in_stock',
        'в наличии' => 'in_stock',
        'несколько' => 'low',
        '0' => 'out',
    ]);
});

it('maps the four values the supplier actually sends', function (string $raw, WarehouseStockStatus $status) {
    [$mapped, $recognized] = $this->values->fromText($raw);

    expect($mapped)->toBe($status)->and($recognized)->toBeTrue();
})->with([
    ['много', WarehouseStockStatus::InStock],
    ['В наличии', WarehouseStockStatus::InStock],
    ['несколько', WarehouseStockStatus::Low],
    ['0', WarehouseStockStatus::Out],
]);

it('counts an empty balance as no stock and does not complain', function () {
    [$status, $recognized] = $this->values->fromText(null);

    expect($status)->toBe(WarehouseStockStatus::Out)->and($recognized)->toBeTrue();
});

it('counts an unknown balance as no stock and reports it', function () {
    [$status, $recognized] = $this->values->fromText('под заказ');

    expect($status)->toBe(WarehouseStockStatus::Out)->and($recognized)->toBeFalse();
});

it('maps a numeric balance from the API against the low stock threshold', function (string $quantity, WarehouseStockStatus $status) {
    expect($this->values->fromQuantity($quantity, 3))->toBe($status);
})->with([
    ['10', WarehouseStockStatus::InStock],
    ['3', WarehouseStockStatus::InStock],
    ['2', WarehouseStockStatus::Low],
    ['0.5', WarehouseStockStatus::Low],
    ['0', WarehouseStockStatus::Out],
    ['-4', WarehouseStockStatus::Out],
]);
