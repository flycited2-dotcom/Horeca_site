<?php

use App\Actions\Catalog\RestoreSupplierValue;
use App\Actions\Catalog\SaveProductByManager;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->supplier = rosholodSupplier();
    $this->profile = rosholodProfile('rosholod.catalog_xml');

    fakeRosholodFeeds();
    runImport($this->profile);

    $this->product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
});

it('locks every field the import could overwrite', function () {
    $supplierFields = array_values(array_diff(FeedCapabilities::PRODUCT_FIELDS, ['stocks']));

    expect(array_diff($supplierFields, SaveProductByManager::LOCKABLE_FIELDS))->toBe([]);
});

it('locks the fields the manager changed and only them', function () {
    app(SaveProductByManager::class)->handle($this->product, [
        'name' => 'Средство для декальцинации Abat Decalc, 5 л',
        'sku' => $this->product->sku,
        'is_hit' => true,
    ]);

    expect($this->product->refresh()->locked_fields)->toBe(['name'])
        ->and($this->product->is_hit)->toBeTrue();
});

it('keeps a manual name, price and category through the next import', function () {
    $category = Category::query()->create(['name' => 'Химия', 'slug' => 'khimiya']);

    app(SaveProductByManager::class)->handle($this->product, [
        'name' => 'Своё название',
        'retail_price' => Money::ofRubles(2500),
        'category_id' => $category->id,
    ]);

    runImport($this->profile, force: true);

    $this->product->refresh();

    expect($this->product->name)->toBe('Своё название')
        ->and($this->product->retail_price?->toDecimal())->toBe('2500.00')
        ->and($this->product->category_id)->toBe($category->id)
        ->and($this->product->locked_fields)->toEqualCanonicalizing(['name', 'retail_price', 'category_id']);
});

it('rebuilds the search line after a manual rename', function () {
    app(SaveProductByManager::class)->handle($this->product, ['name' => 'Декальцинатор для пароконвектомата']);

    expect($this->product->refresh()->search_text)->toContain('декальцинатор для пароконвектомата')
        ->and($this->product->search_text)->toContain('abat');
});

it('gives the field back to the supplier on the next import', function () {
    app(SaveProductByManager::class)->handle($this->product, ['name' => 'Своё название']);

    app(RestoreSupplierValue::class)->handle($this->product, 'name');

    expect($this->product->refresh()->locked_fields)->toBeNull();

    runImport($this->profile, force: true);

    expect($this->product->refresh()->name)
        ->toBe('Abat Decalc (5л)-жидкое кислотное концентрированное средство для декальцинации бойлера для ПКА');
});

it('keeps the other locks when one field is given back', function () {
    app(SaveProductByManager::class)->handle($this->product, ['name' => 'Своё', 'model' => 'Своя модель']);

    app(RestoreSupplierValue::class)->handle($this->product, 'name');

    expect($this->product->refresh()->locked_fields)->toBe(['model']);
});

it('refuses to unlock a field the import never writes', function () {
    app(RestoreSupplierValue::class)->handle($this->product, 'seo_text');
})->throws(InvalidArgumentException::class);

it('recounts the categories when a product moves', function () {
    $from = $this->product->category;
    $to = Category::query()->create(['name' => 'Химия', 'slug' => 'khimiya']);
    $before = $from->products_count;

    app(SaveProductByManager::class)->handle($this->product, ['category_id' => $to->id]);

    expect($to->refresh()->products_count)->toBe(1)
        ->and($from->refresh()->products_count)->toBe($before - 1);
});

it('uses the new brand name in the search line', function () {
    $brand = Brand::query()->create(['name' => 'Абат', 'slug' => 'abat-ru']);

    app(SaveProductByManager::class)->handle($this->product, ['brand_id' => $brand->id]);

    expect($this->product->refresh()->search_text)->toContain('абат')
        ->and($this->product->locked_fields)->toBe(['brand_id']);
});
