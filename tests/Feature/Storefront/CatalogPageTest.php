<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->root = Category::factory()->create(['name' => 'Холодильное оборудование', 'slug' => 'holodilnoe', 'is_active' => true, 'parent_id' => null]);
    $this->child = Category::factory()->create(['name' => 'Шкафы холодильные', 'slug' => 'shkafy', 'is_active' => true, 'parent_id' => $this->root->id]);
});

it('shows the catalog page with switched-on categories only', function () {
    Category::factory()->create(['name' => 'Выключенный раздел', 'is_active' => false, 'parent_id' => null]);

    $this->get('/catalog')
        ->assertOk()
        ->assertSee('Холодильное оборудование')
        ->assertSee('Шкафы холодильные')
        ->assertDontSee('Выключенный раздел');
});

it('shows a category with the products of its subcategories', function () {
    $product = Product::factory()->create(['name' => 'Шкаф холодильный Abat', 'category_id' => $this->child->id]);
    Product::factory()->discontinued()->create(['name' => 'Снятый шкаф', 'category_id' => $this->child->id]);

    $this->get('/catalog/holodilnoe')
        ->assertOk()
        ->assertSee('Шкаф холодильный Abat')
        ->assertDontSee('Снятый шкаф')
        ->assertSee($product->slug);
});

it('hides a switched-off category from the storefront', function () {
    $hidden = Category::factory()->create(['slug' => 'arhiv', 'is_active' => false]);
    Product::factory()->create(['category_id' => $hidden->id]);

    $this->get('/catalog/arhiv')->assertNotFound();
});

it('filters the listing by price, stock and brand from the address', function () {
    $abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    $wanted = Product::factory()->inStock()->create([
        'name' => 'Шкаф подходящий',
        'category_id' => $this->child->id,
        'brand_id' => $abat->id,
        'retail_price' => Money::ofRubles(50_000),
    ]);
    Product::factory()->create(['name' => 'Шкаф дорогой', 'category_id' => $this->child->id, 'brand_id' => $abat->id, 'retail_price' => Money::ofRubles(500_000)]);
    Product::factory()->inStock()->create(['name' => 'Шкаф другого бренда', 'category_id' => $this->child->id, 'retail_price' => Money::ofRubles(50_000)]);

    $this->get('/catalog/shkafy?price_from=10000&price_to=100000&in_stock=1&brand[]=abat')
        ->assertOk()
        ->assertSee('Шкаф подходящий')
        ->assertDontSee('Шкаф дорогой')
        ->assertDontSee('Шкаф другого бренда')
        ->assertSee($wanted->sku);
});

it('keeps "только в наличии" switched off by default', function () {
    Product::factory()->create(['name' => 'Шкаф под заказ', 'category_id' => $this->child->id]);

    $this->get('/catalog/shkafy')
        ->assertOk()
        ->assertSee('Шкаф под заказ');
});

it('sorts the listing by price when the address says so', function () {
    Product::factory()->create(['name' => 'Шкаф дорогой', 'category_id' => $this->child->id, 'retail_price' => Money::ofRubles(90_000)]);
    Product::factory()->create(['name' => 'Шкаф дешёвый', 'category_id' => $this->child->id, 'retail_price' => Money::ofRubles(10_000)]);

    $response = $this->get('/catalog/shkafy?sort=price_asc')->assertOk();

    expect(strpos($response->getContent(), 'Шкаф дешёвый'))->toBeLessThan(strpos($response->getContent(), 'Шкаф дорогой'));
});

it('renders a listing page with a fixed number of queries', function () {
    Product::factory()->count(24)->create(['category_id' => $this->child->id]);

    DB::enableQueryLog();
    $this->get('/catalog/shkafy')->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(20);
});

it('shows the price on request instead of an empty place', function () {
    Product::factory()->priceOnRequest()->create(['name' => 'Шкаф без цены', 'category_id' => $this->child->id]);

    $this->get('/catalog/shkafy')
        ->assertOk()
        ->assertSee('Цена по запросу');
});
