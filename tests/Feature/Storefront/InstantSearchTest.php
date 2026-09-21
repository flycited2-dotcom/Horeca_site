<?php

use App\Livewire\InstantSearch;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Шкафы холодильные', 'products_count' => 8]);
});

it('stays quiet until the customer types', function () {
    Product::factory()->create(['name' => 'Шкаф холодильный Abat', 'category_id' => $this->category->id]);

    Livewire::test(InstantSearch::class)
        ->assertDontSee('Шкаф холодильный Abat')
        ->set('query', 'ш')
        ->assertDontSee('Шкаф холодильный Abat');
});

it('shows products with price and stock, sections and the way to all results', function () {
    Product::factory()->inStock()->create(['name' => 'Шкаф холодильный Abat', 'category_id' => $this->category->id, 'retail_price' => Money::ofRubles(96_750)]);
    Product::factory()->priceOnRequest()->create(['name' => 'Шкаф морозильный Polair', 'category_id' => $this->category->id]);

    Livewire::test(InstantSearch::class)
        ->set('query', 'шкаф')
        ->assertSee('Товары')
        ->assertSee('Шкаф холодильный Abat')
        ->assertSee("96\u{00A0}750\u{00A0}₽", false)
        ->assertSee('В наличии')
        ->assertSee('Цена по запросу')
        ->assertSee('Категории')
        ->assertSee('Шкафы холодильные')
        ->assertSee('Показать все 2 результата по «шкаф»')
        ->assertSeeHtml('href="'.e(route('search', ['q' => 'шкаф'])).'"');
});

it('shows six products and counts the rest', function () {
    Product::factory()->count(8)->sequence(fn ($sequence) => ['name' => "Шкаф холодильный №{$sequence->index}"])
        ->create(['category_id' => $this->category->id]);

    Livewire::test(InstantSearch::class)
        ->set('query', 'шкаф')
        ->assertViewHas('result', fn ($result) => $result->products->count() === 6 && $result->total === 8)
        ->assertSee('Показать все 8 результатов по «шкаф»');
});

it('says so when nothing is found', function () {
    Livewire::test(InstantSearch::class)
        ->set('query', 'пароконвектомат')
        ->assertSee('По запросу «пароконвектомат» ничего не нашлось');
});

it('keeps the query of the search page in the header field', function () {
    Product::factory()->create(['name' => 'Шкаф холодильный Abat', 'category_id' => $this->category->id]);

    $this->get('/search?q=шкаф')
        ->assertOk()
        ->assertSeeLivewire(InstantSearch::class)
        ->assertSee('value="шкаф"', false);
});

it('offers the «Знаю артикул» field on the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Знаю артикул')
        ->assertSee('id="sku-search"', false);
});
