<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Catalog\CatalogCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * The desktop row of root sections, cut out of the page: category names also appear in
 * the menu, the footer and the page itself.
 */
function sectionsRow(TestResponse $response): string
{
    preg_match('/<nav[^>]*data-priority-nav.*?<\/nav>/s', $response->getContent(), $match);

    return $match[0] ?? '';
}

it('shows the contacts from the settings in the header and the footer', function () {
    Setting::query()->create(['key' => 'site.name', 'value' => 'Проф Кухня']);
    Setting::query()->create(['key' => 'contacts.phones', 'value' => '+7 978 123-45-67, +7 3652 60-00-00']);
    Setting::query()->create(['key' => 'contacts.schedule', 'value' => 'Пн–Пт 9:00–18:00']);
    Setting::query()->create(['key' => 'contacts.email', 'value' => 'zakaz@example.ru']);
    Setting::query()->create(['key' => 'contacts.address', 'value' => 'Симферополь, ул. Промышленная, 1']);
    Setting::query()->create(['key' => 'seller.requisites', 'value' => 'ООО «Проф Кухня», ИНН 9102000000']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>'.e(__('shop.home.title')).' | Проф Кухня</title>', false)
        ->assertSee('href="tel:+79781234567"', false)
        ->assertSee('href="tel:+73652600000"', false)
        ->assertSee('Пн–Пт 9:00–18:00')
        ->assertSee('href="mailto:zakaz@example.ru"', false)
        ->assertSee('Симферополь, ул. Промышленная, 1')
        ->assertSee('ООО «Проф Кухня», ИНН 9102000000')
        ->assertSee('Цены на сайте не являются публичной офертой.');
});

it('names the shop after the application until the customer gives a name', function () {
    config(['app.name' => 'HoReCa Shop']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>'.e(__('shop.home.title')).' | HoReCa Shop</title>', false);
});

it('lists the switched-on root sections with products and marks the current one', function () {
    $thermal = Category::factory()->create(['name' => 'Тепловое оборудование', 'sort' => 1, 'products_count' => 412]);
    $cold = Category::factory()->create(['name' => 'Холодильное оборудование', 'sort' => 2, 'products_count' => 286]);
    Category::factory()->inactive()->create(['name' => 'Скрытый раздел', 'products_count' => 9]);
    Category::factory()->create(['name' => 'Пустой раздел', 'products_count' => 0]);
    $ovens = Category::factory()->childOf($thermal)->create(['name' => 'Пароконвектоматы', 'products_count' => 34]);

    $row = sectionsRow($this->get(route('category', $ovens))->assertOk());

    expect($row)
        ->toContain('Тепловое оборудование', 'Холодильное оборудование')
        ->not->toContain('Скрытый раздел', 'Пустой раздел')
        ->toMatch('/<a\s[^>]*href="'.preg_quote(route('category', $thermal), '/').'"[^>]*aria-current="true"/')
        ->not->toMatch('/<a\s[^>]*href="'.preg_quote(route('category', $cold), '/').'"[^>]*aria-current/');

    expect(mb_substr_count($row, 'Пароконвектоматы'))->toBe(0);
});

it('marks the section of the product on its page', function () {
    $cold = Category::factory()->create(['name' => 'Холодильное оборудование', 'products_count' => 1]);
    $cabinets = Category::factory()->childOf($cold)->create(['name' => 'Шкафы холодильные', 'products_count' => 1]);
    $product = Product::factory()->create(['category_id' => $cabinets->id]);

    $row = sectionsRow($this->get(route('product', $product))->assertOk());

    expect($row)->toMatch('/<a\s[^>]*href="'.preg_quote(route('category', $cold), '/').'"[^>]*aria-current="true"/');
});

it('offers every section under «Ещё» when scripts are off', function () {
    Category::factory()->count(3)->create(['products_count' => 5]);

    $row = sectionsRow($this->get('/')->assertOk());

    expect(substr_count($row, 'data-priority-item'))->toBe(3)
        ->and(substr_count($row, 'data-priority-extra'))->toBe(3);
});

it('links only the pages the manager has switched on', function () {
    Page::factory()->create(['slug' => 'dostavka', 'title' => 'Доставка и самовывоз']);
    Page::factory()->create(['slug' => 'oplata', 'title' => 'Оплата по счёту', 'is_active' => false]);
    Page::factory()->create(['slug' => 'politika-konfidencialnosti', 'title' => 'Политика конфиденциальности']);

    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.url('dostavka').'"', false)
        ->assertSee('Доставка и самовывоз')
        ->assertSee('href="'.url('politika-konfidencialnosti').'"', false)
        ->assertDontSee('Оплата по счёту')
        ->assertDontSee('href="'.url('oplata').'"', false);
});

it('keeps the root sections in the catalog cache until the catalog changes', function () {
    $category = Category::factory()->create(['name' => 'Барное оборудование', 'products_count' => 158]);

    $this->get('/')->assertOk();

    // Past the model events, as a bulk update would do: the cached row stays until a bump.
    DB::table('categories')->where('id', $category->id)->update(['name' => 'Барное и кофейное']);

    expect(sectionsRow($this->get('/')))->toContain('Барное оборудование');

    app(CatalogCache::class)->bump();

    expect(sectionsRow($this->get('/')))->toContain('Барное и кофейное');
});

it('reads the settings of the layout with one query', function () {
    DB::enableQueryLog();
    $this->get('/search?q=шкаф')->assertOk();

    $layoutKeys = ['site.name', 'contacts.phones', 'contacts.email', 'contacts.schedule', 'contacts.address', 'seller.requisites'];

    $queries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], '`settings`')
        && array_intersect($layoutKeys, $query['bindings']) !== []);

    expect($queries)->toHaveCount(1);
});
