@props(['shell'])

{{--
    Правая часть шапки (макет, экран 5): плитки «значок над подписью» и корзина — синяя
    кнопка с числом позиций и суммой; на телефоне — значки со счётчиками. «Сравнение»
    видно, пока в нём есть модели. Счётчики после «Сравнить» и «В корзину» обновляет
    скрипт витрины. Вход и избранное встанут сюда же со своими спринтами.
--}}
@php
    $tile = 'relative flex h-control items-center justify-center rounded-control transition-colors duration-150 ease-out hover:bg-bg max-md:w-control md:w-16 md:flex-col md:gap-1';
    $badge = 'absolute top-0.5 right-0.5 min-w-4.5 rounded-full px-1 text-center text-[11px] leading-4.5 font-semibold tabular md:top-0 md:right-2';
@endphp

<div {{ $attributes->class('flex items-center gap-1 md:gap-2') }}>
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
