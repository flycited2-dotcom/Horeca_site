<?php

use App\Enums\Availability;
use App\Enums\ImportRunStatus;
use App\Enums\SupplierRefEntity;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\SupplierRef;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->supplier = rosholodSupplier();
    $this->profile = rosholodProfile('rosholod.catalog_xml');

    fakeRosholodFeeds();
});

it('mirrors the supplier tree into switched-off storefront categories', function () {
    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Success)
        ->and(SupplierRef::query()->where('entity', SupplierRefEntity::Category)->count())->toBe(324)
        ->and(Category::query()->count())->toBe(324)
        ->and(Category::query()->where('is_active', true)->count())->toBe(0)
        ->and(Category::query()->roots()->count())->toBe(25);

    $child = Category::query()->where('name', 'Блендер')->sole();

    expect($child->parent?->name)->toBe('Электромеханическое оборудование')
        ->and($child->slug)->toBe('blender');
});

it('creates brands with the names as the supplier sends them', function () {
    runImport($this->profile);

    expect(Brand::query()->count())->toBe(7)
        ->and(Brand::query()->where('name', 'Rosso (Китай)')->exists())->toBeTrue()
        ->and(SupplierRef::query()->where('entity', SupplierRefEntity::Brand)->count())->toBe(7);
});

it('creates products with the price, the address and the search line', function () {
    $run = runImport($this->profile);

    expect($run->created)->toBe(56)
        ->and($run->updated)->toBe(0)
        ->and($run->errors)->toBe(0)
        ->and(Product::query()->count())->toBe(56);

    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();

    expect($product->name)->toBe('Abat Decalc (5л)-жидкое кислотное концентрированное средство для декальцинации бойлера для ПКА')
        ->and($product->supplier_code)->toBe('ЦБ-Ц0017339')
        ->and($product->sku)->toBe('12000137117')
        ->and($product->rrp_price?->toDecimal())->toBe('2948.00')
        ->and($product->retail_price?->toDecimal())->toBe('2948.00')
        ->and($product->brand?->name)->toBe('Abat')
        ->and($product->category?->name)->toBe('Химия для печей и пароконвектоматов')
        ->and($product->availability)->toBe(Availability::OnOrder)
        ->and($product->is_visible)->toBeTrue()
        ->and($product->slug)->not->toBeEmpty()
        ->and($product->search_text)->toContain('abat')
        ->and($product->search_text)->toContain('цб-ц0017339')
        ->and($product->last_synced_at)->not->toBeNull();
});

it('leaves a product without a price to be asked about', function () {
    runImport($this->profile);

    $onRequest = Product::query()->whereNull('retail_price')->get();

    expect($onRequest)->toHaveCount(7)
        ->and($onRequest->every(fn (Product $product): bool => $product->isPriceOnRequest()))->toBeTrue();
});

it('keeps an empty article empty instead of inventing one', function () {
    runImport($this->profile);

    expect(Product::query()->whereNull('sku')->count())->toBe(7);
});

it('reports a repeated category name and takes the first one', function () {
    $run = runImport($this->profile);

    expect($run->log)->toBeArray()
        ->and(implode("\n", $run->log))->toContain('Морозильные камеры и лари');
});

it('counts the products of every category including the children', function () {
    runImport($this->profile);

    $category = Category::query()->where('name', 'Химия для печей и пароконвектоматов')->sole();
    $root = Category::query()->where('name', 'Прочее (неликвид + комплектующие)')->sole();

    expect($category->products_count)->toBe(Product::query()->where('category_id', $category->id)->count())
        ->and($category->products_count)->toBeGreaterThan(0)
        ->and($root->products_count)->toBeGreaterThanOrEqual(0);
});

it('does nothing on a second run of the same file', function () {
    runImport($this->profile);

    $second = runImport($this->profile);

    expect($second->status)->toBe(ImportRunStatus::Skipped)
        ->and($second->log)->toContain(__('import.messages.same_file'));
});

it('reads the same file again on demand and changes nothing', function () {
    $first = runImport($this->profile);
    $second = runImport($this->profile, force: true);

    expect($second->status)->toBe(ImportRunStatus::Success)
        ->and($second->created)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(56)
        ->and(Product::query()->count())->toBe(56)
        ->and($second->id)->not->toBe($first->id);
});

it('clears the staging table after a run', function () {
    $run = runImport($this->profile);

    expect(ImportRow::query()->where('import_run_id', $run->id)->count())->toBe(0);
});

it('keeps the fields the manager has changed by hand', function () {
    runImport($this->profile);

    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
    $ownCategory = Category::query()->create(['name' => 'Своя категория', 'slug' => 'svoya-kategoriya']);

    $product->forceFill([
        'name' => 'Своё название',
        'category_id' => $ownCategory->id,
        'locked_fields' => ['name', 'category_id'],
        'source_hash' => 'изменено вручную',
    ])->save();

    $run = runImport($this->profile, force: true);
    $product->refresh();

    expect($run->status)->toBe(ImportRunStatus::Success)
        ->and($product->name)->toBe('Своё название')
        ->and($product->category_id)->toBe($ownCategory->id)
        ->and($product->rrp_price?->toDecimal())->toBe('2948.00');
});

it('never changes the address of a product that already exists', function () {
    runImport($this->profile);

    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
    $product->forceFill(['slug' => 'ruchnoy-adres', 'source_hash' => null])->save();

    runImport($this->profile, force: true);

    expect($product->refresh()->slug)->toBe('ruchnoy-adres');
});

it('leaves the storefront category alone once the manager has touched it', function () {
    runImport($this->profile);

    $category = Category::query()->where('name', 'Блендер')->sole();
    $category->forceFill(['name' => 'Блендеры для бара', 'is_active' => true])->save();

    runImport($this->profile, force: true);

    expect($category->refresh()->name)->toBe('Блендеры для бара')
        ->and($category->is_active)->toBeTrue()
        ->and(SupplierRef::query()->where('local_id', $category->id)->where('entity', SupplierRefEntity::Category)->value('name'))->toBe('Блендер');
});
