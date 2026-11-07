<?php

use App\Enums\Availability;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('exports only eligible retail offers with canonical addresses and current availability', function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'seo.origin' => 'https://gastrosnab.ru']);
    $root = Category::factory()->create(['is_active' => true]);
    $category = Category::factory()->create(['parent_id' => $root->id, 'is_active' => true]);
    $attributes = [
        'category_id' => $category->id,
        'name' => 'Стол & полка',
        'description' => 'Описание & параметры.',
        'retail_price' => Money::ofRubles(15000),
        'availability' => Availability::Low,
        'is_visible' => true,
    ];
    $product = Product::factory()->create($attributes);
    $onOrder = Product::factory()->create(array_replace($attributes, ['availability' => Availability::OnOrder]));
    $hidden = Product::factory()->create(array_replace($attributes, ['is_visible' => false]));
    $noPrice = Product::factory()->create(array_replace($attributes, ['retail_price' => null]));
    $noDescription = Product::factory()->create(array_replace($attributes, ['description' => null]));
    Product::factory()->create($attributes);
    foreach ([$product, $onOrder, $hidden, $noPrice, $noDescription] as $item) {
        $item->addMedia(UploadedFile::fake()->image('photo.jpg', 500, 500))->toMediaCollection(Product::IMAGES, 'public');
    }

    $response = $this->get('/yandex.yml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string($response->streamedContent());
    expect($xml)->not->toBeFalse()
        ->and($xml->shop->offers->offer)->toHaveCount(2)
        ->and((string) $xml->shop->offers->offer[0]['available'])->toBe('true')
        ->and((string) $xml->shop->offers->offer[1]['available'])->toBe('false')
        ->and((string) $xml->shop->offers->offer[0]->url)->toBe('https://gastrosnab.ru/product/'.$product->slug)
        ->and((string) $xml->shop->offers->offer[0]->name)->toBe('Стол & полка')
        ->and((string) $xml->shop->offers->offer[0]->price)->toBe('15000.00')
        ->and((string) $xml->shop->offers->offer[0]->currencyId)->toBe('RUR')
        ->and((string) $xml->shop->categories->category[1]['parentId'])->toBe((string) $root->id);
});
