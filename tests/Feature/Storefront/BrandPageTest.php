<?php

use App\Livewire\BrandListing;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->ovens = Category::factory()->create(['name' => 'Пароконвектоматы', 'slug' => 'parokonvektomaty']);
    $this->fridges = Category::factory()->create(['name' => 'Холодильные шкафы', 'slug' => 'shkafy']);
    $this->abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    $this->rada = Brand::factory()->create(['name' => 'Rada', 'slug' => 'rada']);
});

function brandListing(array $query = []): Testable
{
    return Livewire::withQueryParams($query)->test(BrandListing::class, ['brand' => test()->abat]);
}

it('lists the brands with products on the storefront by letter', function () {
    $atesy = Brand::factory()->create(['name' => 'Атеси', 'slug' => 'atesi']);
    $hidden = Brand::factory()->create(['name' => 'Скрытый бренд', 'is_active' => false]);
    Brand::factory()->create(['name' => 'Бренд без товаров']);
    Product::factory()->count(2)->create(['category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);
    Product::factory()->create(['category_id' => $this->fridges->id, 'brand_id' => $atesy->id]);
    Product::factory()->create(['category_id' => $this->fridges->id, 'brand_id' => $hidden->id]);
    Product::factory()->discontinued()->create(['category_id' => $this->fridges->id, 'brand_id' => $this->rada->id]);

    $this->get('/brands')
        ->assertOk()
        ->assertSeeInOrder(['Бренды', '2 бренда · 3 позиции', 'A', 'Abat', '2', 'А', 'Атеси', '1'])
        ->assertSee(route('brand', 'abat'))
        ->assertDontSee('Скрытый бренд')
        ->assertDontSee('Бренд без товаров')
        ->assertDontSee('Rada');
});

it('shows the products of the brand with the listing filters', function () {
    Product::factory()->inStock()->create(['name' => 'Пароконвектомат Abat в наличии', 'category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);
    Product::factory()->create(['name' => 'Пароконвектомат Abat под заказ', 'category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);
    Product::factory()->create(['name' => 'Пароконвектомат Rada', 'category_id' => $this->ovens->id, 'brand_id' => $this->rada->id]);

    $this->get('/brands/abat')
        ->assertOk()
        ->assertSee('<title>Каталог Abat', false)
        ->assertSeeInOrder(['Бренды', 'Abat', '2 модели · 1 в наличии'])
        ->assertSee('Пароконвектомат Abat под заказ')
        ->assertDontSee('Пароконвектомат Rada');

    brandListing()
        ->set('inStock', true)
        ->assertSee('Пароконвектомат Abat в наличии')
        ->assertDontSee('Пароконвектомат Abat под заказ')
        ->assertSee('1 из 2 моделей');
});

it('does not open the page of a switched-off brand', function () {
    $this->abat->update(['is_active' => false]);

    $this->get('/brands/abat')->assertNotFound();
});

it('narrows the brand to a section and drops it from its chip', function () {
    Product::factory()->create(['name' => 'Пароконвектомат Abat', 'category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);
    Product::factory()->create(['name' => 'Шкаф Abat', 'category_id' => $this->fridges->id, 'brand_id' => $this->abat->id]);

    brandListing()
        ->assertSee('Разделы:')
        ->assertSee('Пароконвектоматы · 1')
        ->set('category', 'shkafy')
        ->assertSee('Шкаф Abat')
        ->assertDontSee('Пароконвектомат Abat')
        ->assertSee('Выбрано:')
        ->call('removeFilter', 'category')
        ->assertSet('category', '')
        ->assertSee('Пароконвектомат Abat');
});

it('offers to drop the section when the filters leave nothing in it', function () {
    Product::factory()->create(['category_id' => $this->ovens->id, 'brand_id' => $this->abat->id, 'retail_price' => Money::ofRubles(90_000)]);
    Product::factory()->count(2)->create(['category_id' => $this->fridges->id, 'brand_id' => $this->abat->id, 'retail_price' => Money::ofRubles(10_000)]);

    brandListing(['category' => 'parokonvektomaty', 'price_to' => '50000'])
        ->assertSee('Под эти условия нет ни одной позиции')
        ->assertSee('Снять «Пароконвектоматы» — 2 позиции')
        ->assertSee("Снять «до 50\u{00A0}000\u{00A0}₽» — 1 позиция", false);
});

it('ignores a brand filter in the address of a brand page', function () {
    Product::factory()->create(['name' => 'Шкаф Abat', 'category_id' => $this->fridges->id, 'brand_id' => $this->abat->id]);

    brandListing(['brand' => ['rada']])
        ->assertSee('Шкаф Abat')
        ->assertDontSee('Выбрано:')
        ->assertViewHas('filters', fn ($filters) => $filters->brands === []);
});

it('keeps the section in the form and the links that work without scripts', function () {
    Product::factory()->count(2)->create(['category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);
    Product::factory()->create(['category_id' => $this->fridges->id, 'brand_id' => $this->abat->id]);

    $this->get('/brands/abat?category=parokonvektomaty&in_stock=1')
        ->assertOk()
        ->assertSee('action="'.route('brand', 'abat').'"', false)
        ->assertSee('<input type="hidden" name="category" value="parokonvektomaty">', false)
        ->assertSee(e(route('brand', ['brand' => 'abat', 'category' => 'shkafy', 'in_stock' => 1])), false)
        ->assertDontSee('name="brand[]"', false);
});

it('renders a brand page without a query per product', function () {
    Product::factory()->count(24)->create(['category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);

    DB::enableQueryLog();
    $this->get('/brands/abat')->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(25);
});

it('lists the leading brands on the home page', function () {
    Product::factory()->count(3)->create(['category_id' => $this->ovens->id, 'brand_id' => $this->rada->id]);
    Product::factory()->create(['category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Бренды', 'Все 2 бренда', 'Abat', 'Rada'])
        ->assertSee(route('brand', 'rada'))
        ->assertSee(route('brands'));
});

it('links the brand of a product to the brand page', function () {
    $product = Product::factory()->create(['category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);

    $this->get(route('product', $product))->assertOk()->assertSee(route('brand', 'abat'));
});
