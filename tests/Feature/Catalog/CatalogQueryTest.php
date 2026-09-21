<?php

use App\Enums\AttributeType;
use App\Enums\Availability;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CatalogSort;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->root = Category::factory()->create(['name' => 'Холодильное оборудование', 'is_active' => true, 'parent_id' => null]);
    $this->child = Category::factory()->create(['name' => 'Шкафы', 'is_active' => true, 'parent_id' => $this->root->id]);
    $this->hidden = Category::factory()->create(['name' => 'Архив', 'is_active' => false, 'parent_id' => $this->root->id]);

    $this->catalog = app(CatalogQuery::class);
});

it('lists the products of the category and its switched-on subcategories', function () {
    $inRoot = Product::factory()->create(['category_id' => $this->root->id]);
    $inChild = Product::factory()->create(['category_id' => $this->child->id]);
    Product::factory()->create(['category_id' => $this->hidden->id]);
    Product::factory()->discontinued()->create(['category_id' => $this->child->id]);
    Product::factory()->create(['category_id' => $this->child->id, 'is_visible' => false]);

    $page = $this->catalog->categorySlice($this->root, new CatalogFilters, null);

    expect($page->products->pluck('id')->all())->toEqualCanonicalizing([$inRoot->id, $inChild->id]);
});

it('shows nothing for a switched-off category', function () {
    Product::factory()->create(['category_id' => $this->hidden->id]);

    expect($this->catalog->categorySlice($this->hidden, new CatalogFilters, null)->total)->toBe(0);
});

it('puts products in stock first by default', function () {
    $onOrder = Product::factory()->create(['category_id' => $this->child->id, 'availability' => Availability::OnOrder]);
    $inStock = Product::factory()->inStock()->create(['category_id' => $this->child->id]);

    expect($this->catalog->categorySlice($this->child, new CatalogFilters, null)->products->pluck('id')->all())
        ->toBe([$inStock->id, $onOrder->id]);
});

it('filters by price and leaves products without a price out of the range', function () {
    $cheap = Product::factory()->create(['category_id' => $this->child->id, 'retail_price' => Money::ofRubles(5_000)]);
    $mid = Product::factory()->create(['category_id' => $this->child->id, 'retail_price' => Money::ofRubles(50_000)]);
    Product::factory()->priceOnRequest()->create(['category_id' => $this->child->id]);

    $filters = CatalogFilters::fromQuery(['price_from' => '10 000', 'price_to' => '60000']);

    expect($this->catalog->categorySlice($this->child, $filters, null)->products->pluck('id')->all())->toBe([$mid->id])
        ->and($cheap->id)->not->toBe($mid->id);
});

it('filters by availability and brand', function () {
    $abat = Brand::factory()->create(['slug' => 'abat']);
    $wanted = Product::factory()->inStock()->create(['category_id' => $this->child->id, 'brand_id' => $abat->id]);
    Product::factory()->create(['category_id' => $this->child->id, 'brand_id' => $abat->id]);
    Product::factory()->inStock()->create(['category_id' => $this->child->id]);

    $filters = CatalogFilters::fromQuery(['in_stock' => '1', 'brand' => ['abat']]);

    expect($this->catalog->categorySlice($this->child, $filters, null)->products->pluck('id')->all())->toBe([$wanted->id]);
});

it('filters by a filterable characteristic', function () {
    $power = Attribute::factory()->create(['slug' => 'moshchnost-kvt', 'type' => AttributeType::Number, 'is_filterable' => true]);
    $strong = Product::factory()->create(['category_id' => $this->child->id]);
    $weak = Product::factory()->create(['category_id' => $this->child->id]);
    $strong->attributeValues()->attach($power->id, ['value_number' => 7]);
    $weak->attributeValues()->attach($power->id, ['value_number' => 2]);

    $filters = CatalogFilters::fromQuery(['attr' => ['moshchnost-kvt' => ['min' => '5']]]);

    expect($this->catalog->categorySlice($this->child, $filters, null)->products->pluck('id')->all())->toBe([$strong->id]);
});

it('ignores a characteristic that is not filterable', function () {
    Attribute::factory()->create(['slug' => 'tsvet', 'type' => AttributeType::Text, 'is_filterable' => false]);
    Product::factory()->count(2)->create(['category_id' => $this->child->id]);

    $filters = CatalogFilters::fromQuery(['attr' => ['tsvet' => ['values' => ['красный']]]]);

    expect($this->catalog->categorySlice($this->child, $filters, null)->total)->toBe(2);
});

it('sorts by price with "price on request" last in both directions', function (string $sort) {
    $cheap = Product::factory()->create(['category_id' => $this->child->id, 'retail_price' => Money::ofRubles(100)]);
    $dear = Product::factory()->create(['category_id' => $this->child->id, 'retail_price' => Money::ofRubles(900)]);
    $onRequest = Product::factory()->priceOnRequest()->create(['category_id' => $this->child->id]);

    $ids = $this->catalog->categorySlice($this->child, CatalogFilters::fromQuery(['sort' => $sort]), null)->products->pluck('id')->all();

    expect($ids)->toBe($sort === 'price_asc'
        ? [$cheap->id, $dear->id, $onRequest->id]
        : [$dear->id, $cheap->id, $onRequest->id]);
})->with(['price_asc', 'price_desc']);

it('pages the listing by 24', function () {
    Product::factory()->count(30)->create(['category_id' => $this->child->id]);

    $second = $this->catalog->categorySlice($this->child, new CatalogFilters, null, page: 2);

    expect($second->total)->toBe(30)
        ->and($second->products)->toHaveCount(6)
        ->and($second->perPage)->toBe(CatalogFilters::PER_PAGE)
        ->and($second->from())->toBe(25)
        ->and($second->hasMore())->toBeFalse();
});

it('adds the next pages to the ones on screen for «Показать ещё»', function () {
    Product::factory()->count(60)->create(['category_id' => $this->child->id]);

    $slice = $this->catalog->categorySlice($this->child, new CatalogFilters, null, page: 1, pages: 2);

    expect($slice->products)->toHaveCount(48)
        ->and($slice->firstPage)->toBe(1)
        ->and($slice->lastPage)->toBe(2)
        ->and($slice->to())->toBe(48)
        ->and($slice->hasMore())->toBeTrue()
        ->and($slice->nextCount())->toBe(12);
});

it('shows the last page for a page past the end', function () {
    Product::factory()->count(30)->create(['category_id' => $this->child->id]);

    $slice = $this->catalog->categorySlice($this->child, new CatalogFilters, null, page: 9);

    expect($slice->firstPage)->toBe(2)
        ->and($slice->products)->toHaveCount(6);
});

it('counts the products in stock and each brand under the other filters', function () {
    $abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    $rada = Brand::factory()->create(['name' => 'Rada', 'slug' => 'rada']);
    Product::factory()->inStock()->create(['category_id' => $this->child->id, 'brand_id' => $abat->id, 'retail_price' => Money::ofRubles(1_000)]);
    Product::factory()->create(['category_id' => $this->child->id, 'brand_id' => $abat->id, 'retail_price' => Money::ofRubles(90_000)]);
    Product::factory()->inStock()->create(['category_id' => $this->child->id, 'brand_id' => $rada->id, 'retail_price' => Money::ofRubles(2_000)]);

    $filters = CatalogFilters::fromQuery(['price_to' => '50000', 'brand' => ['abat'], 'in_stock' => '1']);
    $scope = $this->catalog->inCategory($this->child);

    $brands = $this->catalog->brandFacet($this->catalog->filtered(clone $scope, $filters->without('brand')));
    $inStock = $this->catalog->inStockCount($this->catalog->filtered(clone $scope, $filters->without('in_stock')));

    expect($brands->pluck('products_count', 'slug')->all())->toBe(['abat' => 1, 'rada' => 1])
        ->and($inStock)->toBe(1)
        ->and($this->catalog->countInCategory($this->child, $filters->without('brand', 'abat')))->toBe(2);
});

it('loads a page of cards with a fixed number of queries', function () {
    Product::factory()->count(24)->create(['category_id' => $this->child->id]);
    app()->forgetScopedInstances();

    DB::enableQueryLog();

    $page = app(CatalogQuery::class)->categorySlice($this->child, new CatalogFilters, null);
    $page->products->each(fn (Product $product) => [$product->brand?->name, $product->getFirstMediaUrl(Product::IMAGES)]);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(6);
});

it('gives the brand filter with product counts and the price range', function () {
    $abat = Brand::factory()->create(['name' => 'Abat']);
    Product::factory()->count(2)->create(['category_id' => $this->child->id, 'brand_id' => $abat->id, 'retail_price' => Money::ofRubles(1_000)]);
    Product::factory()->create(['category_id' => $this->child->id, 'retail_price' => Money::ofRubles(9_000)]);

    $scope = $this->catalog->inCategory($this->child);
    $brands = $this->catalog->brandFacet($scope);
    $range = $this->catalog->priceRange($scope);

    expect($brands->firstWhere('id', $abat->id)?->products_count)->toBe(2)
        ->and($range['min']->toDecimal())->toBe('1000.00')
        ->and($range['max']->toDecimal())->toBe('9000.00');
});

it('turns a query string into filters and back, dropping garbage', function () {
    $filters = CatalogFilters::fromQuery([
        'price_from' => 'abc',
        'price_to' => '50000',
        'in_stock' => '1',
        'brand' => ['abat', '<script>', 'rosso'],
        'attr' => ['moshchnost-kvt' => ['min' => '3', 'max' => 'x'], 'Плохой ключ' => ['min' => '1']],
        'sort' => 'unknown',
    ]);

    expect($filters->priceFrom)->toBeNull()
        ->and($filters->priceTo)->toBe(50_000)
        ->and($filters->inStockOnly)->toBeTrue()
        ->and($filters->brands)->toBe(['abat', 'rosso'])
        ->and($filters->attributes)->toBe(['moshchnost-kvt' => ['min' => '3']])
        ->and($filters->sort)->toBe(CatalogSort::Popular)
        ->and($filters->toQuery())->toBe([
            'price_to' => 50_000,
            'in_stock' => 1,
            'brand' => ['abat', 'rosso'],
            'attr' => ['moshchnost-kvt' => ['min' => '3']],
        ]);
});
