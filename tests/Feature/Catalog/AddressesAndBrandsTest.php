<?php

use App\Actions\Catalog\MergeBrands;
use App\Actions\Catalog\SetCategoriesActive;
use App\Enums\SupplierRefEntity;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\SupplierRef;
use App\Services\Catalog\CategoryTree;
use Illuminate\Support\Facades\Storage;

it('redirects the old address of a product after a manual change', function () {
    $product = Product::factory()->create(['slug' => 'shkaf-staryy']);

    $product->update(['slug' => 'shkaf-novyy']);

    expect(Redirect::query()->where('from_path', '/product/shkaf-staryy')->value('to_path'))->toBe('/product/shkaf-novyy');
});

it('never builds a chain of redirects', function () {
    $product = Product::factory()->create(['slug' => 'a']);

    $product->update(['slug' => 'b']);
    $product->update(['slug' => 'c']);

    expect(Redirect::query()->where('from_path', '/product/a')->value('to_path'))->toBe('/product/c')
        ->and(Redirect::query()->where('from_path', '/product/b')->value('to_path'))->toBe('/product/c');
});

it('drops the redirect when an address is renamed back', function () {
    $category = Category::factory()->create(['slug' => 'plity']);

    $category->update(['slug' => 'plity-elektricheskie']);
    $category->update(['slug' => 'plity']);

    expect(Redirect::query()->where('from_path', '/catalog/plity')->exists())->toBeFalse()
        ->and(Redirect::query()->where('from_path', '/catalog/plity-elektricheskie')->value('to_path'))->toBe('/catalog/plity');
});

it('leaves addresses alone when nothing but the name changes', function () {
    $brand = Brand::factory()->create();

    $brand->update(['name' => 'Новое имя']);

    expect(Redirect::query()->count())->toBe(0);
});

it('merges a duplicate brand into the kept one', function () {
    $kept = Brand::factory()->create(['name' => 'Rosso', 'slug' => 'rosso']);
    $merged = Brand::factory()->create(['name' => 'Rosso (Китай)', 'slug' => 'rosso-kitay']);
    $products = Product::factory()->count(3)->create(['brand_id' => $merged->id]);

    $moved = app(MergeBrands::class)->handle($merged, $kept);

    expect($moved)->toBe(3)
        ->and(Brand::query()->find($merged->id))->toBeNull()
        ->and(Product::query()->whereKey($products->modelKeys())->pluck('brand_id')->unique()->all())->toBe([$kept->id])
        ->and(Product::query()->whereKey($products->modelKeys())->first()?->search_text)->toContain('rosso')
        ->and(Redirect::query()->where('from_path', '/brands/rosso-kitay')->value('to_path'))->toBe('/brands/rosso');
});

it('keeps the merge after the next import', function () {
    Storage::fake('local');
    $supplier = rosholodSupplier();
    $profile = rosholodProfile('rosholod.catalog_xml');
    fakeRosholodFeeds();
    runImport($profile);

    $merged = Brand::query()->where('name', 'Rosso (Китай)')->sole();
    $kept = Brand::factory()->create(['name' => 'Rosso', 'slug' => 'rosso']);

    app(MergeBrands::class)->handle($merged, $kept);
    runImport($profile, force: true);

    expect(Brand::query()->where('name', 'Rosso (Китай)')->exists())->toBeFalse()
        ->and(SupplierRef::query()->where('supplier_id', $supplier->id)->where('entity', SupplierRefEntity::Brand)->where('name', 'Rosso (Китай)')->value('local_id'))->toBe($kept->id)
        ->and(Product::query()->where('brand_id', $kept->id)->count())->toBeGreaterThan(0);
});

it('refuses to merge a brand into itself', function () {
    $brand = Brand::factory()->create();

    app(MergeBrands::class)->handle($brand, $brand);
})->throws(InvalidArgumentException::class);

it('switches categories on in one go', function () {
    $categories = Category::factory()->count(3)->create(['is_active' => false]);

    $changed = app(SetCategoriesActive::class)->handle($categories->modelKeys(), true);

    expect($changed)->toBe(3)
        ->and(Category::query()->where('is_active', true)->count())->toBe(3);
});

it('builds full paths and never offers a category as its own parent', function () {
    $root = Category::factory()->create(['name' => 'Холодильное оборудование', 'parent_id' => null]);
    $child = Category::factory()->create(['name' => 'Шкафы', 'parent_id' => $root->id]);
    $grandchild = Category::factory()->create(['name' => 'Шкафы среднетемпературные', 'parent_id' => $child->id]);
    $other = Category::factory()->create(['name' => 'Тепловое оборудование', 'parent_id' => null]);

    $tree = new CategoryTree;

    expect($tree->path($grandchild->id))->toBe('Холодильное оборудование › Шкафы › Шкафы среднетемпературные')
        ->and($tree->branch($root->id))->toEqualCanonicalizing([$root->id, $child->id, $grandchild->id])
        ->and(array_keys($tree->parentOptions($child->id)))->toEqualCanonicalizing([$root->id, $other->id]);
});
