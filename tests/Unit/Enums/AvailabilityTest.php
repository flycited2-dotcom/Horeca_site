<?php

use App\Enums\Availability;
use App\Enums\UserRole;

it('sorts availability as in TZ §6.5', function (Availability $availability, int $rank) {
    expect($availability->rank())->toBe($rank);
})->with([
    [Availability::InStock, 1],
    [Availability::Low, 1],
    [Availability::Incoming, 2],
    [Availability::OnOrder, 3],
    [Availability::Discontinued, 4],
]);

it('treats managers and administrators as staff', function () {
    expect(UserRole::Customer->isStaff())->toBeFalse()
        ->and(UserRole::Manager->isStaff())->toBeTrue()
        ->and(UserRole::Admin->isStaff())->toBeTrue();
});

it('names the schema.org availability of every status', function () {
    expect(array_map(fn (Availability $availability): string => $availability->schemaOrg(), Availability::cases()))->toBe([
        'https://schema.org/InStock',
        'https://schema.org/LimitedAvailability',
        'https://schema.org/BackOrder',
        'https://schema.org/MadeToOrder',
        'https://schema.org/Discontinued',
    ]);
});
