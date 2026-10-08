<?php

use App\Enums\Availability;
use App\Enums\WarehouseStockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Support\Money;
use App\Support\Percent;
use App\Support\Typography;
use Illuminate\Support\Facades\DB;

/**
 * The markup of one section of the home page, from its opening tag to its closing one:
 * `catalog`, `stock`, `ready`, `hits`, `fresh`, `picks`, `brands` or `sku`.
 */
function homeBlock(string $html, string $id): string
{
    preg_match('/<section class="gl-sec" id="'.preg_quote($id, '/').'".*?<\/section>/s', $html, $block);

    return $block[0] ?? '';
}

/**
 * How many queries the home page costs once its caches are warm: the second visit of a visitor.
 */
function homeQueryCount(): int
{
    test()->get('/')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get('/')->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('shows every active root category with products, marked for the home page or not', function () {
    $refrigeration = Category::factory()->create([
        'name' => 'Холодильное оборудование',
        'show_on_home' => true,
        'products_count' => 7021,
    ]);
    Category::factory()->create(['name' => 'Аксессуары', 'show_on_home' => false, 'products_count' => 5]);
    Category::factory()->inactive()->create(['name' => 'Скрытая категория', 'show_on_home' => true, 'products_count' => 9]);
    Category::factory()->create(['name' => 'Пустой раздел', 'products_count' => 0]);
    Category::factory()->childOf($refrigeration)->create(['name' => 'Холодильный шкаф', 'products_count' => 3]);

    $response = $this->get('/')->assertOk()->assertDontSee('Скрытая категория');

    // The whole catalog is on the home page: the tiles of the page, not only the bar above.
    preg_match('/<main.*?<\/main>/s', $response->getContent(), $main);

    expect($main[0])
        ->toContain('Холодильное оборудование', "7\u{00A0}021 позиция", 'Аксессуары', '5 позиций')
        ->not->toContain('Пустой раздел')
        ->not->toContain('Скрытая категория');
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
        ->assertSeeText('3 позиции · 2 в наличии')
        ->assertSee('Шкафы холодильные, Лари морозильные');
});

it('opens with the catalog in figures and colours the sections by temperature', function () {
    $cold = Category::factory()->create(['name' => 'Холодильное оборудование', 'show_on_home' => true, 'products_count' => 2]);
    $hot = Category::factory()->create(['name' => 'Тепловое оборудование', 'show_on_home' => true, 'products_count' => 1]);
    Category::factory()->create(['name' => 'Нейтральное оборудование', 'show_on_home' => true, 'products_count' => 1]);
    Product::factory()->count(2)->create(['category_id' => $cold->id]);
    Product::factory()->create(['category_id' => $hot->id]);

    $html = $this->get('/')->assertOk()
        ->assertSeeTextInOrder(['Оборудование и инвентарь для ресторанов, кафе и баров', 'Открыть каталог', 'Найдём за вас'])
        ->assertSee('модели холодильного оборудования')
        ->assertSee('модель теплового оборудования')
        ->getContent();

    expect(substr_count($html, 'data-zone="cold"'))->toBe(1)
        ->and(substr_count($html, 'data-zone="hot"'))->toBe(1)
        ->and($html)->toContain('id="lead-not-found"');
});

it('opens the catalog with the cold and the hot section as big tiles', function () {
    $accessories = Category::factory()->create(['name' => 'Аксессуары для оборудования', 'show_on_home' => true, 'products_count' => 1]);
    $hot = Category::factory()->create(['name' => 'Тепловое оборудование', 'show_on_home' => true, 'products_count' => 1]);
    $cold = Category::factory()->create(['name' => 'Холодильное оборудование', 'show_on_home' => true, 'products_count' => 2]);
    Product::factory()->create(['category_id' => $accessories->id]);
    Product::factory()->create(['category_id' => $hot->id]);
    Product::factory()->count(2)->create(['category_id' => $cold->id]);

    preg_match('/aria-labelledby="home-sections".*?<\/section>/s', $this->get('/')->assertOk()->getContent(), $catalog);
    $at = fn (string $text): int|false => mb_strpos($catalog[0], $text);

    expect(substr_count($catalog[0], 'data-featured'))->toBe(2)
        ->and($at('>Холод<'))->toBeLessThan($at('Холодильное оборудование'))
        ->and($at('Холодильное оборудование'))->toBeLessThan($at('>Жар<'))
        ->and($at('>Жар<'))->toBeLessThan($at('Тепловое оборудование'))
        ->and($at('Тепловое оборудование'))->toBeLessThan($at('Аксессуары для оборудования'));
});

it('leaves out a figure the catalog has nothing for', function () {
    $neutral = Category::factory()->create(['name' => 'Нейтральное оборудование', 'show_on_home' => true, 'products_count' => 1]);
    Product::factory()->create(['category_id' => $neutral->id]);

    $this->get('/')
        ->assertOk()
        ->assertSee('позиция в каталоге')
        ->assertDontSee('холодильного оборудования')
        ->assertDontSee('теплового оборудования');
});

it('does not say «0 в наличии» on a section with nothing in stock', function () {
    $scales = Category::factory()->create(['name' => 'Весовое оборудование', 'show_on_home' => true, 'products_count' => 2]);
    Product::factory()->count(2)->create(['category_id' => $scales->id]);

    $this->get('/')
        ->assertOk()
        ->assertSee('2 позиции')
        ->assertDontSee('0 в наличии');
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
        ->assertSeeText('Часто заказывают')
        ->assertDontSeeText('Новинки')
        ->assertDontSeeText('Готово к отгрузке: Симферополь');

    $second = Product::factory()->inStock()->create(['category_id' => $category->id, 'is_new' => true]);
    ProductStock::factory()->create(['product_id' => $second->id, 'warehouse_id' => $local->id, 'status' => WarehouseStockStatus::Low]);

    $this->get('/')
        ->assertOk()
        ->assertSeeText('Новинки')
        ->assertSeeText('Готово к отгрузке: Симферополь');
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

it('opens with one heading, the way into the catalog and the request for a model the shop has not found', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match('/<section class="gl-hero".*?<\/section>/s', $html, $hero);

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($hero[0] ?? '')->toContain(
            'aria-labelledby="home-heading"',
            '<h1 class="gl-h1" id="home-heading">',
            '<span class="gl-grad">ресторанов, кафе и баров</span>',
            '<a class="gl-btn gl-btn--hot" href="'.route('catalog').'">Открыть каталог</a>',
            'popovertarget="lead-not-found"',
        );
});

it('names the lowest price of the models on the page under the buttons and says nothing without prices', function () {
    $category = Category::factory()->create(['show_on_home' => true, 'products_count' => 3]);

    $this->get('/')->assertOk()->assertDontSeeText('Цены на этой странице');

    Product::factory()->inStock()->create(['category_id' => $category->id, 'retail_price' => Money::ofRubles(37_114)]);
    Product::factory()->inStock()->create(['category_id' => $category->id, 'retail_price' => Money::ofRubles(9_218)]);
    Product::factory()->inStock()->priceOnRequest()->create(['category_id' => $category->id]);

    $this->get('/')->assertOk()->assertSeeText("Цены на этой странице — от 9\u{00A0}218\u{00A0}₽");

    Product::query()->update(['retail_price' => null]);

    $this->get('/')->assertOk()->assertDontSeeText('Цены на этой странице');
});

it('counts the sections of the catalog in the heading of its block', function () {
    Category::factory()->create(['name' => 'Холодильное оборудование', 'show_on_home' => true, 'products_count' => 4]);
    Category::factory()->create(['name' => 'Весовое оборудование', 'show_on_home' => true, 'products_count' => 2]);

    $catalog = homeBlock($this->get('/')->assertOk()->getContent(), 'catalog');

    expect($catalog)->toContain('aria-labelledby="home-sections"', '<span class="gl-grad">раздел</span>')
        ->and(strip_tags($catalog))->toContain('Выберите раздел', '2 раздела каталога');
});

it('lays three or more models in stock out as an accordion with the first column open', function () {
    $category = Category::factory()->create(['name' => 'Холодильное оборудование', 'show_on_home' => true, 'products_count' => 4]);
    $products = collect([37_114, 33_849, 22_388, 9_218])->map(fn (int $rubles): Product => Product::factory()->inStock()->create([
        'category_id' => $category->id,
        'retail_price' => Money::ofRubles($rubles),
    ]));

    $stock = homeBlock($this->get('/')->assertOk()->getContent(), 'stock');

    expect($stock)->toContain('aria-labelledby="strip-in_stock"', 'id="strip-in_stock"', '<ul class="gl-acc" style="--gl-n: 4">', __('shop.home.stock.hint'))
        ->and(preg_match_all('/<li class="gl-card gl-acc__item gl-cold/', $stock))->toBe(4)
        ->and(substr_count($stock, 'gl-is-open'))->toBe(1)
        ->and(substr_count($stock, '>В наличии<'))->toBe(4)
        ->and(substr_count($stock, 'data-cart-form'))->toBe(4)
        ->and($stock)->not->toContain('gl-ships');

    foreach ($products as $product) {
        expect($stock)->toContain(
            'href="'.route('product', $product).'"',
            'action="'.route('cart.add', $product->id).'"',
            Typography::money($product->retail_price),
        );
    }
});

it('does not hide the panels of the accordion from the keyboard or from the screen reader', function () {
    $category = Category::factory()->create(['products_count' => 3]);
    Product::factory()->count(3)->inStock()->create(['category_id' => $category->id]);

    $stock = homeBlock($this->get('/')->assertOk()->getContent(), 'stock');

    // The vertical caption of a column only repeats the panel, so a screen reader skips it; the panel itself is not hidden.
    expect(substr_count($stock, '<div class="gl-acc__tab" aria-hidden="true">'))->toBe(3)
        ->and(substr_count($stock, '<div class="gl-acc__panel">'))->toBe(3)
        ->and($stock)->not->toContain('tabindex="-1"')
        ->and($stock)->not->toContain('<div class="gl-acc__panel" aria-hidden');
});

it('keeps the models in stock as cards when there are fewer than three', function () {
    $category = Category::factory()->create(['show_on_home' => true, 'products_count' => 2]);
    Product::factory()->count(2)->inStock()->create(['category_id' => $category->id]);

    $stock = homeBlock($this->get('/')->assertOk()->getContent(), 'stock');

    expect($stock)->toContain('<ul class="gl-ships">', 'id="strip-in_stock"')
        ->and(preg_match_all('/<li class="gl-card gl-ship/', $stock))->toBe(2)
        ->and($stock)->not->toContain('gl-acc')
        ->and($stock)->not->toContain(__('shop.home.stock.hint'));
});

it('shows at most six models in the accordion', function () {
    $category = Category::factory()->create(['products_count' => 8]);
    Product::factory()->count(8)->inStock()->create(['category_id' => $category->id]);

    $stock = homeBlock($this->get('/')->assertOk()->getContent(), 'stock');

    expect(preg_match_all('/<li class="gl-card gl-acc__item/', $stock))->toBe(6)
        ->and($stock)->toContain('style="--gl-n: 6"');
});

it('shows a product of a strip as a card with the brand, the name, the status, the price and the way into the cart', function () {
    $category = Category::factory()->create(['products_count' => 2]);
    $brand = Brand::factory()->create(['name' => 'Abat']);
    $hit = Product::factory()->create([
        'name' => 'ПКА 10-1/1 Пароконвектомат',
        'model' => 'ПКА 10-1/1',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'is_hit' => true,
        'retail_price' => Money::ofRubles(96_750),
    ]);
    $asked = Product::factory()->priceOnRequest()->create([
        'name' => 'Мармит без цены',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'is_hit' => true,
    ]);

    $hits = homeBlock($this->get('/')->assertOk()->getContent(), 'hits');

    expect($hits)->toContain(
        'aria-labelledby="strip-hits"',
        'href="'.route('product', $hit).'"',
        '<span class="gl-mono">ПКА 10-1/1</span> Пароконвектомат',
        '<span class="gl-ship__brand">Abat</span>',
        '<span class="gl-stock gl-stock--order">Под заказ</span>',
        "96\u{00A0}750\u{00A0}₽",
        'action="'.route('cart.add', $hit->id).'"',
        'Добавить в корзину: ПКА 10-1/1 Пароконвектомат',
        'href="'.route('product', $asked).'"',
        'Цена по запросу',
        'data-lead-product-id="'.$asked->id.'"',
        'Запросить цену',
    )
        ->and(substr_count($hits, 'data-cart-form'))->toBe(1);
});

it('shows the old price of a promotion above the price of the model', function () {
    $category = Category::factory()->create(['products_count' => 1]);
    Product::factory()->create([
        'category_id' => $category->id,
        'is_new' => true,
        'retail_price' => Money::ofRubles(50_000),
        'old_price' => Money::ofRubles(62_000),
    ]);

    $fresh = homeBlock($this->get('/')->assertOk()->getContent(), 'fresh');

    expect($fresh)->toContain('<s class="gl-was">62'."\u{00A0}".'000'."\u{00A0}".'₽</s>', '<span class="gl-price">50'."\u{00A0}".'000'."\u{00A0}".'₽</span>');
});

it('shows a company of an approved tier its own price with the retail one crossed out on the cards', function () {
    setting('pricing.max_discount_without_purchase', 20);
    $tier = PriceTier::factory()->create(['name' => 'Опт-1', 'discount_percent' => Percent::fromDecimal('10')]);
    $category = Category::factory()->create(['products_count' => 1]);
    Product::factory()->create(['category_id' => $category->id, 'is_hit' => true, 'retail_price' => Money::ofRubles(100_000)]);

    $this->actingAs(wholesaleCustomer($tier));

    $hits = homeBlock($this->get('/')->assertOk()->getContent(), 'hits');

    expect($hits)->toContain('<s class="gl-was">100'."\u{00A0}".'000'."\u{00A0}".'₽</s>', '<span class="gl-price">90'."\u{00A0}".'000'."\u{00A0}".'₽</span>');
});

it('gives every strip its own section and its own heading', function () {
    Setting::query()->create(['key' => 'catalog.local_warehouse_name', 'value' => 'Симферополь']);
    Setting::query()->create(['key' => 'catalog.local_strip_min_products', 'value' => 1]);
    $category = Category::factory()->create(['products_count' => 4]);
    $local = Warehouse::factory()->create(['name' => 'Симферополь', 'is_visible' => true]);

    $onShelf = Product::factory()->inStock()->create(['category_id' => $category->id, 'is_hit' => true, 'is_new' => true]);
    ProductStock::factory()->create(['product_id' => $onShelf->id, 'warehouse_id' => $local->id, 'status' => WarehouseStockStatus::InStock]);

    $html = $this->get('/')->assertOk()->getContent();

    foreach (['stock' => 'strip-in_stock', 'ready' => 'strip-local', 'hits' => 'strip-hits', 'fresh' => 'strip-new'] as $section => $heading) {
        expect(homeBlock($html, $section))->toContain('aria-labelledby="'.$heading.'"', 'id="'.$heading.'"');
    }

    expect(strip_tags(homeBlock($html, 'ready')))->toContain('Готово к отгрузке: Симферополь');
});

it('offers the collections as cards and leaves the block out without them', function () {
    $this->get('/')->assertOk()->assertDontSee('id="picks"', false);

    $category = Category::factory()->create(['products_count' => 1]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $collection = ProductCollection::factory()->create(['name' => 'Кафе до 50 посадок', 'slug' => 'kafe', 'is_active' => true]);
    $collection->products()->attach($product->id, ['sort' => 10]);

    $picks = homeBlock($this->get('/')->assertOk()->getContent(), 'picks');

    expect($picks)->toContain('aria-labelledby="collections-heading"', 'href="'.route('collection', 'kafe').'"', 'Кафе до 50 посадок')
        ->and(strip_tags($picks))->toContain('Соберём кухню под задачу');
});

it('lists at most ten brands and says how many there are in all', function () {
    $category = Category::factory()->create(['products_count' => 12]);

    foreach (Brand::factory()->count(12)->create() as $brand) {
        Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id]);
    }

    $brands = homeBlock($this->get('/')->assertOk()->getContent(), 'brands');

    expect(substr_count($brands, 'class="gl-card gl-card--quiet gl-brand"'))->toBe(10)
        ->and($brands)->toContain('href="'.route('brands').'"')
        ->and(strip_tags($brands))->toContain('Все 12 брендов', 'Часть из 12 производителей каталога.');
});

it('says all the brands are listed when they fit on the page', function () {
    $category = Category::factory()->create(['products_count' => 2]);

    foreach (Brand::factory()->count(2)->create() as $brand) {
        Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id]);
    }

    $brands = homeBlock($this->get('/')->assertOk()->getContent(), 'brands');

    expect(substr_count($brands, 'class="gl-card gl-card--quiet gl-brand"'))->toBe(2)
        ->and(strip_tags($brands))->toContain('Все 2 бренда', 'Все производители каталога.');
});

it('leaves the brands block out when the catalog has none', function () {
    $this->get('/')->assertOk()->assertDontSee('id="brands"', false);
});

it('asks for an article on the page itself with a form that searches like the one of the footer', function () {
    $sku = homeBlock($this->get('/')->assertOk()->getContent(), 'sku');

    expect($sku)->toContain(
        'aria-labelledby="home-sku"',
        '<form action="'.route('search').'" method="get" role="search"',
        'for="home-sku-field"',
        'id="home-sku-field"',
        'type="search"',
        'name="q"',
        'type="submit"',
    )
        ->and(strip_tags($sku))->toContain('Знаете артикул?', 'Найти');
});

it('does not ask the database more for many models on the shelves than for few', function () {
    $category = Category::factory()->create(['name' => 'Холодильное оборудование', 'show_on_home' => true, 'products_count' => 12]);

    Product::factory()->count(3)->inStock()->create(['category_id' => $category->id, 'is_hit' => true, 'is_new' => true]);
    $few = homeQueryCount();

    Product::factory()->count(9)->inStock()->create(['category_id' => $category->id, 'is_hit' => true, 'is_new' => true]);
    $many = homeQueryCount();

    expect($many)->toBe($few);
});
