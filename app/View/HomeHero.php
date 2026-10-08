<?php

namespace App\View;

use App\Support\CategoryZone;

/**
 * Первый экран главной (облик «Холод и жар», ТЗ §8.1): цифры каталога рядом с заголовком.
 * Всё считается из тех же корневых разделов, что и плитки ниже: позиции, производители
 * и модели «холодных» и «горячих» разделов. Цифра без моделей не показывается.
 */
final readonly class HomeHero
{
    /**
     * @param  list<array{value: int, label: string, zone: ?string}>  $facts
     */
    public function __construct(public array $facts) {}

    /**
     * @param  array{products: int, brands: int, sections: list<array{name: string, icon: ?string, products_count: int}>}  $home
     */
    public static function from(array $home): self
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

        return new self(array_values(array_filter($facts, fn (array $fact): bool => $fact['value'] > 0)));
    }
}
