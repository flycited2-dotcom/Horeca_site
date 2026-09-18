<?php

use App\Services\Search\SearchTextBuilder;

beforeEach(function () {
    $this->builder = new SearchTextBuilder;
});

it('puts the name, model, brand and codes into one lower-case line', function () {
    $text = $this->builder->build(
        'Плита индукционная КИП-27Н-3,5',
        'КИП-27Н-3,5',
        '71000019569',
        'ЦБ-Ц0017339',
        'Abat',
    );

    expect($text)->toContain('плита индукционная кип-27н-3,5')
        ->and($text)->toContain('abat')
        ->and($text)->toContain('71000019569')
        ->and($text)->toContain('цб-ц0017339');
});

it('adds the compact form of the codes so that "кип27н35" finds the product', function () {
    $text = $this->builder->build('Плита', 'КИП-27Н-3,5', null, null, null);

    expect($text)->toContain('кип27н35');
});

it('replaces ё with е', function () {
    expect(SearchTextBuilder::words('Ёмкость Приёмная'))->toBe('емкость приемная');
});

it('survives a product without a model, article or brand', function () {
    expect($this->builder->build('Стол', null, null, null, null))->toBe('стол');
});
