<?php

namespace App\View;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Строки таблицы сравнения (ТЗ §8.5, макет — экран 13): сначала «Критично для монтажа»
 * (габариты, масса, главные характеристики), затем остальное. Строка, где значения у всех
 * моделей одинаковы, — совпадающая: по умолчанию она свёрнута в сноску под таблицей.
 * Строка, которой нет ни у одной модели, не выводится.
 */
final readonly class ComparisonTable
{
    /**
     * @param  list<array{label: string, mono: bool, install: bool, values: list<?string>, same: bool}>  $rows
     */
    private function __construct(public array $rows) {}

    /**
     * @param  Collection<int, Product>  $products  in the order of the columns
     */
    public static function of(Collection $products): self
    {
        $facts = $products->map(fn (Product $product): array => (new ProductFacts($product))->comparable())->values()->all();

        // A row takes its label from the first model that has it; the order is the order of first appearance.
        $keys = [];

        foreach ($facts as $rows) {
            foreach ($rows as $key => $row) {
                $keys[$key] ??= $row;
            }
        }

        $rows = [];

        foreach ($keys as $key => $row) {
            $values = array_map(fn (array $product): ?string => $product[$key]['value'] ?? null, $facts);

            $rows[] = [
                'label' => $row['label'],
                'mono' => $row['mono'],
                'install' => $row['install'],
                'values' => $values,
                'same' => ! in_array(null, $values, true) && count(array_unique($values)) === 1,
            ];
        }

        // Stable sort: the installation rows go first, each group keeps its order.
        usort($rows, fn (array $a, array $b): int => $b['install'] <=> $a['install']);

        return new self($rows);
    }

    public function total(): int
    {
        return count($this->rows);
    }

    public function differences(): int
    {
        return count(array_filter($this->rows, fn (array $row): bool => ! $row['same']));
    }

    /**
     * The rows on screen by group: every row, or only those where the models differ.
     *
     * @return array<'install'|'specs', non-empty-list<array{label: string, mono: bool, install: bool, values: list<?string>, same: bool}>>
     */
    public function groups(bool $all): array
    {
        $groups = [];

        foreach ($this->rows as $row) {
            if ($all || ! $row['same']) {
                $groups[$row['install'] ? 'install' : 'specs'][] = $row;
            }
        }

        return $groups;
    }

    /**
     * «бренд Abat, масса 18,5 кг» — the first rows that are the same for every model, for the
     * note under the table, and how many more there are.
     *
     * @return array{list: string, more: int}
     */
    public function sameSummary(int $shown = 5): array
    {
        $same = array_values(array_filter($this->rows, fn (array $row): bool => $row['same']));

        $list = array_map(
            fn (array $row): string => self::lowerFirst($row['label']).' '.$row['values'][0],
            array_slice($same, 0, $shown),
        );

        return ['list' => implode(', ', $list), 'more' => max(0, count($same) - $shown)];
    }

    /**
     * «Масса» → «масса», but «GN» and «ТЭН» stay as they are.
     */
    private static function lowerFirst(string $label): string
    {
        $second = mb_substr($label, 1, 1);

        if ($second === '' || mb_strtolower($second) !== $second) {
            return $label;
        }

        return mb_strtolower(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }
}
