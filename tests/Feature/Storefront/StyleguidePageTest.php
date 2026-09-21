<?php

use App\Enums\Availability;
use Illuminate\Support\Facades\DB;

it('hides the styleguide outside local development', function () {
    $this->get('/styleguide')->assertNotFound();
});

it('shows every component state side by side in local development', function () {
    app()->detectEnvironment(fn () => 'local');

    $response = $this->get('/styleguide')->assertOk();

    foreach (['Палитра', 'Типографика', 'Кнопки', 'Поля, счётчик, тумблер', 'Статусы наличия', 'Цены', 'Карточки листинга', 'Хлебные крошки'] as $section) {
        $response->assertSee($section);
    }

    foreach (Availability::cases() as $availability) {
        $response->assertSee($availability->getLabel());
    }

    $response
        ->assertSee('Ожидается 28 сентября')
        ->assertSee('Цена по запросу')
        ->assertSee('Ваша цена')
        ->assertSee("383\u{00A0}995\u{00A0}₽", false)
        ->assertSee("48\u{00A0}605,80\u{00A0}₽", false)
        ->assertSee('Недоступна')
        ->assertSee('aria-disabled="true"', false)
        ->assertSee('role="switch"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('Телефон не распознан. Формат: +7 978 123-45-67')
        ->assertSee('Пароконвектомат ПКА 10-1/1ВП2-01')
        ->assertSee('Стол производственный СП-2/1200, нерж. AISI 430')
        ->assertSee('Пароконвектоматы');
});

it('builds the samples without reading the catalog', function () {
    app()->detectEnvironment(fn () => 'local');

    DB::enableQueryLog();
    $this->get('/styleguide')->assertOk();

    $tables = collect(DB::getQueryLog())
        ->map(fn (array $query) => preg_match('/from [`"]?(\w+)/i', $query['query'], $match) ? $match[1] : null)
        ->filter()
        ->unique();

    expect($tables->intersect(['products', 'categories', 'brands', 'media'])->all())->toBe([]);
});
