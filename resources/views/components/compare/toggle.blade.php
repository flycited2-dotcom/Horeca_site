@props(['product', 'variant' => 'link'])

{{--
    «Сравнить» (ТЗ §8.5): на карточке листинга и в строке поиска — ссылкой под кнопкой
    покупки (макет, экран 8), на странице товара — нейтральной кнопкой «К сравнению»
    (экран 3). Обе формы работают без скриптов; скрипт витрины отправляет их без
    перезагрузки и показывает ту, что соответствует новому состоянию.
--}}
@inject('compare', 'App\Services\Compare\CompareList')

@php
    $compared = $compare->contains($product->id, request()->user());
    $link = 'tap-target inline-flex h-8 w-full items-center justify-center gap-1.5 text-sm text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark';
@endphp

<div data-compare="{{ $product->id }}" {{ $attributes }}>
    <form method="post" action="{{ route('compare.add', $product->id) }}" data-compare-form @if ($compared) hidden @endif>
        @csrf
        @if ($variant === 'button')
            <x-ui.button type="submit" variant="neutral" class="w-full text-sm">{{ __('shop.compare.add_page') }}</x-ui.button>
        @else
            <button type="submit" class="{{ $link }}">{{ __('shop.compare.add') }}</button>
        @endif
    </form>

    <form method="post" action="{{ route('compare.remove', $product->id) }}" data-compare-form @unless ($compared) hidden @endunless>
        @csrf
        @method('DELETE')
        @if ($variant === 'button')
            <x-ui.button type="submit" variant="neutral" class="w-full text-sm">
                <svg class="size-4 text-stock-dot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                {{ __('shop.compare.remove') }}
            </x-ui.button>
        @else
            <button type="submit" class="{{ $link }}">
                <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                {{ __('shop.compare.remove') }}
            </button>
        @endif
    </form>
</div>
