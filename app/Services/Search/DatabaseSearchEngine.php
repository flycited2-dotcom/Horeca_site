<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * LIKE over products.search_text (TZ §8.4) with the four levels of relevance:
 * 1. the compact query is exactly an article, a 1C code or a model;
 * 2. the name or the model starts with the query;
 * 3. every word of the query is in the line;
 * 4. at least one word is.
 * Plain SQL that runs the same on MariaDB and MySQL (TZ §3).
 */
final class DatabaseSearchEngine implements SearchEngineInterface
{
    public function apply(Builder $products, NormalizedQuery $query): Builder
    {
        if ($query->words === []) {
            return $products->whereRaw('1 = 0');
        }

        $words = array_map(self::like(...), $query->words);
        $compact = $query->looksLikeCode() ? self::like($query->compact) : null;

        $products->where(function (Builder $where) use ($words, $compact): void {
            foreach ($words as $word) {
                $where->orWhere('search_text', 'like', "%{$word}%");
            }

            if ($compact !== null) {
                $where->orWhere('search_text', 'like', "%{$compact}%");
            }
        });

        $cases = [];
        $bindings = [];

        if ($compact !== null) {
            $cases[] = "WHEN CONCAT(' ', search_text, ' ') LIKE ? THEN 1";
            $bindings[] = "% {$compact} %";
        }

        $cases[] = 'WHEN search_text LIKE ? OR model LIKE ? THEN 2';
        $bindings[] = self::like($query->text).'%';
        $bindings[] = self::like($query->text).'%';

        if (count($words) > 1) {
            $cases[] = 'WHEN '.implode(' AND ', array_fill(0, count($words), 'search_text LIKE ?')).' THEN 3';
            array_push($bindings, ...array_map(fn (string $word): string => "%{$word}%", $words));
        }

        return $products
            ->orderByRaw('CASE '.implode(' ', $cases).' ELSE 4 END', $bindings)
            ->orderBy('availability_rank')
            ->orderByDesc('popularity')
            ->orderBy('name');
    }

    /**
     * Escapes the LIKE wildcards: "50%" searches for "50%", not for anything after "50".
     */
    private static function like(string $value): string
    {
        return addcslashes($value, '\%_');
    }
}
