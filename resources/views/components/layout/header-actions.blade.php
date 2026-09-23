@props(['shell'])

{{--
    Правая часть шапки (макет, экран 5): плитки «значок над подписью» и корзина — синяя
    кнопка с числом позиций и суммой; на телефоне — значки со счётчиками. «Избранное» и
    «Сравнение» видны, пока в них есть модели. Счётчики после «Сравнить», «В избранное» и «В корзину» обновляет
    скрипт витрины. Гостю — «Войти», вошедшему — плитка с именем и меню кабинета на
    <details>: раскрывается без скриптов, скрипт закрывает его по Esc и нажатию мимо.
--}}
@php
    $tile = 'relative flex h-control items-center justify-center rounded-control transition-colors duration-150 ease-out hover:bg-bg max-md:w-control md:w-16 md:flex-col md:gap-1';
    $badge = 'absolute top-0.5 right-0.5 min-w-4.5 rounded-full px-1 text-center text-[11px] leading-4.5 font-semibold tabular md:top-0 md:right-2';
    $menuItem = 'flex h-control w-full items-center rounded-control px-2.5 text-left text-base transition-colors duration-150 ease-out hover:bg-bg';
@endphp

<div {{ $attributes->class('flex items-center gap-1 md:gap-2') }}>
    @if ($shell->customerName === null)
        <a href="{{ route('login') }}" class="{{ $tile }}">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.5 20a7.5 7.5 0 0 1 15 0"/>
            </svg>
            <span class="text-xs leading-none max-md:sr-only">{{ __('shop.auth.login.submit') }}</span>
        </a>
    @else
        <details data-dismissable class="relative">
            <summary class="{{ $tile }} cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="{{ __('shop.auth.menu', ['name' => $shell->customerName]) }}">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.5 20a7.5 7.5 0 0 1 15 0"/>
                </svg>
                <span class="max-w-15 truncate text-xs leading-none max-md:sr-only" aria-hidden="true">{{ $shell->customerFirstName() }}</span>
            </summary>
            <div class="absolute top-full right-0 z-40 mt-1 flex w-64 flex-col gap-1 rounded-card border border-line bg-surface p-2 shadow-[0_4px_16px_rgba(0,0,0,0.10)]">
                <p class="flex flex-col px-2.5 py-1.5">
                    <span class="truncate text-base font-semibold">{{ $shell->customerName }}</span>
                    <span class="truncate text-sm text-steel-500">{{ $shell->customerEmail }}</span>
                </p>
                <a href="{{ route('account') }}" class="{{ $menuItem }}">{{ __('shop.account.menu') }}</a>
                <a href="{{ route('account.orders') }}" class="{{ $menuItem }}">{{ __('shop.account.menu_orders') }}</a>
                <a href="{{ route('favorites') }}" class="{{ $menuItem }}">{{ __('shop.favorites.title') }}</a>
                <form method="post" action="{{ route('logout') }}" class="border-t border-line-soft pt-1">
                    @csrf
                    <button type="submit" class="{{ $menuItem }}">{{ __('shop.auth.logout') }}</button>
                </form>
            </div>
        </details>
    @endif

    <a href="{{ route('favorites') }}" data-favorite-link @if ($shell->favoritesCount === 0) hidden @endif class="{{ $tile }}">
        <x-favorites.heart class="size-6" />
        <span class="text-xs leading-none max-md:sr-only">{{ __('shop.favorites.header') }}</span>
        <span data-favorite-count class="{{ $badge }} bg-accent-ink text-white">{{ $shell->favoritesCount }}</span>
    </a>

    <a href="{{ route('compare') }}" data-compare-link @if ($shell->compareCount === 0) hidden @endif class="{{ $tile }}">
        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 20V10M12 20V4M19 20v-7"/>
        </svg>
        <span class="text-xs leading-none max-md:sr-only">{{ __('shop.compare.header') }}</span>
        <span data-compare-count class="{{ $badge }} bg-accent-ink text-white">{{ $shell->compareCount }}</span>
    </a>

    <a
        href="{{ route('cart') }}"
        data-cart-link
        class="relative flex h-control items-center gap-2.5 rounded-control bg-accent-ink text-white transition-colors duration-150 ease-out hover:bg-accent-dark max-md:w-control max-md:justify-center md:px-3.5"
    >
        <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 5h2.5l2 10h9l2-7H7M9 19.5a1 1 0 1 0 0 .01M17 19.5a1 1 0 1 0 0 .01"/>
        </svg>
        <span class="flex flex-col max-md:sr-only">
            <span class="sr-only" data-cart-caption @if ($shell->cart->isEmpty()) hidden @endif>{{ __('shop.cart.title') }}:</span>
            <span data-cart-positions class="text-sm leading-tight font-medium">{{ $shell->cart->label() }}</span>
            <span data-cart-total @if ($shell->cart->isEmpty()) hidden @endif class="text-base leading-tight font-semibold whitespace-nowrap tabular">{{ $shell->cart->totalLabel() }}</span>
        </span>
        <span data-cart-badge @if ($shell->cart->isEmpty()) hidden @endif class="{{ $badge }} border border-accent-ink bg-surface text-accent-ink md:hidden" aria-hidden="true">{{ $shell->cart->positions }}</span>
    </a>
</div>
