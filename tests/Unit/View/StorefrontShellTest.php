<?php

use App\Support\Messengers;
use App\View\StorefrontShell;

/**
 * @param  list<array{key: string, label: string, href: string}>  $messengers
 */
function shellWithMessengers(array $messengers): StorefrontShell
{
    return new StorefrontShell(
        siteName: 'Гастроснаб',
        phones: [],
        email: null,
        schedule: null,
        address: null,
        requisites: null,
        categories: [],
        currentRootId: null,
        inCatalog: false,
        stripPages: [],
        footerPages: [],
        privacyPage: null,
        messengers: $messengers,
    );
}

it('gives the link of one messenger of the store for the bottom bar of the phone', function () {
    $shell = shellWithMessengers([
        ['key' => Messengers::TELEGRAM, 'label' => 'Telegram', 'href' => 'https://t.me/gastrosnab'],
        ['key' => Messengers::MAX, 'label' => 'MAX', 'href' => 'https://max.ru/u/f9LHodD0cOKrE8Rl'],
    ]);

    expect($shell->messenger(Messengers::TELEGRAM))->toBe(['key' => 'telegram', 'label' => 'Telegram', 'href' => 'https://t.me/gastrosnab'])
        ->and($shell->messenger(Messengers::MAX)['href'])->toBe('https://max.ru/u/f9LHodD0cOKrE8Rl');
});

it('has no messenger the settings do not give a valid link for', function () {
    $onlyMax = shellWithMessengers([['key' => Messengers::MAX, 'label' => 'MAX', 'href' => 'https://max.ru/u/f9LHodD0cOKrE8Rl']]);

    expect($onlyMax->messenger(Messengers::TELEGRAM))->toBeNull()
        ->and(shellWithMessengers([])->messenger(Messengers::TELEGRAM))->toBeNull();
});
