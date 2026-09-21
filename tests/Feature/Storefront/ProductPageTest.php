<?php

use App\Enums\AttributeType;
use App\Enums\Availability;
use App\Enums\WarehouseStockStatus;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
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

it('marks the product and its offer up for search engines', function () {
    $this->product->update(['availability' => Availability::InStock, 'sku' => '11000019106']);

    $html = $this->get('/product/shkaf-holodilnyy')->assertOk()->getContent();
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
    $data = json_decode($match[1], true);

    expect($data['@type'])->toBe('Product')
        ->and($data['name'])->toBe('Шкаф холодильный ШХ-0,7')
        ->and($data['sku'])->toBe('11000019106')
        ->and($data['offers']['price'])->toBe('120000.00')
        ->and($data['offers']['priceCurrency'])->toBe('RUB')
        ->and($data['offers']['availability'])->toBe('https://schema.org/InStock');
});

it('gives no offer for the price on request', function () {
    $this->product->update(['retail_price' => null]);

    $html = $this->get('/product/shkaf-holodilnyy')->assertOk()->getContent();
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);

    expect(json_decode($match[1], true))->not->toHaveKey('offers');
});

it('lists the characteristics with the data typography and a narrow table when there are few', function () {
    $power = Attribute::factory()->create(['name' => 'Мощность', 'unit' => 'кВт', 'type' => AttributeType::Number, 'is_main' => true]);
    $this->product->attributeValues()->attach($power->id, ['value_number' => 18.9]);
    $this->product->update(['length_mm' => 840, 'width_mm' => 800, 'height_mm' => 1120, 'weight_kg' => 128.5, 'model' => 'ШХ-0,7']);

    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertSee('Характеристики')
        ->assertSee("840×800×1120\u{00A0}мм", false)
        ->assertSee("128,5\u{00A0}кг", false)
        ->assertSee("18,9\u{00A0}кВт", false)
        ->assertSee('Проверьте перед монтажом');
});

it('leaves out the tabs and the blocks it has nothing for', function () {
    $this->product->update(['description' => null, 'warranty_months' => null]);

    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertDontSee('data-panel="description"', false)
        ->assertDontSee('data-panel="warranty"', false)
        ->assertDontSee('Проверьте перед монтажом')
        ->assertSee('Фото уточняется у производителя')
        ->assertSee('Остальные параметры уточним по запросу.');
});

it('tells the warranty and the pickup address in their tabs', function () {
    Setting::query()->create(['key' => 'pickup.address', 'value' => 'Симферополь, ул. Промышленная, 1']);
    $this->product->update(['warranty_months' => 12]);

    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertSee('data-panel="warranty"', false)
        ->assertSee('Гарантия 12 месяцев')
        ->assertSee('Самовывоз: Симферополь, ул. Промышленная, 1');
});

it('offers the related products the storefront may show', function () {
    $related = Product::factory()->create(['name' => 'Подставка под шкаф', 'category_id' => $this->category->id]);
    $hidden = Product::factory()->create(['name' => 'Скрытая подставка', 'category_id' => $this->category->id, 'is_visible' => false]);
    $this->product->relatedProducts()->attach([$related->id => ['sort' => 1], $hidden->id => ['sort' => 2]]);

    $this->get('/product/shkaf-holodilnyy')
        ->assertOk()
        ->assertSee('Часто берут вместе')
        ->assertSee('Подставка под шкаф')
        ->assertDontSee('Скрытая подставка');
});

it('keeps the buy bar at hand on small screens, but not for a discontinued product', function () {
    $this->get('/product/shkaf-holodilnyy')->assertOk()->assertSee('data-sticky-buy', false);

    $this->product->update(['availability' => Availability::Discontinued]);

    $this->get('/product/shkaf-holodilnyy')->assertOk()->assertDontSee('data-sticky-buy', false);
});
