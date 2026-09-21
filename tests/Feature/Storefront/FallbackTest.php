<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('leads the old address of a product to the new one and counts the visit', function () {
    $product = Product::factory()->create(['slug' => 'shkaf-staryy']);
    $product->update(['slug' => 'shkaf-novyy']);

    $this->get('/product/shkaf-staryy')
        ->assertStatus(301)
        ->assertRedirect('/product/shkaf-novyy');

    expect(Redirect::query()->where('from_path', '/product/shkaf-staryy')->value('hits'))->toBe(1);
});

it('keeps the query string of the old address', function () {
    Redirect::query()->create(['from_path' => '/catalog/staryy-razdel', 'to_path' => '/catalog/novyy-razdel']);

    $this->get('/catalog/staryy-razdel?in_stock=1&sort=price_asc')
        ->assertStatus(301)
        ->assertRedirect('/catalog/novyy-razdel?in_stock=1&sort=price_asc');
});

it('follows a redirect from an address the storefront has no route for', function () {
    Redirect::query()->create(['from_path' => '/old-site/about.html', 'to_path' => '/o-kompanii', 'status_code' => 302]);

    $this->get('/old-site/about.html')->assertStatus(302)->assertRedirect('/o-kompanii');
});

it('never redirects a form that was sent to a missing address', function () {
    Redirect::query()->create(['from_path' => '/staraya-forma', 'to_path' => '/']);

    $this->post('/staraya-forma')->assertStatus(405);
});

it('opens a page the manager has switched on and hides one that is off', function () {
    Page::factory()->create([
        'slug' => 'dostavka',
        'title' => 'Доставка',
        'content' => "## Самовывоз\n\nСклад открыт с 9 до 18.\n\n- по городу\n- по Крыму",
        'meta_title' => 'Доставка оборудования',
    ]);
    Page::factory()->create(['slug' => 'garantiya', 'is_active' => false]);

    $this->get('/dostavka')
        ->assertOk()
        ->assertSee('<title>Доставка оборудования', false)
        ->assertSee('<h2>Самовывоз</h2>', false)
        ->assertSee('<li>по Крыму</li>', false);

    $this->get('/garantiya')->assertNotFound();
});

it('shows only what Markdown can say in the text of a page', function () {
    Page::factory()->create([
        'slug' => 'oplata',
        'content' => "Оплата по счёту.\n\n<script>alert(1)</script>\n\n[ссылка](javascript:alert(1))",
    ]);

    $this->get('/oplata')
        ->assertOk()
        ->assertSee('Оплата по счёту.')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('javascript:alert', false);
});

it('answers an unknown address with the storefront 404 page', function () {
    Category::factory()->create(['name' => 'Холодильное оборудование', 'slug' => 'holodilnoe', 'products_count' => 12]);

    $this->get('/net-takoy-stranicy')
        ->assertNotFound()
        ->assertSee('Такой страницы нет')
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('action="'.route('search').'"', false)
        ->assertSee('Холодильное оборудование')
        ->assertSee(route('category', 'holodilnoe'));
});

it('answers a missing product with the same 404 page', function () {
    $this->get('/product/net-takogo-tovara')->assertNotFound()->assertSee('Такой страницы нет');
});

it('builds the 500 page without the database', function () {
    DB::enableQueryLog();
    $html = view('errors.500')->render();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0)
        ->and($html)->toContain('Ошибка на сайте', 'Что-то сломалось на нашей стороне', 'На главную');
});

it('explains an expired form in Russian', function () {
    expect(view('errors.419')->render())->toContain('Страница устарела', 'Обновите страницу');
});

it('answers any other error in Russian too', function () {
    expect(view('errors.4xx', ['exception' => new HttpException(405)])->render())->toContain('Запрос не выполнен', 'Открыть каталог')->not->toContain('Ошибка 405')
        ->and(view('errors.5xx', ['exception' => new HttpException(502)])->render())->toContain('Ошибка на сайте');
});
