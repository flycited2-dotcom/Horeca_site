<?php

use App\Actions\Catalog\RedirectMovedAddress;
use App\Models\Category;
use App\Models\Product;
use App\Models\Redirect;

it('frees the address when a new page takes it', function () {
    $product = Product::factory()->create(['slug' => 'plita-staraya']);
    $product->update(['slug' => 'plita-novaya']);

    expect(Redirect::query()->where('from_path', '/product/plita-staraya')->exists())->toBeTrue();

    // The supplier sends a different product and it gets the address that was freed.
    Product::factory()->create(['slug' => 'plita-staraya']);

    expect(Redirect::query()->where('from_path', '/product/plita-staraya')->exists())->toBeFalse()
        ->and(Redirect::query()->where('from_path', '/product/plita-novaya')->exists())->toBeFalse();
});

it('frees an address on demand', function () {
    Redirect::factory()->create(['from_path' => '/catalog/plity', 'to_path' => '/catalog/plity-elektricheskie']);

    app(RedirectMovedAddress::class)->free('/catalog/plity');

    expect(Redirect::query()->count())->toBe(0);
});

it('does not touch the redirects of other pages', function () {
    $category = Category::factory()->create(['slug' => 'shkafy']);
    $category->update(['slug' => 'shkafy-holodilnye']);

    Product::factory()->create(['slug' => 'shkafy']);

    expect(Redirect::query()->where('from_path', '/catalog/shkafy')->value('to_path'))->toBe('/catalog/shkafy-holodilnye');
});
