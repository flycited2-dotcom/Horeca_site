<?php

use App\Livewire\CategoryListing;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\Cookie;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Шкафы холодильные', 'slug' => 'shkafy']);
    $this->abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    $this->rada = Brand::factory()->create(['name' => 'Rada', 'slug' => 'rada']);
});

function listing(array $query = []): Testable
{
    return Livewire::withQueryParams($query)->test(CategoryListing::class, [
        'category' => test()->category,
        'title' => test()->category->name,
    ]);
}

it('applies a filter on the spot and starts from the first page', function () {
    Product::factory()->inStock()->create(['name' => 'Шкаф в наличии', 'category_id' => $this->category->id]);
    Product::factory()->create(['name' => 'Шкаф под заказ', 'category_id' => $this->category->id]);

    listing(['page' => 3])
        ->set('inStock', true)
        ->assertSet('page', 1)
        ->assertSee('Шкаф в наличии')
        ->assertDontSee('Шкаф под заказ')
        ->assertSee('Выбрано:');
});

it('reads the filters from the address of a shared link', function () {
    Product::factory()->create(['name' => 'Шкаф Abat', 'category_id' => $this->category->id, 'brand_id' => $this->abat->id, 'retail_price' => Money::ofRubles(40_000)]);
    Product::factory()->create(['name' => 'Шкаф Rada', 'category_id' => $this->category->id, 'brand_id' => $this->rada->id, 'retail_price' => Money::ofRubles(40_000)]);

    listing(['brand' => ['abat'], 'price_to' => '50000'])
        ->assertSet('brands', ['abat'])
        ->assertSet('priceTo', '50000')
        ->assertSee('Шкаф Abat')
        ->assertDontSee('Шкаф Rada');
});

it('keeps the default state for garbage in the address', function () {
    Product::factory()->create(['name' => 'Шкаф обычный', 'category_id' => $this->category->id]);

    listing(['page' => 'abc', 'brand' => 'abat', 'sort' => 'drop-table'])
        ->assertSet('page', 1)
        ->assertSet('brands', [])
        ->assertSee('Шкаф обычный');
});

it('counts every brand and the stock under the other filters', function () {
    Product::factory()->inStock()->create(['category_id' => $this->category->id, 'brand_id' => $this->abat->id, 'retail_price' => Money::ofRubles(1_000)]);
    Product::factory()->create(['category_id' => $this->category->id, 'brand_id' => $this->abat->id, 'retail_price' => Money::ofRubles(90_000)]);
    Product::factory()->inStock()->create(['category_id' => $this->category->id, 'brand_id' => $this->rada->id, 'retail_price' => Money::ofRubles(2_000)]);

    listing()
        ->set('priceTo', '50000')
        ->set('brands', ['abat'])
        ->assertViewHas('brandOptions', fn ($brands) => $brands->pluck('products_count', 'slug')->all() === ['abat' => 1, 'rada' => 1])
        ->assertViewHas('inStockCount', 1)
        ->assertViewHas('categoryTotal', 3);
});

it('drops one filter from its chip and all of them from «Сбросить всё»', function () {
    Product::factory()->inStock()->create(['category_id' => $this->category->id, 'brand_id' => $this->abat->id]);

    listing(['brand' => ['abat', 'rada'], 'in_stock' => '1', 'price_from' => '100'])
        ->assertSee("от 100\u{00A0}₽", false)
        ->call('removeFilter', 'brand', 'rada')
        ->assertSet('brands', ['abat'])
        ->call('removeFilter', 'price')
        ->assertSet('priceFrom', '')
        ->call('resetFilters')
        ->assertSet('brands', [])
        ->assertSet('inStock', false)
        ->assertDontSee('Выбрано:');
});

it('appends the next page on «Показать ещё» and jumps by the page links', function () {
    Product::factory()->count(30)->create(['category_id' => $this->category->id]);

    listing()
        ->assertSee('Показано 24 из 30')
        ->assertSee('Показать ещё 6')
        ->call('loadMore')
        ->assertViewHas('slice', fn ($slice) => $slice->products->count() === 30 && ! $slice->hasMore())
        ->assertSee('Показано 30 из 30')
        ->call('goToPage', 2)
        ->assertSet('page', 2)
        ->assertSet('pages', 1)
        ->assertSee('Показаны 25–30 из 30');
});

it('switches to the list view and remembers it in a cookie', function () {
    Product::factory()->create(['name' => 'Шкаф для списка', 'category_id' => $this->category->id]);

    listing()
        ->call('setView', 'list')
        ->assertSet('view', 'list')
        ->assertSeeHtml('<table');

    expect(Cookie::hasQueued(CategoryListing::VIEW_COOKIE))->toBeTrue()
        ->and(Cookie::queued(CategoryListing::VIEW_COOKIE)->getValue())->toBe('list');
});

it('opens in the view the customer chose last time', function () {
    Livewire::withCookie(CategoryListing::VIEW_COOKIE, 'list')
        ->test(CategoryListing::class, ['category' => $this->category, 'title' => 'Шкафы'])
        ->assertSet('view', 'list');
});

it('says which filter to drop when nothing matches', function () {
    Product::factory()->count(2)->create(['category_id' => $this->category->id, 'brand_id' => $this->abat->id, 'retail_price' => Money::ofRubles(5_000)]);

    listing(['brand' => ['abat'], 'price_from' => '100000'])
        ->assertSee('Под эти условия нет ни одной позиции')
        ->assertSee("Снять «от 100\u{00A0}000\u{00A0}₽» — 2 позиции", false)
        ->assertSee('Сбросить все фильтры');
});

it('ignores an unknown sort order', function () {
    listing()->call('sortBy', 'price; drop table')->assertSet('sort', 'popular');
});

it('folds the brands after the sixth and finds a brand by name', function () {
    foreach (range(1, 8) as $number) {
        $brand = Brand::factory()->create(['name' => "Бренд {$number}", 'slug' => "brand-{$number}"]);
        Product::factory()->create(['category_id' => $this->category->id, 'brand_id' => $brand->id]);
    }

    $html = listing()->html();
    expect(substr_count($html, 'data-collapsible'))->toBe(2)
        ->and($html)->toContain('Ещё 2 бренда');

    listing()->set('allBrands', true)->assertDontSeeHtml('data-collapsible');

    // Brand names also stand on the cards, so the checkboxes of the filter are checked.
    listing()->set('brandQuery', 'Бренд 7')
        ->assertSeeHtml('value="brand-7"')
        ->assertDontSeeHtml('value="brand-3"');
});
