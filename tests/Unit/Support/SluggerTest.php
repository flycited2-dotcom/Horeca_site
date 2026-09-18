<?php

use App\Support\Slugger;

it('transliterates a Russian name', function (string $source, string $slug) {
    expect(Slugger::make($source))->toBe($slug);
})->with([
    ['Шкаф холодильный ШХ-0,7', 'shkaf-holodilnyy-shh-07'],
    ['Abat ПКА 6-1/1ПМ2', 'abat-pka-6-11pm2'],
    ['   Плита   индукционная   ', 'plita-induktsionnaya'],
]);

it('cuts a long name to the limit without leaving a dash at the end', function () {
    $slug = Slugger::make(str_repeat('Холодильник ', 40), 120);

    expect(strlen($slug))->toBeLessThanOrEqual(120)
        ->and($slug)->not->toEndWith('-');
});

it('adds the supplier code when the address is taken', function () {
    $taken = ['shkaf-holodilnyy' => true];

    $slug = Slugger::unique(
        'Шкаф холодильный',
        fn (string $candidate): bool => isset($taken[$candidate]),
        fallback: 'ЦБ-Ц0017339',
    );

    expect($slug)->toBe('shkaf-holodilnyy-tsb-ts0017339');
});

it('falls back to a counter when the code does not help either', function () {
    $taken = ['shkaf' => true, 'shkaf-tsb-1' => true];

    $slug = Slugger::unique(
        'Шкаф',
        fn (string $candidate): bool => isset($taken[$candidate]),
        fallback: 'ЦБ-1',
    );

    expect($slug)->toBe('shkaf-2');
});

it('keeps the tail inside the limit', function () {
    $slug = Slugger::unique(
        str_repeat('Шкаф ', 30),
        fn (string $candidate): bool => ! str_ends_with($candidate, '-2'),
        limit: 40,
    );

    expect(strlen($slug))->toBeLessThanOrEqual(40)
        ->and($slug)->toEndWith('-2');
});

it('never returns an empty address', function () {
    expect(Slugger::unique('«»', fn (): bool => false))->toBe('tovar')
        ->and(Slugger::unique('«»', fn (): bool => false, fallback: 'ЦБ-7'))->toBe('tsb-7');
});
