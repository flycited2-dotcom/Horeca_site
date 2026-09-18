<?php

namespace App\Services\Search;

/**
 * Turns what the customer typed into the form of products.search_text (TZ §8.4):
 * lower case, "ё" as "е", words, and the compact form of a code ("КИП-27Н-3,5" → "кип27н35").
 * When nothing is found, the query is tried again in the other keyboard layout.
 */
final class QueryNormalizer
{
    public const int MIN_LENGTH = 2;

    /**
     * Longer queries are cut: nobody types more, and every word is one more LIKE.
     */
    public const int MAX_WORDS = 8;

    /**
     * The Russian ЙЦУКЕН keyboard over the Latin QWERTY one. Only letters and the keys that
     * are letters in Russian; "," and "." stay as they are, they are part of model numbers.
     */
    private const array LATIN_TO_CYRILLIC = [
        'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е', 'y' => 'н', 'u' => 'г', 'i' => 'ш',
        'o' => 'щ', 'p' => 'з', '[' => 'х', ']' => 'ъ', 'a' => 'ф', 's' => 'ы', 'd' => 'в', 'f' => 'а',
        'g' => 'п', 'h' => 'р', 'j' => 'о', 'k' => 'л', 'l' => 'д', ';' => 'ж', "'" => 'э', 'z' => 'я',
        'x' => 'ч', 'c' => 'с', 'v' => 'м', 'b' => 'и', 'n' => 'т', 'm' => 'ь', '`' => 'ё',
    ];

    public function normalize(string $query): NormalizedQuery
    {
        $text = SearchTextBuilder::words($query);
        $words = array_slice(array_values(array_unique(array_filter(explode(' ', $text), fn (string $word): bool => $word !== ''))), 0, self::MAX_WORDS);

        return new NormalizedQuery(
            text: implode(' ', $words),
            words: $words,
            compact: SearchTextBuilder::compact($query),
        );
    }

    /**
     * "ghbdtn" → "привет", "руддщ" → "hello".
     */
    public function switchLayout(string $query): string
    {
        static $cyrillicToLatin = null;
        $cyrillicToLatin ??= array_flip(self::LATIN_TO_CYRILLIC);

        $result = '';

        foreach (mb_str_split(mb_strtolower($query)) as $char) {
            $result .= self::LATIN_TO_CYRILLIC[$char] ?? $cyrillicToLatin[$char] ?? $char;
        }

        return $result;
    }
}
