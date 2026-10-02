<?php

use App\Jobs\SyncSupplierContentPage;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierRef;
use App\Models\Warehouse;
use App\Services\Supplier\Contracts\SupplierContentSourceInterface;
use App\Services\Supplier\Exceptions\FeedReadException;
use App\Services\Supplier\Sources\Rosholod\Api\RosholodApiClient;
use App\Services\Supplier\Sources\Rosholod\Api\RosholodApiContentSource;
use App\Services\Supplier\Sources\Rosholod\RosholodSiteSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

const API_TOKEN = 'dlr_v1.test.secret-value-1234';
const PRODUCT_ID = '0eb83628-3339-11ed-9cd4-00155d0a5704';
const PHOTO_MAIN = 'https://api.rosholod.org/media/products/main.webp';
const PHOTO_SECOND = 'https://api.rosholod.org/media/products/second.webp';

beforeEach(function () {
    Sleep::fake();
    Storage::fake('local');
    Http::preventStrayRequests();
    config([
        'suppliers.rosholod.api.token' => API_TOKEN,
        'suppliers.rosholod.api.base_url' => 'https://api.rosholod.org',
    ]);
});

/**
 * One page of a list the way the API answers it.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function apiPage(array $items, ?string $next = null): array
{
    return ['items' => $items, 'limit' => 100, 'offset' => 0, 'has_more' => $next !== null, 'next_cursor' => $next];
}

/**
 * A full card of the export.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiCard(array $overrides = []): array
{
    return $overrides + [
        'id' => '9c1d2a64-6f0e-4d2c-9b5e-0c7f3d2b1a10',
        'source_id' => PRODUCT_ID,
        'name' => 'Пароконвектомат ПКА 10-1/1ВП2-01',
        'code' => 'ЦБ-Ц0000123',
        'article' => '11000019106',
        'brand_id' => null,
        'product_type_id' => null,
        'country_id' => null,
        'promo' => false,
        'images' => [
            ['id' => 'a1', 'url' => PHOTO_SECOND, 'is_default' => false, 'width' => 800, 'height' => 600],
            ['id' => 'a2', 'url' => PHOTO_MAIN, 'is_default' => true, 'width' => 800, 'height' => 600],
        ],
        'description' => '<p>Паровая печь для кухни ресторана.</p>',
        'brand' => ['id' => 'b1', 'name' => 'Abat'],
        'product_type' => null,
        'country' => ['id' => 'c1', 'name' => 'россия'],
        'vat' => '20.00',
        'production_time' => '50',
        'order_option' => 'Заказное',
        'is_featured' => false,
        'attributes' => [
            'Длина, мм' => 1200,
            'Ширина, мм' => '800',
            'Высота, мм' => '1500',
            'Вес, кг' => 98.5,
            'Гарантия (месяцев)' => '12',
            'Мощность, кВт' => '15',
            'Бренд' => 'Abat',
            'Сенсорный экран' => true,
            'Пустая' => '—',
            'Список' => ['a', 'b'],
        ],
    ];
}

it('sends the token, walks the list by the cursor and keeps the cursor of the previous page', function () {
    Http::fake([
        'api.rosholod.org/api/v1/dealer/brands*' => Http::sequence()
            ->push(apiPage([['id' => '1', 'name' => 'A']], 'cursor-1'))
            ->push(apiPage([['id' => '2', 'name' => 'B']])),
    ]);

    $names = collect(app(RosholodApiClient::class)->items('/api/v1/dealer/brands'))->pluck('name')->all();

    expect($names)->toBe(['A', 'B']);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.API_TOKEN) && ! str_contains($request->url(), 'cursor'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'cursor=cursor-1') && str_contains($request->url(), 'limit=100'));
});

it('waits as long as Retry-After says after a 429 and tries again', function () {
    Http::fake(['api.rosholod.org/*' => Http::sequence()
        ->push(['detail' => 'slow down'], 429, ['Retry-After' => '3'])
        ->push(apiPage([]))]);

    app(RosholodApiClient::class)->get('/api/v1/dealer/brands');

    Sleep::assertSlept(fn ($duration): bool => $duration->totalSeconds === 3.0);
    Http::assertSentCount(2);
});

it('gives up on a 503 after a few tries and never puts the token into the message', function () {
    Http::fake(['api.rosholod.org/*' => Http::response('', 503)]);

    expect(fn () => app(RosholodApiClient::class)->get('/api/v1/dealer/brands'))
        ->toThrow(FeedReadException::class, 'API ответил кодом 503');

    Http::assertSentCount(4);
});

it('says the token is refused without repeating it', function () {
    Http::fake(['api.rosholod.org/*' => Http::response(['detail' => 'bad token '.API_TOKEN], 401)]);

    try {
        app(RosholodApiClient::class)->get('/api/v1/dealer/brands');
    } catch (FeedReadException $exception) {
        expect($exception->getMessage())->toContain('не принял токен')->not->toContain(API_TOKEN);

        return;
    }

    $this->fail('Ожидалась ошибка.');
});

it('asks for the token before the first request', function () {
    config(['suppliers.rosholod.api.token' => null]);

    expect(fn () => app(RosholodApiClient::class)->get('/api/v1/dealer/brands'))->toThrow(FeedReadException::class, 'ROSHOLOD_API_TOKEN');

    Http::assertNothingSent();
});

it('stops a walk that has more but gives no cursor', function () {
    Http::fake(['api.rosholod.org/*' => Http::response(['items' => [], 'has_more' => true, 'next_cursor' => null])]);

    expect(fn () => iterator_to_array(app(RosholodApiClient::class)->items('/api/v1/dealer/brands'), false))
        ->toThrow(FeedReadException::class, 'не прислал курсор');
});

it('turns a full card into photos and details, the main photo first', function () {
    Http::fake(['api.rosholod.org/api/v1/dealer/products/export*' => Http::response(apiPage([apiCard()], 'next-1'))]);

    $page = app(RosholodApiContentSource::class)->page(1);

    expect($page->nextCursor)->toBe('next-1')
        ->and($page->isLast())->toBeFalse()
        ->and($page->lastPage)->toBe(2)
        ->and($page->products[0]->externalId)->toBe(PRODUCT_ID)
        ->and($page->products[0]->urls)->toBe([PHOTO_MAIN, PHOTO_SECOND]);

    $details = $page->details[0];
    $attributes = collect($details->attributes)->mapWithKeys(fn ($attribute): array => [$attribute->key => $attribute->rawValue])->all();

    expect($details->externalId)->toBe(PRODUCT_ID)
        ->and($details->description)->toBe('Паровая печь для кухни ресторана.')
        ->and([$details->lengthMm, $details->widthMm, $details->heightMm, $details->weightKg, $details->warrantyMonths])->toBe([1200, 800, 1500, '98.5', 12])
        ->and($attributes)->toBe(['Мощность, кВт' => '15', 'Сенсорный экран' => 'Да', 'Страна производства' => 'Россия']);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'limit=20') && ! str_contains($request->url(), 'cursor'));
});

it('continues from the cursor and ends the walk on the last page', function () {
    Http::fake(['api.rosholod.org/api/v1/dealer/products/export*' => Http::response(apiPage([apiCard()]))]);

    $page = app(RosholodApiContentSource::class)->page(7, 'next-6');

    expect($page->isLast())->toBeTrue()
        ->and($page->nextCursor)->toBeNull()
        ->and($page->page)->toBe(7);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'cursor=next-6'));
});

it('finds our product by the identifier the config names and skips a card without it', function () {
    Http::fake(['api.rosholod.org/api/v1/dealer/products/export*' => Http::response(apiPage([
        apiCard(),
        apiCard(['id' => 'f0000000-0000-4000-8000-000000000001', 'source_id' => null]),
    ]))]);

    expect(app(RosholodApiContentSource::class)->page(1)->products)->toHaveCount(1);

    config(['suppliers.rosholod.api.external_id_field' => 'id']);

    $page = app(RosholodApiContentSource::class)->page(1);

    expect($page->products)->toHaveCount(2)
        ->and($page->products[0]->externalId)->toBe('9c1d2a64-6f0e-4d2c-9b5e-0c7f3d2b1a10');
});

it('takes photos only from the hosts of the supplier over https', function () {
    Http::fake(['api.rosholod.org/api/v1/dealer/products/export*' => Http::response(apiPage([apiCard(['images' => [
        ['id' => '1', 'url' => 'https://evil.example/x.webp', 'is_default' => true, 'width' => 1, 'height' => 1],
        ['id' => '2', 'url' => 'http://api.rosholod.org/media/insecure.webp', 'is_default' => false, 'width' => 1, 'height' => 1],
        ['id' => '3', 'url' => PHOTO_MAIN, 'is_default' => false, 'width' => 1, 'height' => 1],
    ]])]))]);

    expect(app(RosholodApiContentSource::class)->page(1)->products[0]->urls)->toBe([PHOTO_MAIN]);
});

it('downloads a photo without the token and refuses a stranger', function () {
    Http::fake([PHOTO_MAIN => Http::response('IMAGE-BYTES', 200)]);

    $source = app(RosholodApiContentSource::class);

    expect($source->download(PHOTO_MAIN))->toBe('IMAGE-BYTES')
        ->and(fn () => $source->download('https://evil.example/x.webp'))->toThrow(FeedReadException::class, 'не с адреса поставщика');

    Http::assertSent(fn (Request $request): bool => $request->url() === PHOTO_MAIN && ! $request->hasHeader('Authorization'));
});

it('picks the source of the content by the config', function () {
    config(['suppliers.rosholod.content_source' => 'site']);
    expect(app(SupplierContentSourceInterface::class))->toBeInstanceOf(RosholodSiteSource::class);

    config(['suppliers.rosholod.content_source' => 'api']);
    expect(app(SupplierContentSourceInterface::class))->toBeInstanceOf(RosholodApiContentSource::class);
});

it('hands the cursor of a page to the job of the next one', function () {
    config(['suppliers.rosholod.content_source' => 'api']);
    Queue::fake();
    $supplier = Supplier::factory()->create(['slug' => 'rosholod']);
    Product::factory()->create(['supplier_id' => $supplier->id, 'external_id' => PRODUCT_ID]);
    Http::fake([
        'api.rosholod.org/api/v1/dealer/products/export*' => Http::response(apiPage([apiCard(['images' => []])], 'next-2')),
    ]);

    app()->call([new SyncSupplierContentPage($supplier->id, 4, null, 'next-1'), 'handle']);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'cursor=next-1'));
    Queue::assertPushed(SyncSupplierContentPage::class, function (SyncSupplierContentPage $job): bool {
        return (fn () => [$this->page, $this->cursor])->call($job) === [5, 'next-2'];
    });
});

it('starts the walk of the API from the first page whatever page was asked', function () {
    config(['suppliers.rosholod.content_source' => 'api']);
    Queue::fake();
    Supplier::factory()->create(['slug' => 'rosholod']);

    $this->artisan('supplier:content', ['--page' => 9])
        ->expectsOutputToContain('Загрузка начата с первой страницы')
        ->assertSuccessful();

    Queue::assertPushed(SyncSupplierContentPage::class, fn (SyncSupplierContentPage $job): bool => (fn () => $this->page)->call($job) === 1);
});

it('reports what the API knows against what we have, without the token', function () {
    $supplier = Supplier::factory()->create(['slug' => 'rosholod']);
    Product::factory()->create(['supplier_id' => $supplier->id, 'external_id' => PRODUCT_ID, 'supplier_code' => 'ЦБ-Ц0000123', 'sku' => '11000019106']);
    Product::factory()->create(['supplier_id' => $supplier->id, 'external_id' => 'only-ours', 'supplier_code' => 'ЦБ-Ц9', 'sku' => '1']);
    Warehouse::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Москва (ЦФО)']);
    SupplierRef::factory()->create(['supplier_id' => $supplier->id, 'entity' => 'category', 'external_key' => 'k1', 'name' => 'Пароконвектоматы']);

    Http::fake([
        'api.rosholod.org/api/v1/dealer/products/export*' => Http::response(apiPage([apiCard()])),
        'api.rosholod.org/api/v1/dealer/products*' => Http::response(apiPage([
            ['id' => '9c1d2a64-6f0e-4d2c-9b5e-0c7f3d2b1a10', 'source_id' => PRODUCT_ID, 'name' => 'Пароконвектомат', 'code' => 'ЦБ-Ц0000123', 'article' => '11000019106', 'images' => [['id' => 'a', 'url' => PHOTO_MAIN, 'is_default' => true, 'width' => 8, 'height' => 6]]],
            ['id' => 'f0000000-0000-4000-8000-000000000002', 'source_id' => null, 'name' => 'Новый товар', 'code' => 'ЦБ-Ц5', 'article' => null, 'images' => []],
        ])),
        'api.rosholod.org/api/v1/dealer/product-types*' => Http::response(apiPage([
            ['id' => 't1', 'name' => 'Пароконвектоматы', 'parent_id' => null],
            ['id' => 't2', 'name' => 'Подвид', 'parent_id' => 't1'],
            ['id' => 't3', 'name' => 'Сирота', 'parent_id' => 'hidden'],
        ])),
        'api.rosholod.org/api/v1/dealer/warehouses*' => Http::response(apiPage([
            ['id' => 'w1', 'name' => 'Москва (ЦФО)', 'city' => 'Москва', 'address' => '', 'work_time' => ''],
            ['id' => 'w2', 'name' => 'Волжск', 'city' => 'Волжск', 'address' => '', 'work_time' => ''],
        ])),
        'api.rosholod.org/api/v1/dealer/brands*' => Http::response(apiPage([])),
        'api.rosholod.org/api/v1/dealer/prices*' => Http::response(apiPage([['product_id' => 'p', 'price' => '100.00', 'last_received_at' => null]])),
        'api.rosholod.org/api/v1/dealer/stocks*' => Http::response(apiPage([['product_id' => 'p', 'warehouse_id' => 'w1', 'available' => 0, 'last_received_at' => null]])),
    ]);

    $this->artisan('supplier:api-check', ['--prices' => true, '--stocks' => true])
        ->expectsOutputToContain('catalog:read: есть')
        ->expectsOutputToContain('в API: 2, у нас у поставщика: 2')
        ->expectsOutputToContain('source_id ↔ external_id: 1')
        ->expectsOutputToContain('у нас есть, а в API нет: 1')
        ->expectsOutputToContain('видов: 3, корневых: 1, глубина: 2, без родителя в выдаче: 1')
        ->expectsOutputToContain('складов в API: 2, у нас: 1, совпали по названию: 1')
        ->expectsOutputToContain('строк цен: 1')
        ->expectsOutputToContain('строк остатков: 1')
        ->assertSuccessful();

    $report = Storage::disk('local')->get('rosholod-api-check.txt');

    expect($report)->toContain('Мощность, кВт (1)')->not->toContain(API_TOKEN);
});

it('does not start the check without a token', function () {
    config(['suppliers.rosholod.api.token' => null]);

    $this->artisan('supplier:api-check')->expectsOutputToContain('ROSHOLOD_API_TOKEN')->assertFailed();

    Http::assertNothingSent();
});
