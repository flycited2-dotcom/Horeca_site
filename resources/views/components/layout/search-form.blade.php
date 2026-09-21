@props(['id', 'variant' => 'header'])

{{--
    Поиск (ТЗ §8.4, макет — экран 5): обычная GET-форма на /search, работает без скриптов.
    В шапке поле и кнопка слиты в одну группу, на телефоне кнопка — квадрат с лупой;
    в футере поле и кнопка раздельно. Кегль поля 16 px: телефон не увеличивает страницу
    при фокусе.
--}}
@php
    $header = $variant === 'header';
    $value = $header && request()->routeIs('search') ? (string) request()->query('q', '') : '';
@endphp

<form action="{{ route('search') }}" method="get" role="search" {{ $attributes->class(['flex gap-2', 'md:gap-0' => $header]) }}>
    <label for="{{ $id }}" class="sr-only">{{ __('shop.layout.search') }}</label>
    <input
        id="{{ $id }}"
        type="search"
        name="q"
        value="{{ $value }}"
        placeholder="{{ $header ? __('shop.layout.search_placeholder') : __('shop.layout.footer.sku_placeholder') }}"
        @class([
            'h-control min-w-0 flex-1 rounded-control border bg-surface px-3 text-lg leading-none text-ink placeholder:text-steel-500',
            'transition-colors duration-150 ease-out focus:border-accent',
            'border-line md:rounded-r-none' => $header,
            'border-steel-500' => ! $header,
        ])
    >
    <button
        type="submit"
        @class([
            'inline-flex h-control shrink-0 items-center justify-center rounded-control bg-accent-ink text-base leading-none font-medium text-white',
            'transition-colors duration-150 ease-out hover:bg-accent-dark',
            'w-control md:w-auto md:rounded-l-none md:px-5.5' => $header,
            'px-4' => ! $header,
        ])
    >
        @if ($header)
            <svg class="size-5.5 md:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                <path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14M16 16l4 4"/>
            </svg>
            <span class="sr-only md:not-sr-only">{{ __('shop.layout.search') }}</span>
        @else
            {{ __('shop.layout.search') }}
        @endif
    </button>
</form>
