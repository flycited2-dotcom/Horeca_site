<?php

namespace App\View;

use App\Support\CategoryZone;

/**
 * Плитки разделов на главной (облик «Холод и жар», ТЗ §8.1). Самый крупный «холодный» и самый
 * крупный «горячий» раздел открывают каталог большими витринными плитками — они продолжают
 * цифры первого экрана. Остальные разделы идут обычной сеткой в прежнем порядке.
 */
final readonly class HomeCatalog
{
    /**
     * @param  list<array{id: int, name: string, slug: string, icon: ?string, products_count: int, in_stock: int, children: list<string>, zone: string, featured: bool}>  $tiles
     */
    public function __construct(public array $tiles) {}

    /**
     * @param  list<array{id: int, name: string, slug: string, icon: ?string, products_count: int, in_stock: int, children: list<string>}>  $sections
     */
    public static function from(array $sections): self
    {
        $tiles = array_map(fn (array $section): array => $section + [
            'zone' => CategoryZone::of($section['icon'], $section['name']),
            'featured' => false,
        ], $sections);

        $featured = [];

        foreach ([CategoryZone::COLD, CategoryZone::HOT] as $zone) {
            $leader = null;

            foreach ($tiles as $index => $tile) {
                if ($tile['zone'] === $zone && ($leader === null || $tile['products_count'] > $tiles[$leader]['products_count'])) {
                    $leader = $index;
                }
            }

            if ($leader !== null) {
                $featured[] = ['featured' => true] + $tiles[$leader];
                unset($tiles[$leader]);
            }
        }

        return new self([...$featured, ...array_values($tiles)]);
    }
}
