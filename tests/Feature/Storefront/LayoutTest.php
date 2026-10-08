<?php

use App\Livewire\InstantSearch;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
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

/**
 * The bottom bar of the phone, cut out of the page: Telegram and the cart link also stand in the strip and the footer.
 */
function phoneDock(TestResponse $response): string
{
    preg_match('/<nav[^>]*class="gl-dock".*?<\/nav>/s', $response->getContent(), $match);

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

it('shows the main sections as plates and keeps the rest under «Ещё»', function () {
    Category::factory()->create(['name' => 'Холодильное', 'products_count' => 5, 'show_on_home' => true]);
    Category::factory()->create(['name' => 'Весовое', 'products_count' => 5, 'show_on_home' => true]);
    Category::factory()->create(['name' => 'Прочее неликвид', 'products_count' => 5, 'show_on_home' => false]);
    Category::factory()->create(['name' => 'Аксессуары', 'products_count' => 5, 'show_on_home' => false]);

    $row = sectionsRow($this->get('/catalog')->assertOk());
    preg_match('/<ul class="flex h-22.*?<\/ul>/s', $row, $plates);

    // Only the main ones are plates; the others are in the list of «Ещё», which is always there.
    expect($plates[0])->toContain('Холодильное', 'Весовое')->not->toContain('Прочее неликвид')->not->toContain('Аксессуары')
        ->and($row)->toContain('data-priority-always', 'Прочее неликвид', 'Аксессуары')
        ->and(substr_count($row, 'data-priority-item'))->toBe(2)
        ->and(substr_count($row, 'data-priority-extra'))->toBe(2);
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

it('links the messengers from the settings in the service strip and the footer, Telegram also in the phone dock', function () {
    setting('contacts.telegram', '@gastrosnab');
    setting('contacts.max', 'https://max.ru/u/f9LHodD0cOKrE8Rl');

    $response = $this->get('/')->assertOk();

    // Telegram: the strip, the footer and the bottom bar of the phone; MAX: the strip and the footer.
    expect(substr_count($response->getContent(), 'href="https://t.me/gastrosnab"'))->toBe(3)
        ->and(substr_count($response->getContent(), 'href="https://max.ru/u/f9LHodD0cOKrE8Rl"'))->toBe(2);

    $response->assertSee('aria-label="Написать в Telegram"', false)->assertSee('rel="noopener"', false);
});

it('shows no messenger the settings do not give a valid link for', function () {
    setting('contacts.telegram', 'javascript:alert(1)');
    setting('contacts.max', null);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-messenger', false)
        ->assertDontSee('javascript:alert', false);
});

it('lays the live scene behind every page, errors and the missing page included', function () {
    foreach (['/', '/catalog', '/brands', '/search?q=шкаф', '/no-such-page'] as $path) {
        $this->get($path)->assertSee('<div class="gl-stage__bg" aria-hidden="true">', false);
    }

    expect(view('errors.500')->render())->toContain('<div class="gl-stage__bg" aria-hidden="true">', 'class="gl-pill"', config('app.name'));
});

it('draws the header as a pill with the mark, the real links, the search and the cart', function () {
    $response = $this->get('/')->assertOk()->assertSeeLivewire(InstantSearch::class);

    preg_match('/<nav class="gl-nav".*?<\/nav>/s', $response->getContent(), $nav);

    expect($nav[0] ?? '')->toContain('href="'.route('catalog').'"', 'href="'.route('brands').'"', 'href="'.route('wholesale').'"', 'Каталог', 'Бренды', 'Оптовым клиентам');

    $response->assertSee('<svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">', false)
        ->assertSeeInOrder(['class="gl-pill"', 'class="gl-logo"', 'class="gl-nav"', 'id="site-search"', 'data-cart-link'], false);
});

it('marks where the customer is in the links of the header', function () {
    $brands = $this->get(route('brands'))->assertOk()->getContent();

    expect($brands)->toMatch('/<a href="'.preg_quote(route('brands'), '/').'"\s+aria-current="page"/')
        ->and($brands)->not->toMatch('/<a href="'.preg_quote(route('catalog'), '/').'"\s+aria-current/');

    $category = Category::factory()->create(['products_count' => 3]);

    expect($this->get(route('category', $category))->getContent())
        ->toMatch('/<a href="'.preg_quote(route('catalog'), '/').'"\s+aria-current="true"/');
});

it('shows the sign-in link to a guest and the account menu to a customer', function () {
    $this->get('/')->assertOk()
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('aria-label="Войти в личный кабинет"', false)
        ->assertDontSee('action="'.route('logout').'"', false);

    $this->actingAs(User::factory()->create(['name' => 'Алексей Иванов']))->get('/')->assertOk()
        ->assertSee('aria-label="Кабинет: Алексей Иванов"', false)
        ->assertSee('href="'.route('account.orders').'"', false)
        ->assertSee('action="'.route('logout').'"', false)
        ->assertDontSee('aria-label="Войти в личный кабинет"', false);
});

it('keeps the first screen of the home page inside the scene and gives it a main without margins', function () {
    $this->get('/')->assertOk()
        ->assertSeeInOrder(['class="gl-stage__bg"', 'class="gl-stage__hero"', '<main id="content" class="flex-1">'], false);

    $this->get('/brands')->assertOk()
        ->assertDontSee('class="gl-stage__hero"', false)
        ->assertSee('<main id="content" class="flex-1 container-page py-6 md:py-8">', false);
});

it('keeps the field for a known article in the footer working', function () {
    $response = $this->get('/')->assertOk();

    preg_match('/<footer class="gl-foot.*?<\/footer>/s', $response->getContent(), $footer);

    expect($footer[0] ?? '')->toContain('Знаете артикул?', 'action="'.route('search').'"', 'id="footer-search"', 'name="q"', 'class="gl-find"', 'Найти');
});

it('puts the cart and Telegram into the bottom bar of the phone', function () {
    setting('contacts.telegram', '@gastrosnab');

    expect(phoneDock($this->get('/')->assertOk()))
        ->toContain('aria-label="Быстрые действия"', 'href="'.route('cart').'"', 'Корзина', 'href="https://t.me/gastrosnab"', 'Написать в Telegram')
        ->not->toContain('Каталог');
});

it('sends the second button of the bottom bar to the catalog until Telegram is set', function () {
    expect(phoneDock($this->get('/')->assertOk()))
        ->toContain('href="'.route('cart').'"', 'href="'.route('catalog').'"', 'Каталог')
        ->not->toContain('Telegram');
});

it('leaves the bottom of the page to the purchase bar of the product and to the cart itself', function () {
    $category = Category::factory()->create(['products_count' => 1]);
    $product = Product::factory()->inStock()->create(['category_id' => $category->id]);

    $this->get(route('product', $product))->assertOk()
        ->assertSee('data-sticky-buy', false)
        ->assertDontSee('class="gl-dock"', false);

    $this->get(route('cart'))->assertOk()->assertDontSee('class="gl-dock"', false);
    $this->get(route('catalog'))->assertOk()->assertSee('class="gl-dock"', false);
});

it('lifts the notices and the cookie banner above the purchase bar of the product page only', function () {
    $category = Category::factory()->create(['products_count' => 1]);
    $product = Product::factory()->inStock()->create(['category_id' => $category->id]);

    expect($this->get(route('product', $product))->getContent())
        ->toMatch('/<div\s+data-notices[^>]*\bdata-bar\b[^>]*class="gl-notices"/')
        ->toMatch('/<section\s+data-cookie-banner[^>]*\bdata-bar\b/');

    expect($this->get(route('catalog'))->getContent())
        ->not->toMatch('/data-notices[^>]*\bdata-bar\b/')
        ->not->toMatch('/data-cookie-banner[^>]*\bdata-bar\b/');
});
