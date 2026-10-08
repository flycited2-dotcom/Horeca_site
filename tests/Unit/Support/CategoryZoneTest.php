<?php

use App\Support\CategoryZone;

it('takes the zone from the icon the manager chose', function (?string $icon, string $name, string $zone) {
    expect(CategoryZone::of($icon, $name))->toBe($zone);
})->with([
    ['refrigeration', 'Витрины', CategoryZone::COLD],
    ['thermal', 'Линии раздачи', CategoryZone::HOT],
    ['neutral', 'Холодильное оборудование', CategoryZone::NEUTRAL],
    ['dishwashing', 'Посудомоечное оборудование', CategoryZone::NEUTRAL],
]);

it('reads the zone from the name of a section without an icon', function (string $name, string $zone) {
    expect(CategoryZone::of(null, $name))->toBe($zone);
})->with([
    ['Холодильное оборудование', CategoryZone::COLD],
    ['Морозильные лари', CategoryZone::COLD],
    ['Льдогенераторы', CategoryZone::COLD],
    ['Тепловое и технологическое оборудование', CategoryZone::HOT],
    ['Печи для пиццы', CategoryZone::HOT],
    ['Фритюрницы', CategoryZone::HOT],
    ['Нейтральное оборудование', CategoryZone::NEUTRAL],
    ['Хлебопекарное оборудование', CategoryZone::NEUTRAL],
    ['Кофемолки и кофемашины', CategoryZone::NEUTRAL],
]);
