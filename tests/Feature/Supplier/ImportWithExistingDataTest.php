<?php

use App\Enums\ImportRunStatus;
use App\Enums\SupplierRefEntity;
use App\Models\Brand;
use App\Models\Product;
use App\Models\SupplierRef;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Storage;

/**
 * The shop is never empty when an import arrives: demo data, records created by hand and
 * matches the manager has cleared all exist. Nothing of that may break a run.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->supplier = rosholodSupplier();
    $this->catalog = rosholodProfile('rosholod.catalog_xml');
    $this->stock = rosholodProfile('rosholod.stock_xml');

    fakeRosholodFeeds();
});

it('links a warehouse the shop already has instead of creating a second one', function () {
    Warehouse::factory()->create(['supplier_id' => $this->supplier->id, 'name' => 'Волжск', 'slug' => 'volzhsk']);

    runImport($this->catalog);
    $run = runImport($this->stock);

    expect($run->status)->toBe(ImportRunStatus::Success)
        ->and(Warehouse::query()->where('name', 'Волжск')->count())->toBe(1)
        ->and(SupplierRef::query()->where('entity', SupplierRefEntity::Warehouse)->where('name', 'Волжск')->value('local_id'))
        ->toBe(Warehouse::query()->where('name', 'Волжск')->value('id'));
});

it('links a brand the shop already has instead of creating a second one', function () {
    $brand = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);

    $run = runImport($this->catalog);

    expect($run->status)->toBe(ImportRunStatus::Success)
        ->and(Brand::query()->where('name', 'Abat')->count())->toBe(1)
        ->and(Product::query()->where('brand_id', $brand->id)->count())->toBeGreaterThan(0);
});

it('keeps the search line in step with a brand pinned by the manager', function () {
    runImport($this->catalog);

    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
    $ownBrand = Brand::factory()->create(['name' => 'Абат Россия']);

    $product->forceFill([
        'brand_id' => $ownBrand->id,
        'locked_fields' => ['brand_id'],
        'source_hash' => null,
    ])->save();

    runImport($this->catalog, force: true);

    // "Abat" is still inside the supplier name of the product, but the brand of the line
    // is the one the manager pinned.
    expect($product->refresh()->brand_id)->toBe($ownBrand->id)
        ->and($product->search_text)->toContain('абат россия');
});
