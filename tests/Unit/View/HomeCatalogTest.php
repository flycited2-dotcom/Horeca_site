<?php

use App\Support\CategoryZone;
use App\View\HomeCatalog;

function homeSection(int $id, string $name, int $products, ?string $icon = null): array
{
    return [
        'id' => $id,
        'name' => $name,
        'slug' => "section-{$id}",
        'icon' => $icon,
        'products_count' => $products,
        'in_stock' => 0,
        'children' => [],
    ];
}

it('puts the biggest cold and the biggest hot section first and keeps the rest in their order', function () {
    $tiles = HomeCatalog::from([
        homeSection(1, 'Аксессуары', 5),
        homeSection(2, 'Тепловое оборудование', 1268),
        homeSection(3, 'Льдогенераторы', 40),
        homeSection(4, 'Весовое оборудование', 23),
        homeSection(5, 'Холодильное оборудование', 4009),
        homeSection(6, 'Печи для пиццы', 90),
    ])->tiles;

    expect(array_column($tiles, 'id'))->toBe([5, 2, 1, 3, 4, 6])
        ->and(array_column($tiles, 'featured'))->toBe([true, true, false, false, false, false])
        ->and(array_column($tiles, 'zone'))->toBe([
            CategoryZone::COLD,
            CategoryZone::HOT,
            CategoryZone::NEUTRAL,
            CategoryZone::COLD,
            CategoryZone::NEUTRAL,
            CategoryZone::HOT,
        ]);
});

it('takes the zone from the icon a manager gave the section', function () {
    $tiles = HomeCatalog::from([
        homeSection(1, 'Витрины', 10, 'refrigeration'),
        homeSection(2, 'Холодильное оборудование', 50, 'neutral'),
    ])->tiles;

    expect(array_column($tiles, 'id'))->toBe([1, 2])
        ->and(array_column($tiles, 'featured'))->toBe([true, false]);
});

it('features nothing in a catalog without cold or hot sections', function () {
    $tiles = HomeCatalog::from([
        homeSection(1, 'Весовое оборудование', 23),
        homeSection(2, 'Нейтральное оборудование', 7021),
    ])->tiles;

    expect(array_column($tiles, 'id'))->toBe([1, 2])
        ->and(array_column($tiles, 'featured'))->toBe([false, false]);
});
