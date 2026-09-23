<?php

use App\Enums\Availability;
use App\Enums\UserRole;
use App\Filament\Resources\ProductCollections\Pages\CreateProductCollection;
use App\Filament\Resources\ProductCollections\Pages\EditProductCollection;
use App\Filament\Resources\ProductCollections\RelationManagers\CollectionProductsRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use Database\Seeders\ProductionSeeder;
use Filament\Actions\AttachAction;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->section = Category::factory()->create(['is_active' => true]);
});

/**
 * @param  list<Product>  $products
 */
function collectionWith(array $attributes, array $products = []): ProductCollection
{
    $collection = ProductCollection::factory()->create($attributes + ['is_active' => true]);

    foreach ($products as $index => $product) {
        $collection->products()->attach($product->id, ['sort' => ($index + 1) * 10]);
    }

    return $collection;
}

it('offers up to three switched-on collections with products on the home page', function () {
    $product = Product::factory()->create(['category_id' => $this->section->id]);
    $hidden = Product::factory()->create(['category_id' => $this->section->id, 'is_visible' => false]);

    collectionWith(['name' => 'Кафе до 50 посадок', 'slug' => 'kafe', 'sort' => 10, 'description' => 'Базовый набор для кухни.'], [$product]);
    collectionWith(['name' => 'Бар', 'slug' => 'bar', 'sort' => 20], [$product]);
    collectionWith(['name' => 'Пиццерия', 'slug' => 'pizza', 'sort' => 30], [$product]);
    collectionWith(['name' => 'Столовая', 'slug' => 'stolovaya', 'sort' => 40], [$product]);
    collectionWith(['name' => 'Выключенная', 'slug' => 'off', 'sort' => 1, 'is_active' => false], [$product]);
    collectionWith(['name' => 'Без товаров витрины', 'slug' => 'empty', 'sort' => 2], [$hidden]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Соберём кухню под задачу')
        ->assertSeeInOrder(['Кафе до 50 посадок', 'Базовый набор для кухни.', '1 модель', 'Бар', 'Пиццерия'])
        ->assertSee('href="'.route('collection', 'kafe').'"', false)
        ->assertDontSee('Столовая')
        ->assertDontSee('Выключенная')
        ->assertDontSee('Без товаров витрины');
});

it('shows the storefront products of a collection in the manager\'s order', function () {
    $second = Product::factory()->create(['category_id' => $this->section->id, 'name' => 'Шкаф холодильный']);
    $first = Product::factory()->create(['category_id' => $this->section->id, 'name' => 'Пароконвектомат']);
    $gone = Product::factory()->create(['category_id' => $this->section->id, 'name' => 'Плита снятая', 'availability' => Availability::Discontinued]);

    collectionWith(['name' => 'Бар', 'slug' => 'bar', 'description' => 'Для барной стойки.'], [$first, $second, $gone]);
    collectionWith(['name' => 'Черновик', 'slug' => 'draft', 'is_active' => false], [$first]);

    $this->get(route('collection', 'bar'))
        ->assertOk()
        ->assertSee('<title>Бар | ', false)
        ->assertSee('Для барной стойки.')
        ->assertSeeInOrder(['Пароконвектомат', 'Шкаф холодильный'])
        ->assertDontSee('Плита снятая')
        ->assertSee('2 модели');

    $this->get(route('collection', 'draft'))->assertNotFound();
});

it('lets a manager put a collection together and only an administrator delete it', function () {
    $this->actingAs(staffUser());
    $product = Product::factory()->create(['category_id' => $this->section->id]);

    Livewire::test(CreateProductCollection::class)
        ->fillForm(['name' => 'Бар', 'slug' => 'bar', 'icon' => 'refrigeration', 'sort' => 10, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $collection = ProductCollection::query()->where('slug', 'bar')->sole();

    Livewire::test(CollectionProductsRelationManager::class, ['ownerRecord' => $collection, 'pageClass' => EditProductCollection::class])
        ->callTableAction(AttachAction::class, data: ['recordId' => [$product->id]])
        ->assertHasNoTableActionErrors();

    expect($collection->products()->pluck('products.id')->all())->toBe([$product->id]);

    Livewire::test(EditProductCollection::class, ['record' => $collection->getRouteKey()])->assertActionHidden(DeleteAction::class);

    $this->actingAs(staffUser(UserRole::Admin));
    Livewire::test(EditProductCollection::class, ['record' => $collection->getRouteKey()])->assertActionVisible(DeleteAction::class);
});

it('seeds the starting collections switched off', function () {
    $this->seed(ProductionSeeder::class);

    expect(ProductCollection::query()->orderBy('sort')->pluck('name')->all())->toBe(['Кафе до 50 посадок', 'Бар', 'Кондитерский цех'])
        ->and(ProductCollection::query()->where('is_active', true)->count())->toBe(0);
});
