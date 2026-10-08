@props(['shell'])

{{--
    Правая часть шапки-таблетки (облик «Свечение»). Не оборачивается в общий блок: «Войти» и
    «Корзина» — прямые потомки .gl-pill, чтобы на телефоне встать в нужные строки (glow.css,
    правила order). «Избранное» и «Сравнение» видны, пока в них есть модели, — круглые кнопки
    с числом. «Корзина» — оранжевая кнопка: «Корзина» или число позиций, на широкой шапке и сумма,
    на телефоне — значок с числом. Счётчики после «Сравнить», «В избранное» и «В корзину» обновляет
    скрипт витрины (data-*-link, data-*-count, data-cart-*). Гостю — «Войти», вошедшему — кнопка
    с именем и меню кабинета на <details>: раскрывается без скриптов, скрипт закрывает его по
    Esc и нажатию мимо.
--}}
@php
    $userIcon = '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c.9-3.9 4-6 7.5-6s6.6 2.1 7.5 6"/>';
@endphp

@if ($shell->customerName === null)
    <a href="{{ route('login') }}" class="gl-login" aria-label="{{ __('shop.layout.login_aria') }}">
        <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true">{!! $userIcon !!}</svg>
        <span>{{ __('shop.auth.login.submit') }}</span>
    </a>
@else
    <details data-dismissable class="gl-acct">
        <summary class="gl-login [&::-webkit-details-marker]:hidden" aria-label="{{ __('shop.auth.menu', ['name' => $shell->customerName]) }}">
            <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true">{!! $userIcon !!}</svg>
            <span class="max-w-28 truncate" aria-hidden="true">{{ $shell->customerFirstName() }}</span>
        </summary>

        <div class="gl-sheet absolute top-full right-0 z-40 mt-2 flex w-72 max-w-full flex-col gap-1 p-2">
            <p class="flex flex-col px-3.5 py-2">
                <span class="truncate text-base font-bold text-white">{{ $shell->customerName }}</span>
                <span class="truncate text-sm text-steel-500">{{ $shell->customerEmail }}</span>
            </p>
            <a href="{{ route('account') }}" class="gl-row">{{ __('shop.account.menu') }}</a>
            <a href="{{ route('account.orders') }}" class="gl-row">{{ __('shop.account.menu_orders') }}</a>
            <a href="{{ route('favorites') }}" class="gl-row">{{ __('shop.favorites.title') }}</a>
            <form method="post" action="{{ route('logout') }}" class="mt-1 border-t border-white/10 pt-1">
                @csrf
                <button type="submit" class="gl-row">{{ __('shop.auth.logout') }}</button>
            </form>
        </div>
    </details>
@endif

<a href="{{ route('favorites') }}" data-favorite-link @if ($shell->favoritesCount === 0) hidden @endif class="gl-round gl-iconlink">
    <x-favorites.heart class="size-5" />
    <span class="sr-only">{{ __('shop.favorites.header') }}</span>
    <span data-favorite-count class="gl-count">{{ $shell->favoritesCount }}</span>
</a>

<a href="{{ route('compare') }}" data-compare-link @if ($shell->compareCount === 0) hidden @endif class="gl-round gl-iconlink">
    <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
    <span class="sr-only">{{ __('shop.compare.header') }}</span>
    <span data-compare-count class="gl-count">{{ $shell->compareCount }}</span>
</a>

<a href="{{ route('cart') }}" data-cart-link class="gl-btn gl-btn--hot gl-btn--sm gl-cartbtn">
    <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.8l2.3 11.2h10.4l2-8.2H6.2"/></svg>
    <span class="sr-only" data-cart-caption @if ($shell->cart->isEmpty()) hidden @endif>{{ __('shop.cart.title') }}:</span>
    <span data-cart-positions class="gl-cartbtn__pos">{{ $shell->cart->label() }}</span>
    <span data-cart-total @if ($shell->cart->isEmpty()) hidden @endif class="gl-cartbtn__sum gl-num">{{ $shell->cart->totalLabel() }}</span>
    <span data-cart-badge @if ($shell->cart->isEmpty()) hidden @endif class="gl-count" aria-hidden="true">{{ $shell->cart->positions }}</span>
</a>
