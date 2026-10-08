<?php

use App\Enums\Availability;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Pricing\Price;
use App\Support\CategoryZone;
use App\Support\Money;
use App\View\HomeShelf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A product the way the strips of the home page load it: the brand, the section and the photos come with it.
 *
 * @param  array<string, mixed>  $attributes
 */
function shelfProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes)->load(['brand', 'category', 'media']);
}

it('prepares a product for a card: brand, name, status, zone and price', function () {
    $cold = Category::factory()->create(['name' => 'Холодильное оборудование', 'icon' => 'refrigeration']);
    $product = shelfProduct([
        'name' => 'Шкаф холодильный',
        'model' => 'ШХ-0,7',
        'category_id' => $cold->id,
        'brand_id' => Brand::factory()->create(['name' => 'Abat'])->id,
        'availability' => Availability::InStock,
    ]);
    $price = new Price(Money::ofRubles(96_750), Money::ofRubles(96_750));

    $item = HomeShelf::from([$product], [$product->id => $price])->items[0];

    expect($item)->toMatchArray([
        'brand' => 'Abat',
        'name' => 'Шкаф холодильный',
        'icon' => 'refrigeration',
        'zone' => CategoryZone::COLD,
        'availability' => Availability::InStock,
        'image' => null,
    ])
        ->and($item['product']->is($product))->toBeTrue()
        ->and($item['price'])->toBe($price);
});

it('splits the model off the start of a name so that it can be set in monospace', function () {
    $products = [
        shelfProduct(['name' => 'ПКА 10-1/1 Пароконвектомат', 'model' => 'ПКА 10-1/1']),
        shelfProduct(['name' => 'Пароконвектомат ПКА 10-1/1', 'model' => 'ПКА 10-1/1']),
        shelfProduct(['name' => 'ПКА 10-1/1', 'model' => 'ПКА 10-1/1']),
        shelfProduct(['name' => 'ПКА 10-1/12 Пароконвектомат', 'model' => 'ПКА 10-1/1']),
        shelfProduct(['name' => 'Мармит без модели', 'model' => null]),
    ];

    $items = HomeShelf::from($products, [])->items;

    expect(array_column($items, 'lead'))->toBe(['ПКА 10-1/1', null, null, null, null])
        ->and(array_column($items, 'rest'))->toBe([
            'Пароконвектомат',
            'Пароконвектомат ПКА 10-1/1',
            'ПКА 10-1/1',
            'ПКА 10-1/12 Пароконвектомат',
            'Мармит без модели',
        ]);
});

it('has no brand and no icon, and a neutral zone, for a product of a plain section', function () {
    $product = shelfProduct([
        'category_id' => Category::factory()->create(['name' => 'Аксессуары', 'icon' => null])->id,
        'brand_id' => null,
    ]);

    $item = HomeShelf::from([$product], [])->items[0];

    expect($item['brand'])->toBeNull()
        ->and($item['icon'])->toBeNull()
        ->and($item['zone'])->toBe(CategoryZone::NEUTRAL)
        ->and($item['price'])->toBeNull();
});

it('takes the zone of a product from the name of its section when the manager gave no icon', function () {
    $hot = shelfProduct(['category_id' => Category::factory()->create(['name' => 'Тепловое оборудование', 'icon' => null])->id]);

    expect(HomeShelf::from([$hot], [])->items[0]['zone'])->toBe(CategoryZone::HOT);
});

it('gives the price to the product it was resolved for and none to the others', function () {
    $priced = shelfProduct();
    $asked = shelfProduct();
    $price = new Price(Money::ofRubles(1_000), Money::ofRubles(1_000));

    $items = HomeShelf::from([$priced, $asked], [$priced->id => $price, $asked->id => null])->items;

    expect($items[0]['price'])->toBe($price)
        ->and($items[1]['price'])->toBeNull()
        ->and(HomeShelf::from([$asked], [])->items[0]['price'])->toBeNull();
});

it('takes the card picture of the first photo, and none for a product without a ready one', function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'media-library.queue_conversions_by_default' => false]);

    $photographed = Product::factory()->create();
    $photographed->addMedia(UploadedFile::fake()->image('shkaf.jpg', 800, 600))->toMediaCollection(Product::IMAGES);
    $photographed = $photographed->refresh()->load(['brand', 'category', 'media']);
    $bare = shelfProduct();

    $items = HomeShelf::from([$photographed, $bare], [])->items;

    expect($items[0]['image'])->toBe($photographed->getFirstMediaUrl(Product::IMAGES, 'card'))->not->toBeEmpty()
        ->and($items[1]['image'])->toBeNull();
});

it('turns into an accordion from three products and stays a row of cards below that', function (int $count, bool $accordion) {
    $shelf = HomeShelf::from(array_map(fn (): Product => shelfProduct(), array_fill(0, $count, null)), []);

    expect($shelf->items)->toHaveCount($count)
        ->and($shelf->isAccordion())->toBe($accordion);
})->with([
    'nothing' => [0, false],
    'one' => [1, false],
    'two' => [2, false],
    'three' => [3, true],
    'six' => [6, true],
]);

it('does not ask the database anything while it prepares loaded products', function () {
    $products = [shelfProduct(), shelfProduct(), shelfProduct()];
    $prices = [];

    DB::enableQueryLog();
    HomeShelf::from($products, $prices);

    expect(DB::getQueryLog())->toBe([]);
});
