<?php

use App\Enums\Availability;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

it('runs in the Moscow timezone with the Russian locale', function () {
    expect(config('app.timezone'))->toBe('Europe/Moscow')
        ->and(date_default_timezone_get())->toBe('Europe/Moscow')
        ->and(app()->getLocale())->toBe('ru');
});

it('uses MariaDB by default', function () {
    // The framework merges its own SQLite connection into the config, so the rule
    // "no SQLite" is guarded by the default connection and the actual driver.
    expect(config('database.default'))->toBe('mariadb')
        ->and(DB::connection()->getDriverName())->toBe('mariadb')
        ->and(config('database.connections.mariadb.collation'))->toBe('utf8mb4_unicode_ci');
});

it('gives the import queues a retry_after longer than the import timeout', function () {
    expect(config('queue.connections.redis-imports.retry_after'))->toBe(3700)
        ->and(config('queue.connections.redis-imports.queue'))->toBe('imports')
        ->and(config('queue.connections.database-imports.retry_after'))->toBe(3700)
        ->and(config('queue.connections.database-imports.queue'))->toBe('imports');
});

it('has a Russian label for every enum case', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case->getLabel())->not->toStartWith('enums.');
    }
})->with(fn (): array => array_map(
    fn (string $file): string => 'App\\Enums\\'.basename($file, '.php'),
    glob(dirname(__DIR__, 3).'/app/Enums/*.php'),
));

it('translates labels and validation messages into Russian', function () {
    expect(Availability::OnOrder->getLabel())->toBe('Под заказ')
        ->and(OrderStatus::Invoiced->getLabel())->toBe('Выставлен счёт')
        ->and(__('validation.required', ['attribute' => __('validation.attributes.phone')]))->toBe('Заполните поле «телефон».')
        ->and(trans_choice('shop.home.products_count', 1, ['count' => 1]))->toBe('1 товар')
        ->and(trans_choice('shop.home.products_count', 3, ['count' => 3]))->toBe('3 товара')
        ->and(trans_choice('shop.home.products_count', 11, ['count' => 11]))->toBe('11 товаров');
});
