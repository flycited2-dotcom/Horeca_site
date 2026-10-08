<?php

use App\Services\Pricing\Price;
use App\Support\CategoryZone;
use App\Support\Money;
use App\View\HomeHero;

/**
 * @param  list<array{name: string, icon: ?string, products_count: int}>  $sections
 * @return array{products: int, brands: int, sections: list<array{name: string, icon: ?string, products_count: int}>}
 */
function heroHome(array $sections, int $products = 100, int $brands = 12): array
{
    return ['products' => $products, 'brands' => $brands, 'sections' => $sections];
}

function heroPrice(int $rubles): Price
{
    return new Price(Money::ofRubles($rubles), Money::ofRubles($rubles));
}

it('counts the models of the cold and the hot sections next to the positions and the brands', function () {
    $hero = HomeHero::from(heroHome([
        ['name' => 'Холодильное оборудование', 'icon' => null, 'products_count' => 4009],
        ['name' => 'Льдогенераторы', 'icon' => null, 'products_count' => 40],
        ['name' => 'Тепловое оборудование', 'icon' => null, 'products_count' => 1268],
        ['name' => 'Весовое оборудование', 'icon' => null, 'products_count' => 23],
    ], products: 15017, brands: 64));

    expect(array_column($hero->facts, 'value'))->toBe([15017, 64, 4049, 1268])
        ->and(array_column($hero->facts, 'zone'))->toBe([null, null, CategoryZone::COLD, CategoryZone::HOT])
        ->and(array_column($hero->facts, 'label'))->toBe([
            'shop.home.hero.facts.products',
            'shop.home.hero.facts.brands',
            'shop.home.hero.facts.cold',
            'shop.home.hero.facts.hot',
        ]);
});

it('takes the zone of a section from the icon the manager gave it', function () {
    $hero = HomeHero::from(heroHome([
        ['name' => 'Витрины', 'icon' => 'refrigeration', 'products_count' => 10],
        ['name' => 'Холодильное оборудование', 'icon' => 'neutral', 'products_count' => 50],
    ]));

    expect(array_column($hero->facts, 'value'))->toBe([100, 12, 10]);
});

it('leaves out a figure that is zero', function () {
    $hero = HomeHero::from(heroHome([
        ['name' => 'Весовое оборудование', 'icon' => null, 'products_count' => 23],
    ], products: 23, brands: 0));

    expect(array_column($hero->facts, 'value'))->toBe([23]);
});

it('shows no figures for an empty catalog', function () {
    expect(HomeHero::from(heroHome([], products: 0, brands: 0))->facts)->toBe([]);
});

it('names the lowest price among the prices of the page', function () {
    $hero = HomeHero::from(heroHome([]), [
        10 => heroPrice(37_114),
        11 => heroPrice(9_218),
        12 => heroPrice(22_388),
    ]);

    expect($hero->priceFrom?->kopecks)->toBe(921_800);
});

it('skips the models without a price when it looks for the lowest one', function () {
    $hero = HomeHero::from(heroHome([]), [
        10 => null,
        11 => heroPrice(0),
        12 => heroPrice(22_388),
    ]);

    expect($hero->priceFrom?->kopecks)->toBe(2_238_800);
});

it('names no price when the page has none', function () {
    expect(HomeHero::from(heroHome([]))->priceFrom)->toBeNull()
        ->and(HomeHero::from(heroHome([]), [10 => null])->priceFrom)->toBeNull()
        ->and(HomeHero::from(heroHome([]), [])->priceFrom)->toBeNull();
});
