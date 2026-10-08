<?php

use App\Support\Messengers;

it('makes a Telegram link from a name or a link', function (string $value, string $link) {
    expect(Messengers::telegram($value))->toBe($link);
})->with([
    ['@gastrosnab', 'https://t.me/gastrosnab'],
    ['gastrosnab_crimea', 'https://t.me/gastrosnab_crimea'],
    ['  https://t.me/gastrosnab  ', 'https://t.me/gastrosnab'],
    ['t.me/gastrosnab', 'https://t.me/gastrosnab'],
    ['https://T.ME/+AbCdEf123', 'https://t.me/+AbCdEf123'],
    ['https://telegram.me/gastrosnab', 'https://telegram.me/gastrosnab'],
]);

it('makes a MAX link only from a link of the app', function (string $value, string $link) {
    expect(Messengers::max($value))->toBe($link);
})->with([
    ['https://max.ru/u/f9LHodD0cOKrE8Rl', 'https://max.ru/u/f9LHodD0cOKrE8Rl'],
    ['max.ru/join/AbC123', 'https://max.ru/join/AbC123'],
    ['https://web.max.ru/gastrosnab?start=site', 'https://web.max.ru/gastrosnab?start=site'],
]);

it('refuses what is not a link to the messenger', function (string $messenger, ?string $value) {
    expect(Messengers::link($messenger, $value))->toBeNull();
})->with([
    [Messengers::TELEGRAM, null],
    [Messengers::TELEGRAM, ''],
    [Messengers::TELEGRAM, '@ab'],
    [Messengers::TELEGRAM, 'http://t.me/gastrosnab'],
    [Messengers::TELEGRAM, 'https://t.me/'],
    [Messengers::TELEGRAM, 'https://evil.example/t.me/gastrosnab'],
    [Messengers::TELEGRAM, 'https://t.me.evil.example/gastrosnab'],
    [Messengers::TELEGRAM, 'https://user@t.me/gastrosnab'],
    [Messengers::TELEGRAM, 'javascript:alert(1)'],
    [Messengers::TELEGRAM, 'https://t.me/gastro snab'],
    [Messengers::MAX, 'gastrosnab'],
    [Messengers::MAX, 'https://max.ru'],
    [Messengers::MAX, 'https://t.me/gastrosnab'],
    ['whatsapp', 'https://wa.me/79780000000'],
]);
