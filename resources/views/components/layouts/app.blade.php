@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | '.config('app.name') : config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen flex-col bg-steel-50 text-graphite">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded-control focus:bg-surface focus:px-4 focus:py-2">
        {{ __('shop.layout.skip_to_content') }}
    </a>

    <header class="bg-graphite text-white">
        <div class="mx-auto flex max-w-page items-center px-3 py-4 md:px-4 lg:px-6">
            <a href="{{ route('home') }}" class="font-heading text-xl font-semibold">{{ config('app.name') }}</a>
        </div>
    </header>

    <main id="content" class="mx-auto w-full max-w-page flex-1 px-3 py-8 md:px-4 lg:px-6">
        {{ $slot }}
    </main>

    <footer class="border-t border-steel-200 bg-surface">
        <div class="mx-auto max-w-page px-3 py-6 text-sm text-steel-600 md:px-4 lg:px-6">
            © {{ now()->year }} {{ config('app.name') }}
        </div>
    </footer>
</body>
</html>
