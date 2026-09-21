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
