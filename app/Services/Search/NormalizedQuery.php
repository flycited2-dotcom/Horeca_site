<?php

namespace App\Services\Search;

/**
 * A search query in the form products.search_text is stored in (TZ §8.4).
 */
final readonly class NormalizedQuery
{
    /**
     * @param  list<string>  $words
     */
    public function __construct(
        public string $text,
        public array $words,
        public string $compact,
    ) {}

    public function isSearchable(): bool
    {
        return mb_strlen($this->text) >= QueryNormalizer::MIN_LENGTH;
    }

    /**
     * Articles, 1C codes and models almost always contain digits; a plain word such as
     * "плита" is not treated as a code.
     */
    public function looksLikeCode(): bool
    {
        return preg_match('/\d/', $this->compact) === 1;
    }
}
