<?php

use App\Models\Category;
use Illuminate\Support\Facades\DB;

it('shows active root categories marked for the home page', function () {
    $refrigeration = Category::factory()->create([
        'name' => 'Холодильное оборудование',
        'show_on_home' => true,
        'products_count' => 7021,
    ]);
    Category::factory()->inactive()->create(['name' => 'Скрытая категория', 'show_on_home' => true]);
    Category::factory()->create(['name' => 'Не для главной', 'show_on_home' => false]);
    Category::factory()->childOf($refrigeration)->create(['name' => 'Холодильный шкаф', 'show_on_home' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Холодильное оборудование')
        ->assertSee("7\u{00A0}021 товар", false)
        ->assertDontSee('Скрытая категория')
        ->assertDontSee('Не для главной')
        ->assertDontSee('Холодильный шкаф');
});

it('explains an empty catalog instead of showing a blank page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Каталог пока пуст');
});

it('renders the home page with a small fixed number of queries', function () {
    Category::factory()->count(8)->create(['show_on_home' => true]);

    DB::enableQueryLog();
    $this->get('/')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(20);
});
