@props(['code', 'key' => null])

{{--
    Страница ошибки без каркаса витрины (ТЗ §14): 500, 503, 419, 429, 403. Не обращается
    к базе и не запускает Livewire — она должна открыться и тогда, когда база или кэш
    недоступны. Название магазина — из конфигурации. $key — группа текстов, если у кода
    нет своей: 4xx и 5xx. По экрану 11 макета: без кода ошибки на экране и всегда со вторым
    путём — каталогом (кроме обслуживания сайта, когда закрыт и он). Облик «Свечение»: та же
    живая сцена и шапка-таблетка со знаком магазина, что и у витрины, только без поиска и корзины.
--}}
@php($key ??= (string) $code)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07090C">
    <meta name="robots" content="noindex">
    <title>{{ __('shop.errors.'.$key.'.title') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col">
    <div class="gl-stage flex flex-1 flex-col">
        <x-layout.scene />

        <header class="gl-hdr">
            <div class="gl-wrap container-page">
                <div class="gl-pill">
                    <x-layout.logo :name="config('app.name')" :href="url('/')" />
                </div>
            </div>
        </header>

        <main class="container-page flex-1 py-8 md:py-14">
            <section class="gl-card gl-card--gl-duo flex max-w-2xl flex-col items-start gap-4 p-5 md:p-8">
                <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.errors.'.$key.'.title') }}</h1>
                <p class="max-w-prose text-base text-steel-500">{{ __('shop.errors.'.$key.'.text') }}</p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ url('/') }}" class="gl-btn gl-btn--gl-hot">{{ __('shop.errors.home') }}</a>
                    @unless ((int) $code === 503)
                        <a href="{{ url('/catalog') }}" class="gl-btn gl-btn--gl-glass">{{ __('shop.errors.catalog') }}</a>
                    @endunless
                </div>
            </section>
        </main>
    </div>
</body>
</html>
