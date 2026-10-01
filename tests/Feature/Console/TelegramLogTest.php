<?php

use App\Logging\TelegramLogHandler;
use App\Services\Notifications\TelegramNotifier;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Psr\Log\LogLevel;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    Cache::flush();
    config(['app.name' => 'Гастроснаб', 'app.env' => 'production', 'logging.channels.alarm' => [
        'driver' => 'stack', 'channels' => ['telegram'], 'ignore_exceptions' => false,
    ]]);
});

function telegramConfigured(): void
{
    config(['services.telegram.token' => '123:abc', 'services.telegram.chat_id' => '-100500']);
}

it('sends a critical record to the managers group at once, without the queue', function () {
    telegramConfigured();

    Log::channel('alarm')->critical('Резервная копия базы не сделана: диск полон');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bot123:abc/sendMessage')
        && $request['chat_id'] === '-100500'
        && str_contains($request['text'], 'Ошибка на сайте «Гастроснаб» (production)')
        && str_contains($request['text'], 'диск полон'));
});

it('never puts the token of the bot into a message to the group', function () {
    telegramConfigured();

    Log::channel('alarm')->critical('Сбой настройки: токен 123:abc и адрес bot9999999999:AAHV66lQlTOnsH8lp7sZ_55et3gqBJxioJ8');

    Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Сбой настройки')
        && ! str_contains($request['text'], '123:abc')
        && ! str_contains($request['text'], 'AAHV66lQlTOnsH8lp7sZ')
        && str_contains($request['text'], '<токен>'));
});

it('does not report a failed delivery to Telegram through Telegram', function () {
    telegramConfigured();

    Log::channel('alarm')->critical('cURL error 28: Connection timed out for https://api.telegram.org/bot<токен>/sendMessage');

    Http::assertNothingSent();
});

it('throws a failed delivery without the token in its text and without the original exception', function () {
    telegramConfigured();
    Http::fake(['api.telegram.org/*' => fn () => throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot123:abc/sendMessage')]);

    try {
        app(TelegramNotifier::class)->deliver('Привет');
        $this->fail('The delivery should have thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('cURL error 28')->not->toContain('123:abc')
            ->and($exception->getPrevious())->toBeNull();
    }
});

it('ignores anything below critical', function () {
    telegramConfigured();

    Log::channel('alarm')->error('обычная ошибка');
    Log::channel('alarm')->warning('предупреждение');

    Http::assertNothingSent();
});

it('says nothing while the bot is not set up', function () {
    Log::channel('alarm')->critical('поломка');

    Http::assertNothingSent();
});

it('does not repeat the same alarm within ten minutes', function () {
    telegramConfigured();

    Log::channel('alarm')->critical('одна и та же поломка');
    Log::channel('alarm')->critical('одна и та же поломка');
    Log::channel('alarm')->critical('другая поломка');

    Http::assertSentCount(2);
});

it('never breaks the site when Telegram is down', function () {
    telegramConfigured();
    Http::fake(['api.telegram.org/*' => Http::response('down', 502)]);

    Log::channel('alarm')->critical('поломка при недоступном Telegram');

    expect(true)->toBeTrue();
});

it('sends an unhandled error of the site with its class and place', function () {
    telegramConfigured();
    config(['logging.default' => 'alarm']);
    Route::get('/boom', fn () => throw new RuntimeException('база недоступна'));

    $this->get('/boom')->assertServerError();

    Http::assertSent(fn ($request): bool => str_contains($request['text'], 'база недоступна')
        && str_contains($request['text'], 'RuntimeException')
        && str_contains($request['text'], 'TelegramLogTest.php'));
});

it('does not wake the group for a missing page', function () {
    telegramConfigured();
    config(['logging.default' => 'alarm']);

    $this->get('/no-such-page-anywhere')->assertNotFound();

    Http::assertNothingSent();
});

it('raises the level of an unhandled exception to critical', function () {
    $handler = app(ExceptionHandler::class);
    $levels = (new ReflectionProperty($handler, 'levels'))->getValue($handler);

    expect($levels[Throwable::class] ?? null)->toBe(LogLevel::CRITICAL);
});

it('has a telegram channel in the logging config', function () {
    expect(config('logging.channels.telegram.handler'))->toBe(TelegramLogHandler::class)
        ->and(config('logging.channels.telegram.level'))->toBe('critical');
});
