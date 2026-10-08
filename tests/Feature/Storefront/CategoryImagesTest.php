<?php

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CategoryImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'media-library.queue_conversions_by_default' => false]);
});

/**
 * Товар раздела с настоящим фото: уменьшенные копии делаются сразу, как на сервере после очереди.
 *
 * @param  array<string, mixed>  $attributes
 */
function categoryPhotoProduct(array $attributes): Product
{
    $product = Product::factory()->create($attributes);
    $product->addMedia(UploadedFile::fake()->image('shkaf.jpg', 800, 600))->toMediaCollection(Product::IMAGES);

    return $product->refresh();
}

function cardUrl(Product $product): string
{
    return $product->getFirstMediaUrl(Product::IMAGES, 'card');
}

it('shows the photo of the most popular product of the section on the home tile', function () {
    $root = Category::factory()->create(['name' => 'Холодильное оборудование', 'show_on_home' => true, 'products_count' => 3]);
    $child = Category::factory()->childOf($root)->create(['products_count' => 2]);

    categoryPhotoProduct(['category_id' => $child->id, 'popularity' => 10]);
    $popular = categoryPhotoProduct(['category_id' => $child->id, 'popularity' => 90]);
    categoryPhotoProduct(['category_id' => $root->id, 'popularity' => 40]);

    $this->get('/')
        ->assertOk()
        ->assertSee(cardUrl($popular), false);
});

it('prefers a product in stock to a more popular one on request', function () {
    $root = Category::factory()->create(['show_on_home' => true, 'products_count' => 2]);

    categoryPhotoProduct(['category_id' => $root->id, 'popularity' => 500]);
    $inStock = categoryPhotoProduct(['category_id' => $root->id, 'popularity' => 1, 'availability' => 'in_stock']);

    expect(app(CategoryImages::class)->for([$root->id]))->toBe([$root->id => cardUrl($inStock)]);
});

it('ignores hidden and discontinued products, products of switched-off sections and photos without a ready copy', function () {
    $root = Category::factory()->create(['show_on_home' => true, 'products_count' => 4]);
    $off = Category::factory()->childOf($root)->inactive()->create();

    categoryPhotoProduct(['category_id' => $root->id, 'is_visible' => false, 'popularity' => 100]);
    categoryPhotoProduct(['category_id' => $root->id, 'availability' => 'discontinued', 'popularity' => 100]);
    categoryPhotoProduct(['category_id' => $off->id, 'popularity' => 100]);

    $unconverted = categoryPhotoProduct(['category_id' => $root->id, 'popularity' => 100]);
    $unconverted->getFirstMedia(Product::IMAGES)->forceFill(['generated_conversions' => []])->save();

    $response = $this->get('/')->assertOk();

    expect(app(CategoryImages::class)->for([$root->id]))->toBe([$root->id => null]);
    preg_match('/<main.*?<\/main>/s', $response->getContent(), $main);
    expect($main[0])->not->toContain('<img');
});

it('shows the picture the manager uploaded instead of a product photo', function () {
    $root = Category::factory()->create(['show_on_home' => true, 'products_count' => 1]);
    $product = categoryPhotoProduct(['category_id' => $root->id]);

    $root->addMedia(UploadedFile::fake()->image('razdel.png', 1000, 800))->toMediaCollection(Category::IMAGE);
    $picture = $root->refresh()->getFirstMediaUrl(Category::IMAGE, 'tile');

    $images = app(CategoryImages::class)->for([$root->id]);

    expect($images[$root->id])->toBe($picture)->not->toBe(cardUrl($product));
    $this->get('/')->assertSee($picture, false);
});

it('keeps the picture in the cache until the catalog changes', function () {
    $root = Category::factory()->create(['show_on_home' => true]);
    $product = categoryPhotoProduct(['category_id' => $root->id]);

    $images = app(CategoryImages::class);
    $images->for([$root->id]);

    DB::enableQueryLog();
    $second = $images->for([$root->id]);
    expect(DB::getQueryLog())->toBe([])
        ->and($second[$root->id])->toBe(cardUrl($product));

    // The product is hidden: the import or the manager bumps the version, the picture goes.
    $product->forceFill(['is_visible' => false])->save();
    app(CatalogCache::class)->bump();

    expect($images->for([$root->id])[$root->id])->toBeNull();
});

it('renders the home page with photos in a fixed number of queries', function () {
    foreach (Category::factory()->count(8)->create(['show_on_home' => true, 'products_count' => 1]) as $root) {
        categoryPhotoProduct(['category_id' => $root->id]);
    }

    DB::enableQueryLog();
    $this->get('/')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(24);
});

it('shows the picture on the catalog page', function () {
    $root = Category::factory()->create(['name' => 'Тепловое оборудование']);
    $product = categoryPhotoProduct(['category_id' => $root->id]);

    $this->get('/catalog')->assertOk()->assertSee(cardUrl($product), false);
});

it('turns the biggest subsections of a category into tiles and keeps the rest as chips', function () {
    $root = Category::factory()->create(['slug' => 'holodilnoe', 'icon' => 'refrigeration', 'products_count' => 60]);
    $children = collect(range(1, 14))->map(fn (int $number): Category => Category::factory()->childOf($root)->create([
        'name' => "Подраздел {$number}",
        'products_count' => $number,
    ]));
    $photo = categoryPhotoProduct(['category_id' => $children->last()->id]);

    $response = $this->get('/catalog/holodilnoe')->assertOk();

    // Twelve biggest are tiles with the picture; the two smallest wait behind «Ещё 2 раздела».
    preg_match('/<details[^>]*>\s*<summary[^>]*>\s*Ещё 2 раздела.*?<\/details>/s', $response->getContent(), $more);

    expect($response->getContent())->toContain(cardUrl($photo), 'Ещё 2 раздела')
        ->and($more[0])->toContain('Подраздел 1', 'Подраздел 2')->not->toContain('Подраздел 3');
});

it('uploads the picture of a section from the admin and shows it at once', function () {
    $this->actingAs(staffUser());
    $root = Category::factory()->create(['show_on_home' => true, 'products_count' => 1]);

    $this->get('/')->assertOk();
    expect(app(CategoryImages::class)->for([$root->id])[$root->id])->toBeNull();

    Livewire::test(EditCategory::class, ['record' => $root->id])
        ->fillForm(['image' => UploadedFile::fake()->image('razdel.jpg', 900, 700)])
        ->call('save')
        ->assertHasNoFormErrors();

    $uploaded = $root->refresh()->getFirstMediaUrl(Category::IMAGE, 'tile');

    expect($uploaded)->not->toBe('')
        ->and(app(CategoryImages::class)->for([$root->id])[$root->id])->toBe($uploaded);
});

it('loads the first tiles of the home page at once and leaves the rest for scrolling', function () {
    foreach (range(1, 6) as $sort) {
        $root = Category::factory()->create(['show_on_home' => true, 'products_count' => 1, 'sort' => $sort]);
        categoryPhotoProduct(['category_id' => $root->id]);
    }

    $html = $this->get('/')->assertOk()->getContent();
    preg_match_all('/<img src="[^"]*-card\.webp"[^>]*loading="(eager|lazy)"/', $html, $loading);

    expect(array_slice($loading[1], 0, 4))->each->toBe('eager')
        ->and(array_slice($loading[1], 4))->each->toBe('lazy');
});
