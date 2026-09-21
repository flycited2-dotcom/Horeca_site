@props(['code', 'key' => null])

{{--
    Страница ошибки без каркаса витрины (ТЗ §14): 500, 503, 419, 429, 403. Не обращается
    к базе и не запускает Livewire — она должна открыться и тогда, когда база или кэш
    недоступны. Название магазина — из конфигурации. $key — группа текстов, если у кода
    нет своей: 4xx и 5xx. По экрану 11 макета: без кода ошибки на экране и всегда со вторым
    путём — каталогом (кроме обслуживания сайта, когда закрыт и он).
--}}
@php($key ??= (string) $code)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('shop.errors.'.$key.'.title') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="border-b border-line bg-surface">
        <div class="container-page flex h-17 items-center">
            <a href="{{ url('/') }}" class="text-title font-bold">{{ config('app.name') }}</a>
        </div>
    </header>

    <main class="container-page flex-1 py-8 md:py-14">
        <section class="flex max-w-2xl flex-col items-start gap-4 rounded-card border border-line bg-surface p-4 md:p-8">
            <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.errors.'.$key.'.title') }}</h1>
            <p class="max-w-prose text-base text-steel-500">{{ __('shop.errors.'.$key.'.text') }}</p>
            <div class="flex flex-wrap gap-3">
                <x-ui.button :href="url('/')">{{ __('shop.errors.home') }}</x-ui.button>
                @unless ((int) $code === 503)
                    <x-ui.button variant="neutral" :href="url('/catalog')">{{ __('shop.errors.catalog') }}</x-ui.button>
                @endunless
            </div>
        </section>
    </main>
</body>
</html>
