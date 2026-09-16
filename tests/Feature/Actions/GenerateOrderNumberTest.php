<?php

use App\Actions\Orders\GenerateOrderNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('numbers orders per day starting from 0001', function () {
    $action = app(GenerateOrderNumber::class);
    $morning = CarbonImmutable::parse('2026-09-16 10:00', 'Europe/Moscow');

    expect($action->handle($morning))->toBe('HR-260916-0001')
        ->and($action->handle($morning->addHour()))->toBe('HR-260916-0002')
        ->and($action->handle($morning->addDay()))->toBe('HR-260917-0001');
});

it('counts days by the Moscow date', function () {
    $lateEveningUtc = CarbonImmutable::parse('2026-09-16 22:30', 'UTC');

    expect(app(GenerateOrderNumber::class)->handle($lateEveningUtc))->toBe('HR-260917-0001');
});

it('uses the current moment when no time is given', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-08 12:00', 'Europe/Moscow'));

    expect(app(GenerateOrderNumber::class)->handle())->toBe('HR-260308-0001');
});

it('stops after 9999 orders a day', function () {
    DB::table('order_counters')->insert(['date' => '2026-09-16', 'last_number' => 9999]);

    app(GenerateOrderNumber::class)->handle(CarbonImmutable::parse('2026-09-16 12:00', 'Europe/Moscow'));
})->throws(RuntimeException::class);
