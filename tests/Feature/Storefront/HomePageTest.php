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

    $response = $this->get('/')->assertOk()->assertDontSee('Скрытая категория');

    // The layout lists every switched-on root section; the home panel only the marked ones.
    preg_match('/<main.*?<\/main>/s', $response->getContent(), $main);

    expect($main[0])
        ->toContain('Холодильное оборудование', "7\u{00A0}021 товар")
        ->not->toContain('Не для главной')
        ->not->toContain('Холодильный шкаф');
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
