<?php

use App\Console\Commands\DispatchDueImportsCommand;
use App\Console\Commands\GenerateSitemapCommand;
use App\Console\Commands\RecalculatePopularityCommand;
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

// Популярность — вторая ступень сортировки после наличия (ТЗ §8.2, §17.5).
Schedule::command(RecalculatePopularityCommand::class)
    ->dailyAt('03:00');

// Карта сайта для поисковиков (ТЗ §14).
Schedule::command(GenerateSitemapCommand::class)
    ->dailyAt('04:00');

// Гостевые корзины живут 30 дней после последнего изменения (ТЗ §10.1), гостевое
// избранное — пока жива сессия гостя (§5).
Schedule::command('model:prune', ['--model' => [Cart::class, Favorite::class]])
    ->dailyAt('03:30');
