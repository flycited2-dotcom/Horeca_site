<?php

use App\Actions\Catalog\ExportProductsXlsx;
use App\Enums\Availability;
use App\Enums\UserRole;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\LatestImports;
use App\Filament\Widgets\UnmappedSupplierRefs;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function staffUser(UserRole $role = UserRole::Manager): User
{
    return User::factory()->create([
        'role' => $role,
        'app_authentication_secret' => app(AppAuthentication::class)->generateSecret(),
    ]);
}

beforeEach(function () {
    $this->actingAs(staffUser());
});

it('opens the catalog screens and the dashboard', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    'товары' => fn () => ProductResource::getUrl('index'),
    'категории' => fn () => CategoryResource::getUrl('index'),
    'бренды' => fn () => BrandResource::getUrl('index'),
    'главная' => fn () => '/manage',
]);

it('lists products with a fixed number of queries', function () {
    $brand = Brand::factory()->create();
    $category = Category::factory()->create();
    Product::factory()->count(25)->create(['brand_id' => $brand->id, 'category_id' => $category->id]);

    DB::enableQueryLog();
    Livewire::test(ListProducts::class)->assertCanSeeTableRecords(Product::query()->limit(25)->get());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(20);
});

it('finds products by the filters of TZ 12', function () {
    $onRequest = Product::factory()->create(['retail_price' => null]);
    $priced = Product::factory()->create(['retail_price' => Money::ofRubles(1000)]);
    $locked = Product::factory()->create();
    $locked->forceFill(['locked_fields' => ['name']])->save();

    Livewire::test(ListProducts::class)
        ->filterTable('price_on_request')
        ->assertCanSeeTableRecords([$onRequest])
        ->assertCanNotSeeTableRecords([$priced]);

    Livewire::test(ListProducts::class)
        ->filterTable('locked')
        ->assertCanSeeTableRecords([$locked])
        ->assertCanNotSeeTableRecords([$priced]);
});

it('finds products of a category together with its children', function () {
    $root = Category::factory()->create(['parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $inChild = Product::factory()->create(['category_id' => $child->id]);
    $elsewhere = Product::factory()->create();

    Livewire::test(ListProducts::class)
        ->filterTable('category', $root->id)
        ->assertCanSeeTableRecords([$inChild])
        ->assertCanNotSeeTableRecords([$elsewhere]);
});

it('saves a product and locks what the manager changed', function () {
    $product = Product::factory()->create(['name' => 'Шкаф', 'rrp_price' => Money::ofRubles(1000)]);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['name' => 'Шкаф холодильный', 'retail_price' => '1500.50'])
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();

    expect($product->name)->toBe('Шкаф холодильный')
        ->and($product->retail_price?->toDecimal())->toBe('1500.50')
        ->and($product->rrp_price?->toDecimal())->toBe('1000.00')
        ->and($product->locked_fields)->toEqualCanonicalizing(['name', 'retail_price']);
});

it('refuses a price that is not rubles and kopecks', function () {
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['retail_price' => '15,5'])
        ->call('save')
        ->assertHasFormErrors(['retail_price']);
});

it('saves the price of a price tier', function () {
    $product = Product::factory()->create();
    $tier = PriceTier::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['prices' => [['price_tier_id' => $tier->id, 'price' => '900']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->prices()->sole()->price?->toDecimal())->toBe('900.00');
});

it('gives a locked field back to the supplier from the form', function () {
    $product = Product::factory()->create();
    $product->forceFill(['locked_fields' => ['name'], 'source_hash' => str_repeat('a', 32)])->save();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->callAction(TestAction::make('restore_name')->schemaComponent('name'));

    expect($product->refresh()->locked_fields)->toBeNull()
        ->and($product->source_hash)->toBeNull();
});

it('moves products to another category and locks the choice', function () {
    $products = Product::factory()->count(2)->create();
    $category = Category::factory()->create();

    Livewire::test(ListProducts::class)
        ->callTableBulkAction('move', $products, data: ['category_id' => $category->id])
        ->assertHasNoTableBulkActionErrors();

    expect(Product::query()->where('category_id', $category->id)->count())->toBe(2)
        ->and($products->first()->refresh()->locked_fields)->toBe(['category_id']);
});

it('hides products from the storefront in bulk', function () {
    $products = Product::factory()->count(2)->create(['is_visible' => true]);

    Livewire::test(ListProducts::class)
        ->callTableBulkAction('hide', $products);

    expect(Product::query()->where('is_visible', true)->count())->toBe(0);
});

it('exports products into a readable spreadsheet', function () {
    $product = Product::factory()->create(['name' => 'Плита индукционная', 'retail_price' => Money::fromDecimal('48605.80')]);
    Product::factory()->create(['retail_price' => null]);

    $response = app(ExportProductsXlsx::class)->handle(Product::query()->pluck('id')->all());

    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    unlink($response->getFile()->getPathname());

    $names = array_column($rows, 2);

    expect($rows)->toHaveCount(3)
        ->and($rows[0][2])->toBe(__('admin.product.name'))
        ->and($names)->toContain('Плита индукционная')
        ->and(array_column($rows, 7))->toContain(__('admin.product.price_on_request'));
});

it('does not let a manager delete products', function () {
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->assertActionHidden('delete');
});

it('lets an administrator delete a product softly', function () {
    $this->actingAs(staffUser(UserRole::Admin));
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->callAction('delete');

    expect(Product::withTrashed()->find($product->id)?->trashed())->toBeTrue();
});

it('switches categories on in bulk from the list', function () {
    $categories = Category::factory()->count(2)->create(['is_active' => false]);

    Livewire::test(ListCategories::class)
        ->callTableBulkAction('activate', $categories);

    expect(Category::query()->where('is_active', true)->count())->toBe(2);
});

it('does not offer a child as the parent of its own category', function () {
    $root = Category::factory()->create(['parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $root->id]);

    Livewire::test(EditCategory::class, ['record' => $root->id])
        ->fillForm(['parent_id' => $child->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);
});

it('merges brands from the brand list', function () {
    $kept = Brand::factory()->create(['name' => 'Rosso']);
    $merged = Brand::factory()->create(['name' => 'Rosso (Китай)']);
    Product::factory()->create(['brand_id' => $merged->id]);

    Livewire::test(ListBrands::class)
        ->callTableAction('merge', $merged, data: ['target_id' => $kept->id])
        ->assertHasNoTableActionErrors();

    expect(Brand::query()->find($merged->id))->toBeNull()
        ->and(Product::query()->where('brand_id', $kept->id)->count())->toBe(1);
});

it('shows what the catalog still needs on the dashboard', function () {
    Product::factory()->create(['retail_price' => null, 'availability' => Availability::OnOrder]);

    Livewire::test(UnmappedSupplierRefs::class)
        ->assertSee(__('admin.dashboard.price_on_request'))
        ->assertSee(__('admin.dashboard.unmapped_brands'));

    Livewire::test(LatestImports::class)->assertOk();
});
