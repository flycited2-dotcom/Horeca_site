<?php

use App\Enums\Availability;
use App\Enums\WarehouseStockStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Шкафы холодильные', 'slug' => 'shkafy', 'is_active' => true]);

    $this->product = Product::factory()->create([
        'name' => 'Шкаф холодильный ШХ-0,7',
        'slug' => 'shkaf-holodilnyy',
        'sku' => '71000019569',
        'supplier_code' => 'ЦБ-Ц0017339',
        'category_id' => $this->category->id,
        'retail_price' => Money::ofRubles(120_000),
        'description' => 'Однодверный шкаф для профессиональной кухни.',
    ]);
});

it('shows the card with the price, the codes and the way back to the category', function () {
    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertSee('Шкаф холодильный ШХ-0,7')
        ->assertSee('71000019569')
        ->assertSee('ЦБ-Ц0017339')
        ->assertSee("120\u{00A0}000\u{00A0}₽", false)
        ->assertSee('Добавить в корзину')
        ->assertSee('Шкафы холодильные')
        ->assertSee('Однодверный шкаф');
});

it('names the warehouses with their delivery time and never the quantity', function () {
    $warehouse = Warehouse::factory()->create([
        'name' => 'Симферополь',
        'is_visible' => true,
        'delivery_days_min' => 1,
        'delivery_days_max' => 2,
    ]);
    $hidden = Warehouse::factory()->create(['name' => 'Скрытый склад', 'is_visible' => false]);

    ProductStock::factory()->create([
        'product_id' => $this->product->id,
        'warehouse_id' => $warehouse->id,
        'status' => WarehouseStockStatus::InStock,
        'quantity' => 17,
    ]);
    ProductStock::factory()->create(['product_id' => $this->product->id, 'warehouse_id' => $hidden->id]);

    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertSee('Симферополь')
        ->assertSee('1–2 дней')
        ->assertDontSee('Скрытый склад')
        ->assertDontSee('17 шт');
});

it('keeps the page of a discontinued product and offers a replacement', function () {
    $this->product->forceFill(['availability' => Availability::Discontinued])->save();

    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertSee('Снят с производства')
        ->assertSee('Подобрать аналог')
        ->assertDontSee('Добавить в корзину');
});

it('asks for the price when the supplier has none', function () {
    $this->product->forceFill(['retail_price' => null])->save();

    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertSee('Цена по запросу')
        ->assertSee('Запросить цену');
});

it('hides a product the manager switched off', function () {
    $this->product->forceFill(['is_visible' => false])->save();

    $this->get('/product/shkaf-holodilnyy')->assertNotFound();
});

it('renders the card with a fixed number of queries', function () {
    Product::factory()->count(6)->create(['category_id' => $this->category->id, 'retail_price' => Money::ofRubles(125_000)]);

    DB::enableQueryLog();
    $this->get('/product/shkaf-holodilnyy')->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(20);
});
