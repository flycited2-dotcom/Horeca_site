<?php

use App\Services\Catalog\ListingSlice;
use Illuminate\Database\Eloquent\Collection;

function slice(int $total, int $first, int $last): ListingSlice
{
    return new ListingSlice(new Collection, $total, $first, $last, perPage: 24);
}

it('numbers the pages with gaps around the ones on screen', function (int $first, int $last, array $links) {
    expect(slice(288, $first, $last)->pageLinks())->toBe($links);
})->with([
    'start' => [1, 1, [1, 2, 3, 4, 5, null, 12]],
    'middle' => [6, 6, [1, null, 5, 6, 7, null, 12]],
    'end' => [12, 12, [1, null, 8, 9, 10, 11, 12]],
    'loaded with «Показать ещё»' => [1, 3, [1, 2, 3, 4, 5, null, 12]],
]);

it('lists every page of a short listing', function () {
    expect(slice(100, 1, 1)->pageLinks())->toBe([1, 2, 3, 4, 5]);
});

it('tells how far the customer has scrolled', function () {
    $slice = slice(96, 1, 1);

    expect($slice->from())->toBe(1)
        ->and($slice->to())->toBe(24)
        ->and($slice->progress())->toBe(25)
        ->and($slice->nextCount())->toBe(24)
        ->and($slice->isOnScreen(1))->toBeTrue()
        ->and($slice->isOnScreen(2))->toBeFalse();
});

it('adds only the rest on the last «Показать ещё»', function () {
    $slice = slice(50, 1, 2);

    expect($slice->to())->toBe(48)
        ->and($slice->nextCount())->toBe(2)
        ->and($slice->hasMore())->toBeTrue();
});

it('shows an empty listing as complete', function () {
    $slice = slice(0, 1, 1);

    expect($slice->from())->toBe(0)
        ->and($slice->to())->toBe(0)
        ->and($slice->progress())->toBe(100)
        ->and($slice->hasMore())->toBeFalse()
        ->and($slice->pageLinks())->toBe([1]);
});
