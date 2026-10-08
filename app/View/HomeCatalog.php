<?php

namespace App\View;

use App\Support\CategoryZone;
use Illuminate\Support\Str;

/**
 * Плитки разделов на главной (облик «Свечение», ТЗ §8.1). Самый крупный «холодный» и самый
 * крупный «горячий» раздел открывают каталог двумя большими карточками — они же дают фото
 * для первого экрана. Остальные разделы идут ровной сеткой в прежнем порядке. Раздел без
 * фото рисуется линованной плашкой с одним словом из названия, а последний раздел,
 * оставшийся в ряду один, растягивается на ширину сетки.
 */
final readonly class HomeCatalog
{
    /**
     * Столбцов в сетке разделов на планшете: большая карточка занимает 3, обычная — 2.
     */
    private const int TABLET_COLUMNS = 6;

    /**
     * Слова, которые есть в названии почти каждого раздела и ничего не говорят на плашке.
     */
    private const array GENERIC = ['оборудование', 'оборудования'];

    /**
     * @param  list<array{id: int, name: string, slug: string, icon: ?string, products_count: int, in_stock: int, children: list<string>, zone: string, featured: bool, label: string, wide: bool}>  $tiles
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
            'label' => self::label($section['name']),
            'wide' => false,
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

        return new self(self::stretchLast([...$featured, ...array_values($tiles)]));
    }

    /**
     * Витринная плитка «холодного» или «горячего» раздела: её фото и название идут на первый экран.
     *
     * @return array{id: int, name: string, slug: string, icon: ?string, products_count: int, in_stock: int, children: list<string>, zone: string, featured: bool, label: string, wide: bool}|null
     */
    public function lead(string $zone): ?array
    {
        foreach ($this->tiles as $tile) {
            if ($tile['featured'] && $tile['zone'] === $zone) {
                return $tile;
            }
        }

        return null;
    }

    /**
     * Слово для линованной плашки раздела без фото: последнее слово названия («Торговые стеллажи» —
     * «Стеллажи»), а если название кончается словом «оборудование» — первое («Весовое оборудование» —
     * «Весовое»). Своих слов плашка не придумывает.
     */
    private static function label(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return $name;
        }

        $last = $words[array_key_last($words)];
        $word = count($words) > 1 && in_array(mb_strtolower($last), self::GENERIC, true) ? $words[0] : $last;

        return Str::ucfirst($word);
    }

    /**
     * На планшете в ряд встаёт 6 столбцов. Если последняя плитка осталась в своём ряду одна,
     * она занимает весь ряд, а не половину с пустотой справа.
     *
     * @param  list<array<string, mixed>>  $tiles
     * @return list<array<string, mixed>>
     */
    private static function stretchLast(array $tiles): array
    {
        $row = [];
        $used = 0;

        foreach ($tiles as $index => $tile) {
            $span = $tile['featured'] ? 3 : 2;

            if ($used + $span > self::TABLET_COLUMNS) {
                $row = [];
                $used = 0;
            }

            $row[] = $index;
            $used += $span;
        }

        if (count($row) === 1) {
            $tiles[$row[0]]['wide'] = true;
        }

        return $tiles;
    }
}
