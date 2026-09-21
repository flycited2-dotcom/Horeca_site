@props(['title' => null, 'description' => null])

{{--
    Каркас витрины (макет, экран 5): служебная полоса, липкая шапка с поиском,
    ряд корневых категорий, футер. Контейнер 1320, поля 56 на десктопе и 12 на мобильном.
--}}
@php
    $settings = app(\App\Services\Settings\Settings::class);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | '.config('app.name') : config('app.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen flex-col">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-20 focus:rounded-control focus:bg-surface focus:px-4 focus:py-2">
        {{ __('shop.layout.skip_to_content') }}
    </a>

    <div class="bg-slate text-sm text-steel-500">
        <div class="mx-auto flex h-9 max-w-page items-center gap-4 px-3 md:h-10 md:px-6 xl:px-14">
            @if ($phone = $settings->get('contacts.phones'))
                <a href="tel:{{ preg_replace('/[^+\d]/', '', (string) $phone) }}" class="font-medium text-ink">{{ $phone }}</a>
            @endif

            @if ($schedule = $settings->get('contacts.schedule'))
                <span class="hidden sm:inline">{{ $schedule }}</span>
            @endif
        </div>
    </div>

    <header class="sticky top-0 z-10 border-b border-line bg-surface">
        <div class="mx-auto flex max-w-page flex-col gap-3 px-3 py-3 md:flex-row md:items-center md:gap-6 md:px-6 md:py-4 xl:px-14">
            <a href="{{ route('home') }}" class="text-xl font-bold">{{ config('app.name') }}</a>

            <form action="{{ route('search') }}" method="get" role="search" class="flex flex-1 gap-2">
                <label for="site-search" class="sr-only">{{ __('shop.layout.search') }}</label>
                <input
                    id="site-search"
                    type="search"
                    name="q"
                    value="{{ request()->routeIs('search') ? request()->query('q') : '' }}"
                    placeholder="{{ __('shop.layout.search_placeholder') }}"
                    class="h-control w-full rounded-control border border-line bg-surface px-3 text-base placeholder:text-steel-500"
                >
                <x-ui.button type="submit">{{ __('shop.layout.search') }}</x-ui.button>
            </form>

            <a href="{{ route('catalog') }}" class="h-control shrink-0 content-center text-md font-semibold text-accent-ink">
                {{ __('shop.layout.catalog') }}
            </a>
        </div>
    </header>

    <main id="content" class="mx-auto w-full max-w-page flex-1 px-3 py-6 md:px-6 md:py-8 xl:px-14">
        {{ $slot }}
    </main>

    <footer class="mt-8 bg-slate">
        <div class="mx-auto max-w-page px-3 py-8 text-sm text-steel-500 md:px-6 xl:px-14">
            @if ($requisites = $settings->get('seller.requisites'))
                <p class="max-w-prose">{{ $requisites }}</p>
            @endif

            <p class="mt-4">{{ __('shop.layout.copyright', ['year' => now()->year, 'name' => config('app.name')]) }}</p>
        </div>
    </footer>
</body>
</html>
