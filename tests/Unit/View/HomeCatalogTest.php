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

it('gives a section without a picture one word of its name for the ruled plate', function () {
    $labels = array_column(HomeCatalog::from([
        homeSection(1, 'Торговые стеллажи', 4),
        homeSection(2, 'Весовое оборудование', 23),
        homeSection(3, 'Кофемолки и кофемашины', 66),
        homeSection(4, 'Фаст-фуд', 224),
        homeSection(5, 'бытовое оборудование', 11),
        homeSection(6, 'Оборудование', 3),
    ])->tiles, 'label', 'id');

    expect($labels)->toBe([
        1 => 'Стеллажи',
        2 => 'Весовое',
        3 => 'Кофемашины',
        4 => 'Фаст-фуд',
        5 => 'Бытовое',
        6 => 'Оборудование',
    ]);
});

it('gives the featured sections of a zone to the first screen', function () {
    $catalog = HomeCatalog::from([
        homeSection(1, 'Аксессуары', 5),
        homeSection(2, 'Тепловое оборудование', 1268),
        homeSection(3, 'Холодильное оборудование', 4009),
        homeSection(4, 'Льдогенераторы', 40),
    ]);

    expect($catalog->lead(CategoryZone::COLD)['id'])->toBe(3)
        ->and($catalog->lead(CategoryZone::HOT)['id'])->toBe(2)
        ->and($catalog->lead(CategoryZone::NEUTRAL))->toBeNull();
});

it('has no section for the first screen where the catalog has no such zone', function () {
    $catalog = HomeCatalog::from([
        homeSection(1, 'Весовое оборудование', 23),
        homeSection(2, 'Холодильное оборудование', 4009),
    ]);

    expect($catalog->lead(CategoryZone::COLD)['id'])->toBe(2)
        ->and($catalog->lead(CategoryZone::HOT))->toBeNull();
});

it('stretches a last section that would stay alone in its row on the tablet', function (int $smaller, bool $stretched) {
    $sections = [
        homeSection(1, 'Холодильное оборудование', 4009),
        homeSection(2, 'Тепловое оборудование', 1268),
        ...array_map(fn (int $number): array => homeSection($number + 2, "Нейтральный раздел {$number}", 10), range(1, $smaller)),
    ];

    $tiles = HomeCatalog::from($sections)->tiles;

    // Two big sections fill the first row of six columns, the others take two columns each: three to a row.
    expect(array_column($tiles, 'wide'))->toBe([...array_fill(0, count($tiles) - 1, false), $stretched]);
})->with([
    'one section in the last row' => [4, true],
    'two sections in the last row' => [5, false],
    'a full last row' => [6, false],
    'one section in the last row after two full ones' => [7, true],
]);

it('stretches the only section of a catalog over the row', function () {
    $tiles = HomeCatalog::from([homeSection(1, 'Весовое оборудование', 23)])->tiles;

    expect(array_column($tiles, 'wide'))->toBe([true]);
});
