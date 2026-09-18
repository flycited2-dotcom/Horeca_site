<?php

use App\Enums\Availability;
use App\Enums\ImportRunStatus;
use App\Enums\SupplierRefEntity;
use App\Enums\WarehouseStockStatus;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\SupplierRef;
use App\Models\Warehouse;
use App\Services\Catalog\AvailabilityCalculator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->supplier = rosholodSupplier();
    $this->catalog = rosholodProfile('rosholod.catalog_xml');
    $this->stock = rosholodProfile('rosholod.stock_xml');

    fakeRosholodFeeds();

    runImport($this->catalog);
});

it('creates the warehouses of the supplier and shows them to the customer', function () {
    $run = runImport($this->stock);

    expect($run->status)->toBe(ImportRunStatus::Success)
        ->and(Warehouse::query()->count())->toBe(17)
        ->and(Warehouse::query()->where('is_visible', false)->count())->toBe(0)
        ->and(SupplierRef::query()->where('entity', SupplierRefEntity::Warehouse)->count())->toBe(17)
        ->and(Warehouse::query()->where('name', 'Симферополь')->exists())->toBeTrue();
});

it('writes the stocks of the products it finds in the catalog', function () {
    $run = runImport($this->stock);

    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
    $stocks = $product->stocks()->with('warehouse')->get();

    expect($run->updated)->toBe(42)
        ->and($stocks)->toHaveCount(2)
        ->and($stocks->firstWhere('warehouse.name', 'Волжск')?->status)->toBe(WarehouseStockStatus::InStock)
        ->and($stocks->firstWhere('warehouse.name', 'Санкт-Петербург')?->status)->toBe(WarehouseStockStatus::Low)
        ->and($stocks->firstWhere('warehouse.name', 'Волжск')?->raw_value)->toBe('много')
        ->and($stocks->firstWhere('warehouse.name', 'Волжск')?->unit)->toBe('шт');
});

it('counts the positions that have no card in the catalog', function () {
    $run = runImport($this->stock);

    expect(implode("\n", $run->log ?? []))->toContain(__('import.records.products_not_in_catalog', ['count' => 3]));
});

it('turns the stocks into the availability of the product', function () {
    runImport($this->stock);

    $inStock = Product::query()->where('availability', Availability::InStock)->count();
    $low = Product::query()->where('availability', Availability::Low)->count();
    $onOrder = Product::query()->where('availability', Availability::OnOrder)->count();

    expect($inStock)->toBeGreaterThan(0)
        ->and($inStock + $low + $onOrder)->toBe(56)
        ->and(Product::query()->where('availability', Availability::InStock)->where('availability_rank', '!=', 1)->count())->toBe(0);
});

it('does not count a warehouse the manager has hidden', function () {
    runImport($this->stock);

    $product = Product::query()
        ->whereHas('stocks', fn ($query) => $query->where('status', WarehouseStockStatus::InStock))
        ->firstOrFail();

    Warehouse::query()->update(['is_visible' => false]);

    app(AvailabilityCalculator::class)->recalculateForSupplier($this->supplier->id);

    expect($product->refresh()->availability)->toBe(Availability::OnOrder);
});

it('leaves the names and the categories alone: stocks own neither', function () {
    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
    $before = $product->only(['name', 'category_id', 'brand_id', 'rrp_price', 'retail_price', 'source_hash']);

    runImport($this->stock);

    expect($product->refresh()->only(array_keys($before)))->toEqual($before);
});

it('replaces the whole snapshot on the next run', function () {
    runImport($this->stock);

    $product = Product::query()
        ->whereHas('stocks', fn ($query) => $query->where('status', WarehouseStockStatus::InStock))
        ->firstOrFail();

    $before = ProductStock::query()->count();

    $withoutStocks = (string) preg_replace('~<stocks>.*?</stocks>~s', '<stocks/>', rosholodFixture('stock_sample.xml'));
    fakeRosholodFeeds(stock: $withoutStocks);

    $run = runImport($this->stock);

    expect($run->status)->toBe(ImportRunStatus::Success)
        ->and($before)->toBeGreaterThan(0)
        ->and(ProductStock::query()->count())->toBe(0)
        ->and($product->refresh()->availability)->toBe(Availability::OnOrder);
});

it('keeps a unit the manager has fixed by hand', function () {
    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
    $product->forceFill(['unit' => 'компл', 'locked_fields' => ['unit']])->save();

    runImport($this->stock);

    expect($product->refresh()->unit)->toBe('компл');
});
