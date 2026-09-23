<?php

use App\Console\Commands\DispatchDueImportsCommand;
use App\Console\Commands\SendImportDigestCommand;
use App\Models\Cart;
use App\Models\Favorite;
use Illuminate\Support\Facades\Schedule;

/*
 * Расписание (ТЗ §6.4, §13). Время московское: APP_TIMEZONE=Europe/Moscow.
 *
 * Профили импорта запускаются по своему cron-выражению из import_profiles.schedule,
 * поэтому планировщик каждую минуту спрашивает, кому пора.
 */

Schedule::command(DispatchDueImportsCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(SendImportDigestCommand::class)
    ->dailyAt('20:00');

// Гостевые корзины живут 30 дней после последнего изменения (ТЗ §10.1), гостевое
// избранное — пока жива сессия гостя (§5).
Schedule::command('model:prune', ['--model' => [Cart::class, Favorite::class]])
    ->dailyAt('03:30');
