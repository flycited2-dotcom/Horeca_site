<?php

use App\Services\Search\QueryNormalizer;

beforeEach(function () {
    $this->normalizer = new QueryNormalizer;
});

it('lower-cases the query and writes ё as е', function () {
    $query = $this->normalizer->normalize('  Ёмкость   ПРИЁМНАЯ ');

    expect($query->text)->toBe('емкость приемная')
        ->and($query->words)->toBe(['емкость', 'приемная']);
});

it('builds the compact form of a code', function () {
    $query = $this->normalizer->normalize('КИП-27Н-3,5');

    expect($query->compact)->toBe('кип27н35')
        ->and($query->looksLikeCode())->toBeTrue();
});

it('does not take a plain word for a code', function () {
    expect($this->normalizer->normalize('плита')->looksLikeCode())->toBeFalse();
});

it('switches the keyboard layout both ways', function (string $typed, string $meant) {
    expect($this->normalizer->switchLayout($typed))->toBe($meant);
})->with([
    ['ghbdtn', 'привет'],
    ['gkbnf', 'плита'],
    ['руддщ', 'hello'],
    ['Gfhjrjydtrnjvfn', 'пароконвектомат'],
    ['[fqth', 'хайер'],
]);

it('keeps digits and punctuation of model numbers when switching', function () {
    expect($this->normalizer->switchLayout('rbg-27y-3,5'))->toBe('кип-27н-3,5');
});

it('ignores a query shorter than two characters', function () {
    expect($this->normalizer->normalize('а')->isSearchable())->toBeFalse()
        ->and($this->normalizer->normalize('шх')->isSearchable())->toBeTrue();
});

it('keeps at most eight words', function () {
    $query = $this->normalizer->normalize('один два три четыре пять шесть семь восемь девять десять');

    expect($query->words)->toHaveCount(QueryNormalizer::MAX_WORDS);
});
