<?php

use App\Enums\Availability;
use App\Enums\WarehouseStockStatus;
use App\Services\Catalog\AvailabilityCalculator;

it('puts a product in stock when at least one warehouse has it', function () {
    $statuses = [WarehouseStockStatus::Out, WarehouseStockStatus::Low, WarehouseStockStatus::InStock];

    expect(AvailabilityCalculator::fromStatuses($statuses))->toBe(Availability::InStock);
});

it('says "мало" when there is no full warehouse left', function () {
    $statuses = [WarehouseStockStatus::Out, WarehouseStockStatus::Low];

    expect(AvailabilityCalculator::fromStatuses($statuses))->toBe(Availability::Low);
});

it('falls back to "под заказ" without any stock', function () {
    expect(AvailabilityCalculator::fromStatuses([]))->toBe(Availability::OnOrder)
        ->and(AvailabilityCalculator::fromStatuses([WarehouseStockStatus::Out]))->toBe(Availability::OnOrder);
});

it('says "ожидается" only when the source reports a delivery on its way', function () {
    expect(AvailabilityCalculator::fromStatuses([], isIncoming: true))->toBe(Availability::Incoming);
});

it('keeps "снят с производства" above everything else', function () {
    $statuses = [WarehouseStockStatus::InStock];

    expect(AvailabilityCalculator::fromStatuses($statuses, isDiscontinued: true))->toBe(Availability::Discontinued);
});

it('gives every status the sorting weight of TZ 6.5', function () {
    expect(Availability::InStock->rank())->toBe(1)
        ->and(Availability::Low->rank())->toBe(1)
        ->and(Availability::Incoming->rank())->toBe(2)
        ->and(Availability::OnOrder->rank())->toBe(3)
        ->and(Availability::Discontinued->rank())->toBe(4);
});
