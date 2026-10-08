<?php

namespace App\View;

use App\Services\Pricing\Price;
use App\Support\CategoryZone;
use App\Support\Money;

/**
 * Первый экран главной (облик «Свечение», ТЗ §8.1): цифры каталога под заголовком и мелкая
 * строка с нижней границей цен. Всё считается из тех же корневых разделов, что и плитки ниже:
 * позиции, производители и модели «холодных» и «горячих» разделов. Цифра без моделей не
 * показывается. «Цены от» — самая низкая цена среди моделей, которые главная показывает сама,
 * то есть строка говорит только о том, что видно на странице; нет цен — нет и строки.
 */
final readonly class HomeHero
{
    /**
     * @param  list<array{value: int, label: string, zone: ?string}>  $facts
     */
    public function __construct(public array $facts, public ?Money $priceFrom = null) {}

    /**
     * @param  array{products: int, brands: int, sections: list<array{name: string, icon: ?string, products_count: int}>}  $home
     * @param  array<int, Price|null>  $prices  цены моделей главной: id товара => цена (PriceResolver::forMany)
     */
    public static function from(array $home, array $prices = []): self
    {
        $zones = [CategoryZone::COLD => 0, CategoryZone::HOT => 0];

        foreach ($home['sections'] as $section) {
            $zone = CategoryZone::of($section['icon'], $section['name']);

            if (isset($zones[$zone])) {
                $zones[$zone] += $section['products_count'];
            }
        }

        $facts = [
            ['value' => $home['products'], 'label' => 'shop.home.hero.facts.products', 'zone' => null],
            ['value' => $home['brands'], 'label' => 'shop.home.hero.facts.brands', 'zone' => null],
            ['value' => $zones[CategoryZone::COLD], 'label' => 'shop.home.hero.facts.cold', 'zone' => CategoryZone::COLD],
            ['value' => $zones[CategoryZone::HOT], 'label' => 'shop.home.hero.facts.hot', 'zone' => CategoryZone::HOT],
        ];

        return new self(
            array_values(array_filter($facts, fn (array $fact): bool => $fact['value'] > 0)),
            self::cheapest($prices),
        );
    }

    /**
     * @param  array<int, Price|null>  $prices
     */
    private static function cheapest(array $prices): ?Money
    {
        $cheapest = null;

        foreach ($prices as $price) {
            if ($price === null || $price->amount->kopecks <= 0) {
                continue;
            }

            if ($cheapest === null || $price->amount->lessThan($cheapest)) {
                $cheapest = $price->amount;
            }
        }

        return $cheapest;
    }
}
