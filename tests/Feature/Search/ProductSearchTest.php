<?php

use App\Enums\Availability;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Search\ProductSearch;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Плиты индукционные', 'is_active' => true]);
    $this->brand = Brand::factory()->create(['name' => 'Abat']);

    $this->make = fn (array $attributes): Product => Product::factory()->create([
        'category_id' => $this->category->id,
        'brand_id' => $this->brand->id,
        ...$attributes,
    ]);
});

it('puts an exact code first, then a name that starts with the query, then the rest', function () {
    $mentions = ($this->make)(['name' => 'Подставка под плиту КИП-27Н', 'model' => 'ПП-1', 'sku' => '100']);
    $startsWith = ($this->make)(['name' => 'Плита индукционная КИП-27', 'model' => 'КИП-27', 'sku' => '200']);
    $exact = ($this->make)(['name' => 'Варочная панель', 'model' => 'КИП-27Н-3,5', 'sku' => '300']);

    $found = app(ProductSearch::class)->instant('КИП-27Н-3,5', null)->products;

    expect($found->first()?->id)->toBe($exact->id);

    // No word forms: "плит" finds both "плита" and "плиту", the name that starts with it first.
    $found = app(ProductSearch::class)->instant('плит', null)->products;

    expect($found->pluck('id')->all())->toBe([$startsWith->id, $mentions->id]);
});

it('ranks a product with every word above one with a single word', function () {
    $one = ($this->make)(['name' => 'Шкаф холодильный', 'model' => 'ШХ-1']);
    $both = ($this->make)(['name' => 'Шкаф морозильный Abat', 'model' => 'ШМ-1']);

    $found = app(ProductSearch::class)->instant('морозильный шкаф', null)->products;

    expect($found->pluck('id')->all())->toBe([$both->id, $one->id]);
});

it('finds a product by the article without separators', function () {
    $product = ($this->make)(['name' => 'Мойка', 'sku' => '12-000-137117', 'model' => null]);

    expect(app(ProductSearch::class)->instant('12000137117', null)->products->pluck('id')->all())->toBe([$product->id]);
});

it('finds a product typed in the wrong keyboard layout', function () {
    $product = ($this->make)(['name' => 'Пароконвектомат Abat ПКА 6-1/1', 'model' => 'ПКА 6-1/1']);

    $result = app(ProductSearch::class)->instant('gfhjrjydtrnjvfn', null);

    expect($result->products->pluck('id')->all())->toBe([$product->id])
        ->and($result->layoutSwitched)->toBeTrue()
        ->and($result->query)->toBe('пароконвектомат');
});

it('finds ё and е alike', function () {
    $product = ($this->make)(['name' => 'Ёмкость для заготовок']);

    expect(app(ProductSearch::class)->instant('емкость', null)->products->pluck('id')->all())->toBe([$product->id]);
});

it('never shows discontinued, hidden products or products of a switched-off category', function () {
    ($this->make)(['name' => 'Плита снятая', 'availability' => Availability::Discontinued]);
    ($this->make)(['name' => 'Плита скрытая', 'is_visible' => false]);
    Product::factory()->create([
        'name' => 'Плита в выключенной категории',
        'category_id' => Category::factory()->create(['is_active' => false])->id,
    ]);
    $shown = ($this->make)(['name' => 'Плита на витрине']);

    expect(app(ProductSearch::class)->instant('плита', null)->products->pluck('id')->all())->toBe([$shown->id]);
});

it('treats LIKE wildcards in the query as plain characters', function () {
    ($this->make)(['name' => 'Стол производственный']);

    expect(app(ProductSearch::class)->instant('%_', null)->products)->toBeEmpty();
});

it('offers matching categories with the instant results', function () {
    ($this->make)(['name' => 'Плита индукционная']);
    Category::factory()->create(['name' => 'Плиты газовые', 'is_active' => false]);

    $categories = app(ProductSearch::class)->instant('плиты', null)->categories;

    expect($categories->pluck('id')->all())->toBe([$this->category->id]);
});

it('does not search for a single character', function () {
    ($this->make)(['name' => 'Ящик']);

    expect(app(ProductSearch::class)->instant('я', null)->isEmpty())->toBeTrue();
});

it('gives the search page its products, in the other layout when needed', function () {
    ($this->make)(['name' => 'Плита индукционная']);
    ($this->make)(['name' => 'Пароконвектомат']);

    $search = app(ProductSearch::class);
    $plain = $search->scope('плита');
    $switched = $search->scope('gkbnf');

    expect($plain->products()->count())->toBe(1)
        ->and($plain->layoutSwitched)->toBeFalse()
        ->and($switched->products()->count())->toBe(1)
        ->and($switched->layoutSwitched)->toBeTrue()
        ->and($switched->query->text)->toBe('плита')
        ->and($search->scope('п'))->toBeNull();
});

it('finds the one product with exactly the article', function () {
    $wanted = ($this->make)(['name' => 'Плита ЭП-4ЖШ', 'sku' => '11000019106', 'model' => 'ЭП-4ЖШ']);
    ($this->make)(['name' => 'Плита ЭП-4ЖШ-01', 'sku' => '11000019107', 'model' => 'ЭП-4ЖШ-01']);

    $search = app(ProductSearch::class);

    expect($search->exact($search->scope('11000019106'), null)?->id)->toBe($wanted->id)
        ->and($search->exact($search->scope('плита'), null))->toBeNull();
});
