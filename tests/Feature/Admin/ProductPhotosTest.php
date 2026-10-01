<?php

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'media-library.queue_conversions_by_default' => false]);

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Manager,
        'app_authentication_secret' => app(AppAuthentication::class)->generateSecret(),
    ]));
});

it('uploads a manual photo from the product card', function () {
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['images' => [UploadedFile::fake()->image('shkaf.jpg', 1200, 900)]])
        ->call('save')
        ->assertHasNoFormErrors();

    $media = $product->refresh()->getMedia(Product::IMAGES);

    expect($media)->toHaveCount(1)
        ->and($media->first()->getCustomProperty('source'))->toBe('manual');
});

it('refuses a file that is not a picture', function () {
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['images' => [UploadedFile::fake()->create('price.pdf', 100, 'application/pdf')]])
        ->call('save')
        ->assertHasFormErrors(['images']);

    expect($product->refresh()->getMedia(Product::IMAGES))->toHaveCount(0);
});

it('shows the uploaded photo on the storefront instead of the placeholder', function () {
    $product = Product::factory()->create([
        'slug' => 'shkaf-s-foto',
        'category_id' => Category::factory()->create(['is_active' => true])->id,
    ]);
    $product->addMedia(UploadedFile::fake()->image('shkaf.jpg', 800, 600))
        ->withCustomProperties(['source' => 'manual'])
        ->toMediaCollection(Product::IMAGES);

    $this->get('/product/shkaf-s-foto')
        ->assertOk()
        ->assertDontSee(__('shop.product.no_photo'))
        ->assertSee('<img', false);
});

it('lets the customer swipe through the photos and open them fitted to the screen', function () {
    $product = Product::factory()->create([
        'slug' => 'shkaf-s-tremya-foto',
        'category_id' => Category::factory()->create(['is_active' => true])->id,
    ]);

    foreach (['a', 'b', 'c'] as $name) {
        $product->addMedia(UploadedFile::fake()->image("{$name}.jpg", 4000, 3000))->toMediaCollection(Product::IMAGES);
    }

    $content = $this->get('/product/shkaf-s-tremya-foto')->assertOk()->getContent();

    // Swipe: a track of three slides with a counter; the viewer shows the same photos in the «full» size,
    // not the original of four thousand pixels, and can be closed.
    expect(substr_count($content, 'data-gallery-open='))->toBe(3)
        ->and($content)->toContain('data-gallery-track', 'snap-x snap-mandatory', 'data-gallery-counter', '1 из 3', 'data-gallery-viewer', 'data-viewer-close')
        ->and(substr_count($content, 'data-gallery-thumb='))->toBe(3);

    foreach ($product->refresh()->getMedia(Product::IMAGES) as $image) {
        expect($content)->toContain($image->getUrl('full'));
    }
});

it('keeps a single photo without a counter and thumbnails', function () {
    $product = Product::factory()->create([
        'slug' => 'shkaf-s-odnim-foto',
        'category_id' => Category::factory()->create(['is_active' => true])->id,
    ]);
    $product->addMedia(UploadedFile::fake()->image('a.jpg', 800, 600))->toMediaCollection(Product::IMAGES);

    $this->get('/product/shkaf-s-odnim-foto')
        ->assertOk()
        ->assertSee('data-gallery-open="0"', false)
        ->assertDontSee('data-gallery-counter', false)
        ->assertDontSee('data-gallery-thumb', false)
        ->assertDontSee('data-viewer-step', false);
});
