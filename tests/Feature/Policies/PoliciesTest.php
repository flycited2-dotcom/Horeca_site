<?php

use App\Models\Cart;
use App\Models\Company;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\User;

it('lets a customer see only their own orders', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $order))->toBeTrue()
        ->and($stranger->can('view', $order))->toBeFalse()
        ->and($owner->can('viewAny', Order::class))->toBeFalse()
        ->and($owner->can('update', $order))->toBeFalse()
        ->and($owner->can('delete', $order))->toBeFalse();
});

it('lets staff work with any order and only administrators delete', function () {
    $order = Order::factory()->create();
    $manager = User::factory()->manager()->create();
    $admin = User::factory()->admin()->create();

    expect($manager->can('viewAny', Order::class))->toBeTrue()
        ->and($manager->can('view', $order))->toBeTrue()
        ->and($manager->can('update', $order))->toBeTrue()
        ->and($manager->can('delete', $order))->toBeFalse()
        ->and($admin->can('delete', $order))->toBeTrue();
});

it('lets a company member view and edit only their own company', function () {
    $member = User::factory()->withApprovedCompany()->create();
    $otherCompany = Company::factory()->create();
    $withoutCompany = User::factory()->create();

    expect($member->can('view', $member->company))->toBeTrue()
        ->and($member->can('update', $member->company))->toBeTrue()
        ->and($member->can('view', $otherCompany))->toBeFalse()
        ->and($withoutCompany->can('view', $otherCompany))->toBeFalse()
        ->and($member->can('delete', $member->company))->toBeFalse()
        ->and(User::factory()->manager()->create()->can('update', $otherCompany))->toBeTrue();
});

it('lets users manage only their own carts', function () {
    $owner = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $owner->id]);
    $guestCart = Cart::factory()->create();

    expect($owner->can('update', $cart))->toBeTrue()
        ->and(User::factory()->create()->can('view', $cart))->toBeFalse()
        ->and($owner->can('view', $guestCart))->toBeFalse();
});

it('lets users remove only their own favorites', function () {
    $favorite = Favorite::factory()->create();

    expect($favorite->user->can('delete', $favorite))->toBeTrue()
        ->and(User::factory()->create()->can('delete', $favorite))->toBeFalse();
});
