<?php

use App\Models\Category;
use App\Models\Product;
use App\Support\Money;

beforeEach(function () {
    $this->category = Category::factory()->create(['is_active' => true]);
});

it('finds products by name and shows the found count', function () {
    Product::factory()->create(['name' => 'Пароконвектомат Abat ПКА 6-1/1', 'category_id' => $this->category->id]);
    Product::factory()->create(['name' => 'Стол производственный', 'category_id' => $this->category->id]);

    $this->get('/search?q=пароконвектомат')
        ->assertOk()
        ->assertSee('Пароконвектомат Abat ПКА 6-1/1')
        ->assertDontSee('Стол производственный');
});

it('tells the customer when the query only worked in the other layout', function () {
    Product::factory()->create(['name' => 'Пароконвектомат Abat', 'category_id' => $this->category->id]);

    $this->get('/search?q=gfhjrjydtrnjvfn')
        ->assertOk()
        ->assertSee('Пароконвектомат Abat')
        ->assertSee('пароконвектомат');
});

it('explains an empty result instead of leaving a blank page', function () {
    Product::factory()->create(['name' => 'Стол производственный', 'category_id' => $this->category->id]);

    $this->get('/search?q=холодильник')
        ->assertOk()
        ->assertSee('ничего не нашлось')
        ->assertSee('Проверьте раскладку');
});

it('asks for at least two characters', function () {
    $this->get('/search?q=а')
        ->assertOk()
        ->assertSee('Введите хотя бы два символа');
});

it('filters the search results by price', function () {
    Product::factory()->create(['name' => 'Плита дешёвая', 'category_id' => $this->category->id, 'retail_price' => Money::ofRubles(10_000)]);
    Product::factory()->create(['name' => 'Плита дорогая', 'category_id' => $this->category->id, 'retail_price' => Money::ofRubles(90_000)]);

    $this->get('/search?q=плита&price_from=50000')
        ->assertOk()
        ->assertSee('Плита дорогая')
        ->assertDontSee('Плита дешёвая');
});

it('never shows a discontinued product in the results', function () {
    Product::factory()->discontinued()->create(['name' => 'Плита снятая', 'category_id' => $this->category->id]);

    $this->get('/search?q=плита')
        ->assertOk()
        ->assertDontSee('Плита снятая');
});
