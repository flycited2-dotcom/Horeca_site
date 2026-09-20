@props(['price' => null, 'size' => 'card'])

{{--
    Цена (ТЗ §7, §9). Нет цены — «Цена по запросу», а не пустое место. Оптовику
    розничная цена показывается зачёркнутой, его собственная — как «Ваша цена».
--}}
@php
    $amountClass = $size === 'page' ? 'text-2xl' : 'text-xl';
@endphp

<div {{ $attributes->class('flex flex-col gap-1') }}>
    @if ($price === null)
        <span class="{{ $amountClass }} font-bold text-steel-500">{{ __('shop.price.on_request') }}</span>
    @elseif ($price->isWholesale && $price->hasDiscount())
        <span class="text-sm text-steel-500 line-through tabular">{{ \App\Support\Typography::money($price->retail) }}</span>
        <span class="{{ $amountClass }} font-bold tabular">{{ \App\Support\Typography::money($price->amount) }}</span>
        <span class="text-sm text-steel-500">
            {{ __('shop.price.yours') }}@if ($price->tierName) · {{ $price->tierName }} @endif
        </span>
    @else
        @if ($price->oldPrice)
            <span class="text-sm text-steel-500 line-through tabular">{{ \App\Support\Typography::money($price->oldPrice) }}</span>
        @endif
        <span class="{{ $amountClass }} font-bold tabular">{{ \App\Support\Typography::money($price->amount) }}</span>
    @endif
</div>
