<?php

use App\Enums\Availability;
use App\Enums\WarehouseStockStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

it('shows active root categories marked for the home page', function () {
    $refrigeration = Category::factory()->create([
        'name' => 'Холодильное оборудование',
        'show_on_home' => true,
        'products_count' => 7021,
    ]);
    Category::factory()->inactive()->create(['name' => 'Скрытая категория', 'show_on_home' => true]);
    Category::factory()->create(['name' => 'Не для главной', 'show_on_home' => false]);
    Category::factory()->childOf($refrigeration)->create(['name' => 'Холодильный шкаф', 'show_on_home' => true]);

    $response = $this->get('/')->assertOk()->assertDontSee('Скрытая категория');

    // The layout lists every switched-on root section; the home panel only the marked ones.
    preg_match('/<main.*?<\/main>/s', $response->getContent(), $main);

    expect($main[0])
        ->toContain('Холодильное оборудование', "7\u{00A0}021 позиция")
        ->not->toContain('Не для главной')
        ->not->toContain('Холодильный шкаф');
});

it('explains an empty catalog instead of showing a blank page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Каталог пока пуст');
});

it('renders the home page with a small fixed number of queries', function () {
    Category::factory()->count(8)->create(['show_on_home' => true]);

    DB::enableQueryLog();
    $this->get('/')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(20);
});

it('counts the stock of a section with its subsections and names the biggest of them', function () {
    $cold = Category::factory()->create(['name' => 'Холодильное оборудование', 'show_on_home' => true, 'products_count' => 3]);
    $cabinets = Category::factory()->childOf($cold)->create(['name' => 'Шкафы холодильные', 'products_count' => 2]);
    Category::factory()->childOf($cold)->create(['name' => 'Лари морозильные', 'products_count' => 1]);
    Product::factory()->inStock()->create(['category_id' => $cabinets->id]);
    Product::factory()->withAvailability(Availability::Low)->create(['category_id' => $cold->id]);
    Product::factory()->create(['category_id' => $cabinets->id]);

    $this->get('/')
        ->assertOk()
        ->assertSee('3 позиции · 2 в наличии')
        ->assertSee('Шкафы холодильные, Лари морозильные')
        ->assertSee('Весь каталог — 1 раздел');
});

it('shows the hits, the new products and the local warehouse only when there are any', function () {
    Setting::query()->create(['key' => 'catalog.local_warehouse_name', 'value' => 'Симферополь']);
    Setting::query()->create(['key' => 'catalog.local_strip_min_products', 'value' => 2]);
    $category = Category::factory()->create(['products_count' => 3]);
    $local = Warehouse::factory()->create(['name' => 'Симферополь', 'is_visible' => true]);

    Product::factory()->create(['name' => 'Хит продаж', 'category_id' => $category->id, 'is_hit' => true]);
    $first = Product::factory()->inStock()->create(['name' => 'С местного склада', 'category_id' => $category->id]);
    ProductStock::factory()->create(['product_id' => $first->id, 'warehouse_id' => $local->id, 'status' => WarehouseStockStatus::InStock]);

    // One product at the local warehouse is fewer than the minimum: no strip yet.
    $this->get('/')
        ->assertOk()
        ->assertSee('Часто заказывают')
        ->assertDontSee('Новинки')
        ->assertDontSee('Готово к отгрузке: Симферополь');

    $second = Product::factory()->inStock()->create(['category_id' => $category->id, 'is_new' => true]);
    ProductStock::factory()->create(['product_id' => $second->id, 'warehouse_id' => $local->id, 'status' => WarehouseStockStatus::Low]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Новинки')
        ->assertSee('Готово к отгрузке: Симферополь');
});

it('marks the shop up as an organization with the contacts it has', function () {
    Setting::query()->create(['key' => 'site.name', 'value' => 'Проф Кухня']);
    Setting::query()->create(['key' => 'contacts.phones', 'value' => '+7 978 123-45-67']);

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $this->get('/')->assertOk()->getContent(), $markup);

    expect(json_decode($markup[1], true))->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Проф Кухня',
        'url' => url('/'),
        'telephone' => '+7 978 123-45-67',
    ]);
});

it('keeps the organization markup on the home page only', function () {
    $this->get(route('catalog'))->assertOk()->assertDontSee('"@type":"Organization"', false);
});
