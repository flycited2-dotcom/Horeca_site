<?php

use App\Actions\Catalog\RecalculateCategoryCounts;
use App\Models\Category;
use App\Models\Product;

it('counts visible products that are not discontinued, including subcategories', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();
    $empty = Category::factory()->create(['products_count' => 99]);

    Product::factory()->count(2)->create(['category_id' => $child->id]);
    Product::factory()->create(['category_id' => $root->id]);
    Product::factory()->discontinued()->create(['category_id' => $child->id]);
    Product::factory()->create(['category_id' => $child->id, 'is_visible' => false]);
    Product::factory()->create(['category_id' => $child->id])->delete();

    app(RecalculateCategoryCounts::class)->handle();

    expect($child->fresh()->products_count)->toBe(2)
        ->and($root->fresh()->products_count)->toBe(3)
        ->and($empty->fresh()->products_count)->toBe(0);
});

it('fails loudly on a cyclic category tree', function () {
    $first = Category::factory()->create();
    $second = Category::factory()->childOf($first)->create();
    $first->update(['parent_id' => $second->id]);

    app(RecalculateCategoryCounts::class)->handle();
})->throws(LogicException::class);
