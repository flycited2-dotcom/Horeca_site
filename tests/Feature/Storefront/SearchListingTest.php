<?php

use App\Livewire\SearchListing;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function () {
    $this->ovens = Category::factory()->create(['name' => 'Пароконвектоматы', 'slug' => 'parokonvektomaty', 'products_count' => 3]);
    $this->parts = Category::factory()->create(['name' => 'Комплектующие к ПКА', 'slug' => 'komplektuyushchie', 'products_count' => 1]);
    $this->abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
});

it('puts the exact article in its own card and the rest below', function () {
    $wanted = Product::factory()->inStock()->create(['name' => 'Пароконвектомат ПКА 10-1/1ВП2-01', 'sku' => '11000019106', 'model' => 'ПКА 10-1/1ВП2-01', 'category_id' => $this->ovens->id]);
    Product::factory()->create(['name' => 'Подставка под 11000019106', 'sku' => '11000044170', 'category_id' => $this->parts->id]);

    Livewire::test(SearchListing::class, ['query' => '11000019106'])
        ->assertViewHas('exact', fn ($exact) => $exact?->id === $wanted->id)
        ->assertViewHas('slice', fn ($slice) => $slice->total === 1 && ! $slice->products->contains('id', $wanted->id))
        ->assertSee('Точное совпадение по артикулу')
        ->assertSee('Открыть карточку')
        ->assertSee('Похожие и связанные — 1 позиция, без точного совпадения');
});

it('shows only the card when the article is all that was found', function () {
    Product::factory()->create(['name' => 'Пароконвектомат ПКА 10-1/1ВП2-01', 'sku' => '11000019106', 'category_id' => $this->ovens->id]);

    Livewire::test(SearchListing::class, ['query' => '11000019106'])
        ->assertSee('Точное совпадение по артикулу')
        ->assertDontSee('Сузить поиск')
        ->assertDontSee('Под эти условия нет ни одной позиции');
});

it('counts the results and offers the sections to narrow down to', function () {
    Product::factory()->count(2)->inStock()->create(['name' => 'Пароконвектомат Abat', 'category_id' => $this->ovens->id]);
    Product::factory()->create(['name' => 'Пароконвектомата фильтр', 'category_id' => $this->parts->id]);

    Livewire::test(SearchListing::class, ['query' => 'пароконвектомат'])
        ->assertSee('Поиск: «пароконвектомат»')
        ->assertSee('3 результата · 2 в наличии · 2 раздела')
        ->assertSee('Уточнить:')
        ->assertSee('Пароконвектоматы · 2')
        ->assertSee('Комплектующие к ПКА · 1')
        ->assertSee('Только в наличии · 2');
});

it('narrows the results to a section and drops it again from its chip', function () {
    Product::factory()->create(['name' => 'Пароконвектомат Abat', 'category_id' => $this->ovens->id]);
    Product::factory()->create(['name' => 'Пароконвектомата фильтр', 'category_id' => $this->parts->id]);

    Livewire::test(SearchListing::class, ['query' => 'пароконвектомат'])
        ->set('category', 'komplektuyushchie')
        ->assertSet('page', 1)
        ->assertViewHas('slice', fn ($slice) => $slice->products->pluck('name')->all() === ['Пароконвектомата фильтр'])
        ->assertSee('Выбрано:')
        ->call('removeFilter', 'category')
        ->assertSet('category', '')
        ->assertViewHas('slice', fn ($slice) => $slice->total === 2);
});

it('filters the results like the listing and orders them by price on request', function () {
    Product::factory()->create(['name' => 'Плита дешёвая', 'category_id' => $this->ovens->id, 'brand_id' => $this->abat->id, 'retail_price' => Money::ofRubles(10_000)]);
    Product::factory()->create(['name' => 'Плита дорогая', 'category_id' => $this->ovens->id, 'retail_price' => Money::ofRubles(90_000)]);

    Livewire::test(SearchListing::class, ['query' => 'плита'])
        ->assertSee('По релевантности')
        ->call('sortBy', 'price_desc')
        ->assertViewHas('slice', fn ($slice) => $slice->products->pluck('name')->all() === ['Плита дорогая', 'Плита дешёвая'])
        ->set('brands', ['abat'])
        ->assertViewHas('slice', fn ($slice) => $slice->products->pluck('name')->all() === ['Плита дешёвая'])
        ->assertViewHas('brandOptions', fn ($brands) => $brands->firstWhere('slug', 'abat')?->products_count === 1 && $brands->count() === 2);
});

it('suggests dropping the section when the filters leave nothing in it', function () {
    Product::factory()->inStock()->create(['name' => 'Пароконвектомат Abat', 'category_id' => $this->ovens->id]);
    Product::factory()->create(['name' => 'Пароконвектомата фильтр', 'category_id' => $this->parts->id]);

    Livewire::withQueryParams(['category' => 'komplektuyushchie', 'in_stock' => '1'])
        ->test(SearchListing::class, ['query' => 'пароконвектомат'])
        ->assertSee('Под эти условия нет ни одной позиции')
        ->assertSee('Снять «Комплектующие к ПКА» — 1 позиция')
        ->assertSee('Снять «Только в наличии» — 1 позиция');
});

it('points to the biggest sections when nothing is found', function () {
    Category::factory()->create(['name' => 'Холодильное оборудование', 'products_count' => 4009]);

    Livewire::test(SearchListing::class, ['query' => 'гриль'])
        ->assertSee('По запросу «гриль» ничего не нашлось')
        ->assertSee("Холодильное оборудование · 4\u{00A0}009", false)
        ->assertSee('Все категории');
});

it('keeps the query in the filter form that works without scripts', function () {
    Product::factory()->create(['name' => 'Плита Abat', 'category_id' => $this->ovens->id, 'brand_id' => $this->abat->id]);

    $this->get('/search?q=плита&category=parokonvektomaty')
        ->assertOk()
        ->assertSee('<input type="hidden" name="q" value="плита">', false)
        ->assertSee('<input type="hidden" name="category" value="parokonvektomaty">', false)
        ->assertSee('name="brand[]"', false)
        ->assertSee('<title>Поиск: «плита» |', false);
});
